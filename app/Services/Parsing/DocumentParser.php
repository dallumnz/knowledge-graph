<?php

namespace App\Services\Parsing;

/**
 * DocumentParser implementation.
 *
 * Parses Markdown and HTML content to extract document structure
 * including headings, tables, code blocks, and lists with hierarchy depth
 * and section type information.
 */
class DocumentParser implements IDocumentParser
{
    /**
     * {@inheritdoc}
     */
    public function parse(string $content): DocumentStructure
    {
        if (trim($content) === '') {
            return new DocumentStructure();
        }

        $elements = [];
        $lines = preg_split('/\r\n|\r|\n/', $content);
        $lineCount = count($lines);

        // Parse each structural element
        $headings = $this->parseHeadings($content);
        $tables = $this->parseTables($content);
        $codeBlocks = $this->parseCodeBlocks($content);
        $lists = $this->parseLists($content);

        // Build elements array with line positions
        $elements = $this->buildElements($content, $lines);

        // Calculate max depth from headings
        $maxDepth = 0;
        foreach ($headings as $heading) {
            $level = 1;
            foreach (['h1', 'h2', 'h3', 'h4', 'h5', 'h6'] as $i => $h) {
                if (isset($heading[$h]) && $heading[$h] !== '') {
                    $level = $i + 1;
                    break;
                }
            }
            $maxDepth = max($maxDepth, $level);
        }

        return new DocumentStructure(
            elements: $elements,
            headings: $headings,
            tables: $tables,
            lists: $lists,
            codeBlocks: $codeBlocks,
            maxDepth: $maxDepth
        );
    }

    /**
     * {@inheritdoc}
     */
    public function detectType(string $content): string
    {
        $trimmed = trim($content);

        // Check for HTML tags
        if (preg_match('/^<[a-z][a-z0-9]*[^>]*>/i', $trimmed)) {
            return 'html';
        }

        // Check for Markdown headers or common Markdown syntax
        if (
            preg_match('/^(#{1,6}\s+)/m', $trimmed) ||
            preg_match('/^\s*[-*+]\s/m', $trimmed) ||
            preg_match('/^\s*\d+\.\s/m', $trimmed) ||
            str_contains($trimmed, '```')
        ) {
            return 'markdown';
        }

        return 'unknown';
    }

    /**
     * {@inheritdoc}
     */
    public function parseHeadings(string $content): array
    {
        $headings = [];

        // Parse Markdown headings (# Heading, ## Heading, etc.)
        $markdownPattern = '/^(#{1,6})\s+(.+)$/m';
        if (preg_match_all($markdownPattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[0] as $index => $match) {
                $level = strlen($matches[1][$index][0]);
                $text = trim($matches[2][$index][0]);
                $heading = ['level' => $level, 'text' => $text];
                $this->addHeadingToHierarchy($headings, $heading);
            }
        }

        // Parse HTML headings (<h1>...</h1>, <h2>...</h2>, etc.)
        $htmlPattern = '/<h([1-6])[^>]*>(.*?)<\/h\1>/is';
        if (preg_match_all($htmlPattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[0] as $index => $match) {
                $level = (int) $matches[1][$index][0];
                $text = trim(preg_replace('/<[^>]+>/', '', $matches[2][$index][0]));
                $heading = ['level' => $level, 'text' => $text];
                $this->addHeadingToHierarchy($headings, $heading);
            }
        }

        return $headings;
    }

    /**
     * Add a heading to the hierarchical structure.
     */
    private function addHeadingToHierarchy(array &$headings, array $heading): void
    {
        $level = $heading['level'];
        $text = $heading['text'];

        $hKey = 'h' . $level;

        // Find the last heading at a higher or equal level
        $insertIndex = count($headings);
        for ($i = count($headings) - 1; $i >= 0; $i--) {
            $existingLevel = 1;
            foreach (['h1', 'h2', 'h3', 'h4', 'h5', 'h6'] as $j => $h) {
                if (isset($headings[$i][$h]) && $headings[$i][$h] !== '') {
                    $existingLevel = $j + 1;
                    break;
                }
            }

            if ($existingLevel <= $level) {
                $insertIndex = $i + 1;
                break;
            }
        }

        // Build new heading entry
        $newHeading = [];
        for ($i = 1; $i <= 6; $i++) {
            $newHeading['h' . $i] = '';
        }
        $newHeading[$hKey] = $text;

        // If inserting after an existing heading, inherit parent headings
        if ($insertIndex > 0 && $insertIndex <= count($headings)) {
            $previous = $headings[$insertIndex - 1] ?? [];
            for ($i = 1; $i < $level; $i++) {
                if (isset($previous['h' . $i]) && $previous['h' . $i] !== '') {
                    $newHeading['h' . $i] = $previous['h' . $i];
                }
            }
        }

        array_splice($headings, $insertIndex, 0, [$newHeading]);
    }

    /**
     * {@inheritdoc}
     */
    public function parseTables(string $content): array
    {
        $tables = [];

        // Parse Markdown tables (| header | header |, |---|, | cell |)
        $markdownTablePattern = '/(?:\|[^\n]*\|\n)(?:\|[\s\-:]*\|\n)?(?:\|[^\n]*\|)+/';
        if (preg_match_all($markdownTablePattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[0] as $match) {
                $tableText = $match[0];
                $rows = $this->parseMarkdownTable($tableText);
                if ($rows !== null) {
                    $tables[] = $rows;
                }
            }
        }

        // Parse HTML tables (<table>...</table>)
        $htmlTablePattern = '/<table[^>]*>.*?<\/table>/is';
        if (preg_match_all($htmlTablePattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[0] as $match) {
                $table = $this->parseHtmlTable($match[0]);
                if ($table !== null) {
                    $tables[] = $table;
                }
            }
        }

        return $tables;
    }

    /**
     * Parse a Markdown table string.
     */
    private function parseMarkdownTable(string $tableText): ?array
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($tableText));
        if (count($lines) < 2) {
            return null;
        }

        $headers = $this->parseMarkdownRow($lines[0]);
        $rows = [];

        // Skip separator row if present
        $startRow = 1;
        if (preg_match('/^[\|\-\+\s]+$/', $lines[1]) || preg_match('/\|[\-\:]+/', $lines[1])) {
            $startRow = 2;
        }

        for ($i = $startRow; $i < count($lines); $i++) {
            $row = $this->parseMarkdownRow($lines[$i]);
            if (!empty($row)) {
                $rows[] = $row;
            }
        }

        return [
            'headers' => $headers,
            'rows' => $rows,
        ];
    }

    /**
     * Parse a single Markdown table row.
     */
    private function parseMarkdownRow(string $row): array
    {
        $row = trim($row);
        if ($row === '' || $row === '|') {
            return [];
        }

        // Remove leading and trailing pipes
        $row = preg_replace('/^\||\|$/', '', $row);

        return array_map('trim', explode('|', $row));
    }

    /**
     * Parse an HTML table.
     */
    private function parseHtmlTable(string $tableHtml): ?array
    {
        $headers = [];
        $rows = [];

        // Extract headers
        $thPattern = '/<th[^>]*>(.*?)<\/th>/is';
        if (preg_match_all($thPattern, $tableHtml, $matches)) {
            $headers = array_map(fn($m) => trim(preg_replace('/<[^>]+>/', '', $m)), $matches[1]);
        }

        // Extract rows
        $trPattern = '/<tr[^>]*>(.*?)<\/tr>/is';
        if (preg_match_all($trPattern, $tableHtml, $matches)) {
            foreach ($matches[1] as $rowHtml) {
                $cells = [];
                $tdPattern = '/<t[dh][^>]*>(.*?)<\/t[dh]>/is';
                if (preg_match_all($tdPattern, $rowHtml, $cellMatches)) {
                    $cells = array_map(fn($c) => trim(preg_replace('/<[^>]+>/', '', $c)), $cellMatches[1]);
                }
                if (!empty($cells)) {
                    $rows[] = $cells;
                }
            }
        }

        if (empty($headers) && empty($rows)) {
            return null;
        }

        return [
            'headers' => $headers,
            'rows' => $rows,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function parseCodeBlocks(string $content): array
    {
        $codeBlocks = [];

        // Parse Markdown fenced code blocks (```)
        $fencedPattern = '/```(\w*)\n([\s\S]*?)```/';
        if (preg_match_all($fencedPattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[0] as $index => $match) {
                $language = isset($matches[1][$index][0]) ? trim($matches[1][$index][0]) : '';
                $code = isset($matches[2][$index][0]) ? trim($matches[2][$index][0]) : '';
                $codeBlocks[] = [
                    'type' => 'fenced',
                    'content' => $code,
                    'language' => $language,
                ];
            }
        }

        // Parse Markdown indented code blocks (4+ spaces or 1+ tab)
        $indentedPattern = /(?:^|\n)(    |\t)(.+)/';
        if (preg_match_all($indentedPattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[0] as $match) {
                $codeBlocks[] = [
                    'type' => 'indented',
                    'content' => trim($match[0]),
                ];
            }
        }

        // Parse HTML code blocks (<code>...</code>)
        $inlineCodePattern = '/<code[^>]*>(.*?)<\/code>/is';
        if (preg_match_all($inlineCodePattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[0] as $index => $match) {
                $code = $matches[1][$index][0];
                // Check if it's inline or block
                $isBlock = preg_match('/<pre[^>]*>/is', substr($content, max(0, $match[1] - 10), $match[1] + 20));
                $codeBlocks[] = [
                    'type' => $isBlock ? 'block' : 'inline',
                    'content' => trim($code),
                ];
            }
        }

        // Parse HTML pre blocks (<pre>...</pre>)
        $prePattern = '/<pre[^>]*>(.*?)<\/pre>/is';
        if (preg_match_all($prePattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[0] as $match) {
                $code = preg_replace('/<code[^>]*>(.*?)<\/code>/is', '$1', $matches[1][$index][0] ?? $match[0]);
                $codeBlocks[] = [
                    'type' => 'pre',
                    'content' => trim(preg_replace('/<[^>]+>/', '', $code)),
                ];
            }
        }

        return $codeBlocks;
    }

    /**
     * {@inheritdoc}
     */
    public function parseLists(string $content): array
    {
        $lists = [];

        // Parse Markdown unordered lists (-, *, +)
        $unorderedPattern = '/^([\-\*\+])\s+(.+)$/m';
        $this->parseListWithPattern($content, $unorderedPattern, 'unordered', $lists);

        // Parse Markdown ordered lists (1., 2., etc.)
        $orderedPattern = '/^(\d+\.)\s+(.+)$/m';
        $this->parseListWithPattern($content, $orderedPattern, 'ordered', $lists);

        // Parse HTML unordered lists (<ul>...</ul>)
        $this->parseHtmlList($content, '/<ul[^>]*>(.*?)<\/ul>/is', 'unordered', $lists);

        // Parse HTML ordered lists (<ol>...</ol>)
        $this->parseHtmlList($content, '/<ol[^>]*>(.*?)<\/ol>/is', 'ordered', $lists);

        return $lists;
    }

    /**
     * Parse a list using a regex pattern.
     */
    private function parseListWithPattern(string $content, string $pattern, string $type, array &$lists): void
    {
        if (preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
            $currentList = null;
            $lastOffset = -1;

            foreach ($matches[0] as $index => $match) {
                $offset = $match[1];
                $item = trim($matches[2][$index][0] ?? $match[0]);

                // If we jumped to a different list, save the current one
                if ($currentList !== null && $offset > $lastOffset + 100) {
                    $lists[] = $currentList;
                    $currentList = null;
                }

                if ($currentList === null) {
                    $currentList = [
                        'type' => $type,
                        'items' => [],
                    ];
                }

                $currentList['items'][] = $item;
                $lastOffset = $offset;
            }

            // Don't forget the last list
            if ($currentList !== null) {
                $lists[] = $currentList;
            }
        }
    }

    /**
     * Parse an HTML list.
     */
    private function parseHtmlList(string $content, string $pattern, string $type, array &$lists): void
    {
        if (preg_match_all($pattern, $content, $matches)) {
            foreach ($matches[1] as $listHtml) {
                $items = [];
                $liPattern = '/<li[^>]*>(.*?)<\/li>/is';
                if (preg_match_all($liPattern, $listHtml, $liMatches)) {
                    foreach ($liMatches[1] as $itemHtml) {
                        $items[] = trim(preg_replace('/<[^>]+>/', '', $itemHtml));
                    }
                }

                if (!empty($items)) {
                    $lists[] = [
                        'type' => $type,
                        'items' => $items,
                    ];
                }
            }
        }
    }

    /**
     * Build the elements array with line positions.
     */
    private function buildElements(string $content, array $lines): array
    {
        $elements = [];

        // Track positions for each element type
        $headingPattern = '/^(#{1,6})\s+|<\/h[1-6]>/';
        $tablePattern = '/(?:<table|<tr|<th|<td|\||---)/';
        $codePattern = '/(```|<code|<pre)/';
        $listPattern = '/^[\-\*\+]\s+|^\d+\.\s+|<[ou]l|<li/';

        foreach ($lines as $lineIndex => $line) {
            $trimmed = trim($line);
            if ($trimmed === '') {
                continue;
            }

            // Detect element type
            if (preg_match($headingPattern, $line)) {
                $level = 1;
                if (preg_match('/^#{1,6}/', $trimmed)) {
                    $level = strlen(preg_replace('/#.*$/', '', $trimmed));
                } elseif (preg_match('/<h([1-6])/', $line, $m)) {
                    $level = (int) $m[1];
                }

                $elements[] = new DocumentElement(
                    type: 'heading',
                    content: $line,
                    level: $level,
                    startLine: $lineIndex + 1,
                    endLine: $lineIndex + 1,
                    metadata: ['heading' => trim(preg_replace('/#+\s*/', '', $trimmed))]
                );
            } elseif (preg_match($tablePattern, $line)) {
                $elements[] = new DocumentElement(
                    type: 'table',
                    content: $line,
                    level: 0,
                    startLine: $lineIndex + 1,
                    endLine: $lineIndex + 1
                );
            } elseif (preg_match($codePattern, $line)) {
                $elements[] = new DocumentElement(
                    type: 'code',
                    content: $line,
                    level: 0,
                    startLine: $lineIndex + 1,
                    endLine: $lineIndex + 1
                );
            } elseif (preg_match($listPattern, $trimmed)) {
                $elements[] = new DocumentElement(
                    type: 'list',
                    content: $line,
                    level: 0,
                    startLine: $lineIndex + 1,
                    endLine: $lineIndex + 1
                );
            } else {
                $elements[] = new DocumentElement(
                    type: 'paragraph',
                    content: $line,
                    level: 0,
                    startLine: $lineIndex + 1,
                    endLine: $lineIndex + 1
                );
            }
        }

        return $elements;
    }
}
