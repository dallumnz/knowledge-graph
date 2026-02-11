<?php

namespace App\Services\Chunking;

/**
 * DocumentChunker implementation.
 *
 * Chunks text content while respecting document boundaries:
 * - Markdown headers (#, ##, ###, etc.)
 * - HTML headers (<h1> through <h6>)
 * - Paragraph integrity (no mid-paragraph splits)
 * - Lists as atomic units
 */
class DocumentChunker implements IDocumentChunker
{
    /**
     * {@inheritdoc}
     */
    public function chunkText(string $content, int $size = 500, int $overlap = 0): array
    {
        if (trim($content) === '') {
            return [];
        }

        // Split content into logical sections based on headers
        $sections = $this->splitIntoSections($content);

        // If no sections found, fall back to simple chunking
        if (count($sections) === 0) {
            return $this->simpleChunk(trim($content), $size, $overlap);
        }

        // Process each section and merge into chunks
        return $this->processSections($sections, $size, $overlap);
    }

    /**
     * Split content into sections based on headers.
     *
     * @return array<int, array{header: string, content: string}>
     */
    private function splitIntoSections(string $content): array
    {
        $sections = [];

        // Split by Markdown headers
        $markdownPattern = '/^(#{1,6})\s+(.+)$/m';
        if (preg_match_all($markdownPattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
            $sections = $this->buildSectionsFromMatches($content, $matches, 'markdown');
        }

        // If no Markdown headers found, try HTML headers
        if (count($sections) === 0) {
            $htmlPattern = '/<h([1-6])[^>]*>(.*?)<\/h\1>/is';
            if (preg_match_all($htmlPattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
                $sections = $this->buildSectionsFromMatches($content, $matches, 'html');
            }
        }

        return $sections;
    }

    /**
     * Build sections from header matches.
     *
     * @return array<int, array{header: string, content: string}>
     */
    private function buildSectionsFromMatches(
        string $content,
        array $matches,
        string $type
    ): array {
        $sections = [];
        $prevOffset = 0;

        foreach ($matches[0] as $index => $match) {
            $offset = $match[1];

            // Get content before this header (if any)
            if ($offset > $prevOffset) {
                $beforeContent = trim(substr($content, $prevOffset, $offset - $prevOffset));
                if ($beforeContent !== '') {
                    $sections[] = [
                        'header' => '',
                        'content' => $beforeContent,
                    ];
                }
            }

            // Extract header text
            $headerText = $type === 'markdown'
                ? trim($matches[2][$index][0])
                : trim(strip_tags($matches[2][$index][0]));

            // Find end of this section (next header or end of content)
            $nextOffset = null;
            if (isset($matches[0][$index + 1])) {
                $nextOffset = $matches[0][$index + 1][1];
            }

            $sectionContent = trim(substr($content, $offset, $nextOffset ? $nextOffset - $offset : strlen($content) - $offset));

            // Remove header from section content
            if ($type === 'markdown') {
                $sectionContent = trim(preg_replace('/^#{1,6}\s+.+$/m', '', $sectionContent));
            } elseif ($type === 'html') {
                $sectionContent = preg_replace('/<h[1-6][^>]*>.*?<\/h\1>/is', '', $sectionContent);
                $sectionContent = trim($sectionContent);
            }

            $sections[] = [
                'header' => $headerText,
                'content' => $sectionContent,
            ];

            $prevOffset = $offset;
        }

        // Handle content after last header
        if ($prevOffset < strlen($content)) {
            $afterContent = trim(substr($content, $prevOffset));
            if ($afterContent !== '') {
                $sections[] = [
                    'header' => '',
                    'content' => $afterContent,
                ];
            }
        }

        return $sections;
    }

    /**
     * Process sections into chunks.
     *
     * @param array<int, array{header: string, content: string}> $sections
     * @return array<int, string>
     */
    private function processSections(array $sections, int $size, int $overlap): array
    {
        $chunks = [];
        $currentChunk = '';

        foreach ($sections as $section) {
            $header = $section['header'];
            $content = $section['content'];

            // Prefix content with header if present
            $sectionText = $header !== '' ? "## {$header}\n\n{$content}" : $content;

            // Handle lists as atomic units
            $parts = $this->splitIntoParts($sectionText);

            foreach ($parts as $part) {
                $part = trim($part);
                if ($part === '') {
                    continue;
                }

                // Check if adding this part would exceed chunk size
                if (strlen($currentChunk) + strlen($part) > $size && $currentChunk !== '') {
                    // Save current chunk and start new one
                    $chunks[] = trim($currentChunk);
                    $currentChunk = $part;

                    // Add overlap from previous chunk
                    if ($overlap > 0 && count($chunks) > 0) {
                        $previousChunk = $chunks[count($chunks) - 1];
                        $overlapText = $this->extractOverlapText($previousChunk, $overlap);
                        $currentChunk = $overlapText . ' ' . $currentChunk;
                    }
                } else {
                    $currentChunk .= ($currentChunk !== '' ? "\n\n" : '') . $part;
                }
            }
        }

        // Add final chunk
        if (trim($currentChunk) !== '') {
            $chunks[] = trim($currentChunk);
        }

        // If no chunks created, fall back to simple chunking
        if (count($chunks) === 0) {
            return $this->simpleChunk(trim($content ?? ''), $size, $overlap);
        }

        return $chunks;
    }

    /**
     * Split content into parts, preserving lists as atomic units.
     *
     * @return array<int, string>
     */
    private function splitIntoParts(string $content): array
    {
        $parts = [];

        // Match list patterns (Markdown and HTML)
        $listPatterns = [
            // Markdown ordered lists: "1.", "2.", etc.
            '/^(\d+\.\s+.+(?:\n\s*.+)*)/m',
            // Markdown unordered lists: "- ", "* ", "+ "
            '/^([\-\*\+]\s+.+(?:\n\s*.+)*)/m',
            // HTML lists
            '/<[ou]l[^>]*>.*?<\/[ou]l>/is',
        ];

        // First, extract lists as separate parts
        $remaining = $content;
        $lists = [];

        foreach ($listPatterns as $pattern) {
            preg_match_all($pattern, $remaining, $matches, PREG_OFFSET_CAPTURE);
            foreach ($matches[0] as $match) {
                $lists[] = [
                    'offset' => $match[1],
                    'text' => $match[0],
                ];
            }
        }

        // Sort lists by offset
        usort($lists, fn($a, $b) => $a['offset'] - $b['offset']);

        // Extract non-list content between lists
        $lastOffset = 0;
        foreach ($lists as $list) {
            if ($list['offset'] > $lastOffset) {
                $nonList = trim(substr($remaining, $lastOffset, $list['offset'] - $lastOffset));
                if ($nonList !== '') {
                    $parts[] = $nonList;
                }
            }
            $parts[] = trim($list['text']);
            $lastOffset = $list['offset'] + strlen($list['text']);
        }

        // Handle remaining content after last list
        if ($lastOffset < strlen($remaining)) {
            $remainingPart = trim(substr($remaining, $lastOffset));
            if ($remainingPart !== '') {
                $parts[] = $remainingPart;
            }
        }

        // If no lists found, split by paragraphs
        if (count($parts) === 0) {
            $paragraphs = preg_split('/\n\n+/', $content, -1, PREG_SPLIT_NO_EMPTY);
            foreach ($paragraphs as $paragraph) {
                $trimmed = trim($paragraph);
                if ($trimmed !== '') {
                    $parts[] = $trimmed;
                }
            }
        }

        return $parts;
    }

    /**
     * Simple chunking fallback for text without clear boundaries.
     *
     * @return array<int, string>
     */
    private function simpleChunk(string $content, int $size, int $overlap): array
    {
        if (strlen($content) <= $size) {
            return [$content];
        }

        $chunks = [];
        $sentences = preg_split('/(?<=[.!?])\s+/', $content, -1, PREG_SPLIT_NO_EMPTY);

        $currentChunk = '';
        foreach ($sentences as $sentence) {
            if (strlen($currentChunk) + strlen($sentence) > $size && $currentChunk !== '') {
                $chunks[] = trim($currentChunk);
                $currentChunk = $sentence;

                // Add overlap from previous chunk
                if ($overlap > 0 && count($chunks) > 0) {
                    $previousChunk = $chunks[count($chunks) - 1];
                    $overlapText = $this->extractOverlapText($previousChunk, $overlap);
                    $currentChunk = $overlapText . ' ' . $currentChunk;
                }
            } else {
                $currentChunk .= ($currentChunk !== '' ? ' ' : '') . $sentence;
            }
        }

        if (trim($currentChunk) !== '') {
            $chunks[] = trim($currentChunk);
        }

        return $chunks;
    }

    /**
     * Extract overlap text from the end of a chunk.
     */
    private function extractOverlapText(string $chunk, int $overlapSize): string
    {
        if (strlen($chunk) <= $overlapSize) {
            return $chunk;
        }

        $text = substr($chunk, -$overlapSize);

        // Try to end at a sentence boundary
        $lastPeriod = strrpos($text, '.');
        $lastExclamation = strrpos($text, '!');
        $lastQuestion = strrpos($text, '?');

        $lastPunctuation = max($lastPeriod, $lastExclamation, $lastQuestion);
        if ($lastPunctuation !== false && $lastPunctuation > $overlapSize * 0.5) {
            return trim(substr($text, $lastPunctuation + 1));
        }

        // Fall back to word boundary
        $lastSpace = strrpos($text, ' ');
        if ($lastSpace !== false && $lastSpace > $overlapSize * 0.3) {
            return trim(substr($text, $lastSpace));
        }

        return trim($text);
    }
}
