<?php

namespace App\Services\Chunking;

/**
 * Interface for DocumentChunker.
 *
 * Defines the contract for smart text chunking that respects
 * document boundaries such as headers, paragraphs, and lists.
 */
interface IDocumentChunker
{
    /**
     * Chunk text into smaller pieces respecting document boundaries.
     *
     * @param string $content The text content to chunk
     * @param int $size Maximum chunk size in characters
     * @param int $overlap Overlap between chunks in characters
     * @return array<int, string> Array of text chunks
     */
    public function chunkText(string $content, int $size = 500, int $overlap = 0): array;
}
