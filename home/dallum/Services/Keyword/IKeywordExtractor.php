<?php

namespace App\Services\Keyword;

/**
 * Interface for KeywordExtractor service.
 *
 * Defines the contract for extracting and weighting keywords
 * from text chunks using TF-IDF and other algorithms.
 */
interface IKeywordExtractor
{
    /**
     * Extract weighted keywords from a collection of text chunks.
     *
     * Uses TF-IDF to identify important keywords and assigns weights
     * based on frequency, position, and domain-specific vocabulary.
     *
     * @param array<int, array{content: string, id?: int|string, position?: int}> $chunks
     * @return array<int, array{chunk_id?: int|string, keywords: array<int, array{keyword: string, weight: float}>}>
     */
    public function extractWeighted(array $chunks): array;

    /**
     * Calculate weights for a list of keywords within a context.
     *
     * Uses TF-IDF scoring where:
     * - TF (Term Frequency): How often the term appears
     * - IDF (Inverse Document Frequency): How rare the term is across documents
     *
     * Also applies position weighting (earlier = higher weight)
     * and domain vocabulary boost.
     *
     * @param array<int, string> $keywords Keywords to weight
     * @param array{content: string, position?: int}|null $context Optional context for position weighting
     * @return array<int, array{keyword: string, weight: float}>
     */
    public function weightKeywords(array $keywords, ?array $context = null): array;

    /**
     * Extract keywords from a single text using TF-IDF.
     *
     * @param string $text The text to extract keywords from
     * @param int $count Maximum number of keywords to extract
     * @return array<int, array{keyword: string, weight: float}>
     */
    public function extractFromText(string $text, int $count = 10): array;

    /**
     * Set domain-specific vocabulary for keyword boosting.
     *
     * Keywords in this vocabulary will receive a boost factor
     * in their weight calculation.
     *
     * @param array<int, string> $vocabulary List of domain-specific terms
     * @param float $boostFactor Multiplier for vocabulary terms (default: 1.5)
     * @return self
     */
    public function setDomainVocabulary(array $vocabulary, float $boostFactor = 1.5): self;

    /**
     * Store weighted keywords for chunks in the database.
     *
     * @param array<int, array{chunk_id: int, keywords: array<int, array{keyword: string, weight: float}>}> $weightedKeywords
     * @return bool True if stored successfully
     */
    public function storeWeightedKeywords(array $weightedKeywords): bool;
}
