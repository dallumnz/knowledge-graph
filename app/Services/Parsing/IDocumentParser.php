<?php

namespace App\Services\Parsing;

/**
 * Interface for DocumentParser.
 *
 * Defines the contract for parsing document content (Markdown or HTML)
 * and extracting structural metadata including headings, tables, code blocks,
 * and lists with hierarchy information.
 */
interface IDocumentParser
{
    /**
     * Parse document content and return its structure.
     *
     * @param string $content The document content to parse (Markdown or HTML)
     * @return DocumentStructure The parsed document structure
     */
    public function parse(string $content): DocumentStructure;

    /**
     * Detect the content type (markdown or html).
     *
     * @param string $content The content to detect
     * @return string 'markdown' | 'html' | 'unknown'
     */
    public function detectType(string $content): string;

    /**
     * Parse only headings from the content.
     *
     * @param string $content The content to parse
     * @return array<int, array{h1?: string, h2?: string, h3?: string, h4?: string, h5?: string, h6?: string}>
     */
    public function parseHeadings(string $content): array;

    /**
     * Parse tables from the content.
     *
     * @param string $content The content to parse
     * @return array<int, array{rows: array<int, array<int, string>>, headers: array<int, string>}>
     */
    public function parseTables(string $content): array;

    /**
     * Parse code blocks from the content.
     *
     * @param string $content The content to parse
     * @return array<int, array{type: string, content: string, language?: string}>
     */
    public function parseCodeBlocks(string $content): array;

    /**
     * Parse lists from the content.
     *
     * @param string $content The content to parse
     * @return array<int, array{type: string, items: array<int, string>}>
     */
    public function parseLists(string $content): array;
}
