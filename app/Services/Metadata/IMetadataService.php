<?php

namespace App\Services\Metadata;

/**
 * Interface for MetadataService.
 *
 * Defines the contract for generating metadata (summaries and keywords)
 * for text chunks in the RAG pipeline.
 */
interface IMetadataService
{
    /**
     * Generate a summary of the given text.
     *
     * @param string $text The text to summarize
     * @return string A summary of 80-120 characters
     */
    public function generateSummary(string $text): string;

    /**
     * Extract significant keywords from the given text.
     *
     * @param string $text The text to extract keywords from
     * @param int $count Maximum number of keywords to extract (default: 5)
     * @return array<int, string> Array of extracted keywords
     */
    public function extractKeywords(string $text, int $count = 5): array;
}
