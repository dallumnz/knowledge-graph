<?php

namespace App\Services\Keyword;

/**
 * Interface for KeywordExtractor.
 *
 * Defines the contract for extracting and weighting keywords
 * from text chunks using TF-IDF and positional analysis.
 */
interface IKeywordExtractor
{
    /**
     * Extract keywords with weights from a single text.
     *
     * @param string $text The text to extract keywords from
     * @param int $count Maximum number of keywords to extract
     * @return array<int, array{keyword: string, weight: float}>
     */
    public function extractWeighted(string $text, int $count = 5): array;

    /**
     * Extract keywords with weights from multiple chunks.
     *
     * @param array<int, string> $chunks Array of text chunks
     * @param int $count Maximum number of keywords per chunk
     * @return array<int, array{chunk_index: int, keywords: array<int, array{keyword: string, weight: float}>}>
     */
    public function extractFromChunks(array $chunks, int $count = 5): array;

    /**
     * Calculate TF-IDF weights for keywords.
     *
     * @param array<int, string> $documents Array of document texts
     * @param array<int, string> $keywords Keywords to calculate weights for
     * @return array<string, float> Keyword => weight mapping
     */
    public function computeTfIdf(array $documents, array $keywords): array;
}
