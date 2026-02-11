<?php

namespace App\Services\Parsing;

/**
 * Represents a structural element within a document.
 */
class DocumentElement
{
    public function __construct(
        public readonly string $type,
        public readonly string $content,
        public readonly int $level,
        public readonly int $startLine,
        public readonly int $endLine,
        public readonly array $metadata = []
    ) {}
}

/**
 * Value object representing the parsed structure of a document.
 *
 * Contains hierarchical information about headings, tables, code blocks,
 * lists, and other structural elements extracted from Markdown or HTML.
 */
class DocumentStructure
{
    /**
     * @param array<int, DocumentElement> $elements All parsed elements in order
     * @param array<int, array{h1: string, h2?: string, h3?: string, h4?: string, h5?: string, h6?: string}> $headings Extracted headings with hierarchy
     * @param array<int, array{rows: array<int, array<int, string>>, headers: array<int, string>}> $tables Parsed tables
     * @param array<int, array{type: string, items: array<int, string>}> $lists Parsed lists
     * @param array<int, array{type: string, content: string}> $codeBlocks Parsed code blocks
     * @param int $maxDepth Maximum heading hierarchy depth found
     */
    public function __construct(
        public readonly array $elements = [],
        public readonly array $headings = [],
        public readonly array $tables = [],
        public readonly array $lists = [],
        public readonly array $codeBlocks = [],
        public readonly int $maxDepth = 0
    ) {}

    /**
     * Get elements by type.
     *
     * @return array<int, DocumentElement>
     */
    public function getElementsByType(string $type): array
    {
        return array_filter($this->elements, fn(DocumentElement $el) => $el->type === $type);
    }

    /**
     * Get elements within a specific heading hierarchy.
     *
     * @return array<int, DocumentElement>
     */
    public function getElementsUnderHeading(string $h1, ?string $h2 = null): array
    {
        $elements = [];
        $inSection = false;
        $targetDepth = $h2 !== null ? 2 : 1;

        foreach ($this->elements as $element) {
            if ($element->type === 'heading') {
                $heading = $element->metadata['heading'] ?? '';
                if ($heading === $h1) {
                    $inSection = true;
                    if ($h2 === null) {
                        continue;
                    }
                } elseif ($inSection && $h2 !== null && $heading === $h2) {
                    $inSection = true;
                } elseif ($element->level <= $targetDepth) {
                    $inSection = false;
                }
            } elseif ($inSection) {
                $elements[] = $element;
            }
        }

        return $elements;
    }

    /**
     * Get the table of contents as a nested structure.
     *
     * @return array<int, array{heading: string, level: int, children?: array<int, array>}>
     */
    public function getTableOfContents(): array
    {
        $toc = [];
        $currentSection = null;

        foreach ($this->headings as $heading) {
            $headingText = $heading['h1'] ?? $heading['h2'] ?? $heading['h3'] ?? '';
            $level = 1;
            foreach (['h1', 'h2', 'h3', 'h4', 'h5', 'h6'] as $i => $h) {
                if (isset($heading[$h]) && $heading[$h] !== '') {
                    $level = $i + 1;
                    $headingText = $heading[$h];
                    break;
                }
            }

            $item = ['heading' => $headingText, 'level' => $level];

            if ($level === 1 || $currentSection === null) {
                $toc[] = &$item;
                $currentSection = &$item;
                $currentSection['children'] = [];
            } elseif ($level === 2) {
                $currentSection['children'][] = $item;
            }
        }

        return $toc;
    }

    /**
     * Check if the document has a specific structure type.
     */
    public function hasType(string $type): bool
    {
        return count($this->getElementsByType($type)) > 0;
    }

    /**
     * Get statistics about the document structure.
     *
     * @return array{heading_count: int, table_count: int, list_count: int, code_block_count: int, max_depth: int}
     */
    public function getStatistics(): array
    {
        return [
            'heading_count' => count($this->headings),
            'table_count' => count($this->tables),
            'list_count' => count($this->lists),
            'code_block_count' => count($this->codeBlocks),
            'max_depth' => $this->maxDepth,
        ];
    }
}
