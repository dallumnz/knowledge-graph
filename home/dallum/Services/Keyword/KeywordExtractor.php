<?php

namespace App\Services\Keyword;

use App\Models\ChunkKeyword;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * KeywordExtractor implementation.
 *
 * Extracts and weights keywords from text chunks using TF-IDF algorithm.
 * Supports position weighting, frequency-based weighting, and domain-specific vocabulary.
 */
class KeywordExtractor implements IKeywordExtractor
{
    /**
     * Default minimum keyword length.
     */
    private const MIN_KEYWORD_LENGTH = 3;

    /**
     * Default maximum keywords per chunk.
     */
    private const DEFAULT_KEYWORD_COUNT = 10;

    /**
     * Default position decay factor.
     * Keywords earlier in text get higher weight.
     */
    private const POSITION_DECAY = 0.1;

    /**
     * Default frequency boost threshold.
     * Words appearing more than this many times get boosted.
     */
    private const FREQUENCY_BOOST_THRESHOLD = 2;

    /**
     * Domain-specific vocabulary boost factor.
     */
    private float $vocabularyBoost = 1.5;

    /**
     * Domain-specific vocabulary terms.
     */
    private array $domainVocabulary = [];

    /**
     * Document frequency cache for TF-IDF calculation.
     */
    private ?array $documentFrequencies = null;

    /**
     * Total number of documents processed.
     */
    private int $totalDocuments = 0;

    /**
     * {@inheritdoc}
     */
    public function extractWeighted(array $chunks): array
    {
        if (empty($chunks)) {
            return [];
        }

        // Build document frequency map from all chunks
        $this->buildDocumentFrequencies($chunks);

        $results = [];

        foreach ($chunks as $index => $chunk) {
            if (!isset($chunk['content']) || trim($chunk['content']) === '') {
                $results[] = [
                    'chunk_id' => $chunk['id'] ?? null,
                    'keywords' => [],
                ];
                continue;
            }

            $position = $chunk['position'] ?? $index;
            $content = $chunk['content'];

            // Extract and weight keywords
            $keywords = $this->extractFromText($content, self::DEFAULT_KEYWORD_COUNT);

            // Apply position weighting
            $keywords = $this->applyPositionWeighting($keywords, $position, count($chunks));

            // Normalize weights to 0-1 range
            $keywords = $this->normalizeWeights($keywords);

            $results[] = [
                'chunk_id' => $chunk['id'] ?? null,
                'keywords' => $keywords,
            ];
        }

        // Clear cache
        $this->clearCache();

        return $results;
    }

    /**
     * {@inheritdoc}
     */
    public function weightKeywords(array $keywords, ?array $context = null): array
    {
        if (empty($keywords)) {
            return [];
        }

        $weighted = [];

        foreach ($keywords as $index => $keyword) {
            $keyword = strtolower(trim($keyword));

            if (strlen($keyword) < self::MIN_KEYWORD_LENGTH) {
                continue;
            }

            // Base weight from TF-IDF
            $weight = $this->calculateTfIdfWeight($keyword);

            // Apply vocabulary boost
            if ($this->isDomainTerm($keyword)) {
                $weight *= $this->vocabularyBoost;
            }

            // Apply position weight if context provided
            if ($context !== null && isset($context['position'])) {
                $positionDecay = self::POSITION_DECAY * $context['position'];
                $weight *= (1 - min($positionDecay, 0.5));
            }

            $weighted[] = [
                'keyword' => $keyword,
                'weight' => round($weight, 4),
            ];
        }

        // Sort by weight descending
        usort($weighted, fn ($a, $b) => $b['weight'] <=> $a['weight']);

        return $weighted;
    }

    /**
     * {@inheritdoc}
     */
    public function extractFromText(string $text, int $count = 10): array
    {
        if (trim($text) === '') {
            return [];
        }

        if ($count < 1) {
            throw new InvalidArgumentException('Keyword count must be at least 1');
        }

        // Tokenize and clean text
        $tokens = $this->tokenize($text);

        if (empty($tokens)) {
            return [];
        }

        // Remove stop words
        $filteredTokens = $this->filterStopWords($tokens);

        if (empty($filteredTokens)) {
            return [];
        }

        // Calculate term frequencies
        $termFrequencies = $this->calculateTermFrequencies($filteredTokens);

        // Calculate TF-IDF weights
        $tfidfScores = $this->calculateTfIdfScores($termFrequencies);

        // Sort by TF-IDF score
        arsort($tfidfScores);

        // Take top N keywords
        $topKeywords = array_slice($tfidfScores, 0, $count, true);

        // Convert to result format
        $result = [];
        foreach ($topKeywords as $keyword => $score) {
            $result[] = [
                'keyword' => $keyword,
                'weight' => $score,
            ];
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function setDomainVocabulary(array $vocabulary, float $boostFactor = 1.5): self
    {
        $this->domainVocabulary = array_map('strtolower', array_filter($vocabulary, fn ($term) => is_string($term) && strlen($term) >= self::MIN_KEYWORD_LENGTH));
        $this->vocabularyBoost = max(1.0, $boostFactor);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function storeWeightedKeywords(array $weightedKeywords): bool
    {
        if (empty($weightedKeywords)) {
            return true;
        }

        try {
            DB::beginTransaction();

            foreach ($weightedKeywords as $item) {
                if (!isset($item['chunk_id']) || empty($item['keywords'])) {
                    continue;
                }

                // Delete existing keywords for this chunk
                ChunkKeyword::where('chunk_id', $item['chunk_id'])->delete();

                // Insert new keywords
                $records = array_map(fn ($kw) => [
                    'chunk_id' => $item['chunk_id'],
                    'keyword' => $kw['keyword'],
                    'weight' => $kw['weight'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ], $item['keywords']);

                ChunkKeyword::insert($records);
            }

            DB::commit();

            Log::info('Stored weighted keywords', [
                'chunks_processed' => count($weightedKeywords),
            ]);

            return true;
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to store weighted keywords', [
                'message' => $e->getMessage(),
            ]);

            return false;
        }
    }

    // ==========================================
    // Private Helper Methods
    // ==========================================

    /**
     * Build document frequency map from all chunks.
     */
    private function buildDocumentFrequencies(array $chunks): void
    {
        $this->documentFrequencies = [];
        $this->totalDocuments = count($chunks);

        foreach ($chunks as $chunk) {
            if (!isset($chunk['content']) || trim($chunk['content']) === '') {
                continue;
            }

            $tokens = $this->tokenize($chunk['content']);
            $uniqueTokens = array_unique(array_filter($tokens, fn ($t) => strlen($t) >= self::MIN_KEYWORD_LENGTH));

            foreach ($uniqueTokens as $token) {
                $token = strtolower($token);
                $this->documentFrequencies[$token] = ($this->documentFrequencies[$token] ?? 0) + 1;
            }
        }
    }

    /**
     * Calculate TF-IDF weight for a term.
     */
    private function calculateTfIdfWeight(string $term): float
    {
        if ($this->documentFrequencies === null) {
            return 1.0;
        }

        $term = strtolower($term);

        // Term frequency (how often it appears in current context)
        // This is simplified - in a full implementation, we'd pass context
        $tf = 1.0;

        // Inverse document frequency
        $df = $this->documentFrequencies[$term] ?? 0;

        if ($df === 0) {
            // Term not seen in training data - give it a small weight
            return 0.1;
        }

        $idf = log(($this->totalDocuments + 1) / ($df + 1)) + 1;

        return $tf * $idf;
    }

    /**
     * Calculate term frequencies from tokens.
     */
    private function calculateTermFrequencies(array $tokens): array
    {
        $counts = array_count_values($tokens);
        $total = count($tokens);

        // Normalize by total tokens (sublinear TF)
        $tfScores = [];
        foreach ($counts as $term => $count) {
            $tfScores[$term] = 1.0 + log($count);
        }

        return $tfScores;
    }

    /**
     * Calculate TF-IDF scores for all terms.
     */
    private function calculateTfIdfScores(array $termFrequencies): array
    {
        $scores = [];

        foreach ($termFrequencies as $term => $tf) {
            $df = $this->documentFrequencies[$term] ?? 0;
            $idf = log(($this->totalDocuments + 1) / ($df + 1)) + 1;
            $scores[$term] = $tf * $idf;
        }

        return $scores;
    }

    /**
     * Apply position-based weighting to keywords.
     */
    private function applyPositionWeighting(array $keywords, int $position, int $totalChunks): array
    {
        if ($position === 0) {
            return $keywords; // First chunk gets full weight
        }

        $positionFactor = 1.0 - (self::POSITION_DECAY * min($position, 5));

        return array_map(fn ($kw) => [
            'keyword' => $kw['keyword'],
            'weight' => $kw['weight'] * $positionFactor,
        ], $keywords);
    }

    /**
     * Normalize weights to 0-1 range.
     */
    private function normalizeWeights(array $keywords): array
    {
        if (empty($keywords)) {
            return $keywords;
        }

        // Find max weight
        $maxWeight = max(array_column($keywords, 'weight'));

        if ($maxWeight <= 0) {
            return $keywords;
        }

        // Normalize
        return array_map(fn ($kw) => [
            'keyword' => $kw['keyword'],
            'weight' => round($kw['weight'] / $maxWeight, 4),
        ], $keywords);
    }

    /**
     * Tokenize text into words.
     */
    private function tokenize(string $text): array
    {
        // Convert to lowercase
        $text = strtolower($text);

        // Remove punctuation but keep alphanumeric
        $text = preg_replace('/[^\p{L}\p{N}\s]/u', '', $text);

        // Split into words
        $words = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);

        return $words ?? [];
    }

    /**
     * Filter out stop words from tokens.
     */
    private function filterStopWords(array $tokens): array
    {
        $stopWords = $this->getStopWords();

        return array_filter($tokens, fn ($token) => {
            $lower = strtolower($token);
            return strlen($lower) >= self::MIN_KEYWORD_LENGTH
                && !isset($stopWords[$lower]);
        });
    }

    /**
     * Check if a term is in the domain vocabulary.
     */
    private function isDomainTerm(string $term): bool
    {
        return in_array(strtolower($term), $this->domainVocabulary, true);
    }

    /**
     * Clear the document frequency cache.
     */
    private function clearCache(): void
    {
        $this->documentFrequencies = null;
        $this->totalDocuments = 0;
    }

    /**
     * Get common English stop words.
     */
    private function getStopWords(): array
    {
        return [
            'a' => true, 'an' => true, 'the' => true, 'and' => true, 'or' => true,
            'but' => true, 'if' => true, 'then' => true, 'else' => true, 'when' => true,
            'at' => true, 'by' => true, 'for' => true, 'with' => true, 'about' => true,
            'against' => true, 'between' => true, 'into' => true, 'through' => true,
            'during' => true, 'before' => true, 'after' => true, 'above' => true,
            'below' => true, 'to' => true, 'from' => true, 'up' => true, 'down' => true,
            'in' => true, 'out' => true, 'on' => true, 'off' => true, 'over' => true,
            'under' => true, 'again' => true, 'further' => true, 'then' => true,
            'once' => true, 'here' => true, 'there' => true, 'where' => true,
            'why' => true, 'how' => true, 'all' => true, 'each' => true, 'few' => true,
            'more' => true, 'most' => true, 'other' => true, 'some' => true, 'such' => true,
            'no' => true, 'nor' => true, 'not' => true, 'only' => true, 'own' => true,
            'same' => true, 'so' => true, 'than' => true, 'too' => true, 'very' => true,
            'can' => true, 'will' => true, 'just' => true, 'should' => true, 'could' => true,
            'would' => true, 'might' => true, 'must' => true, 'shall' => true,
            'have' => true, 'has' => true, 'had' => true, 'having' => true,
            'do' => true, 'does' => true, 'did' => true, 'doing' => true,
            'be' => true, 'is' => true, 'are' => true, 'was' => true, 'were' => true,
            'been' => true, 'being' => true,
            'i' => true, 'me' => true, 'my' => true, 'myself' => true, 'we' => true,
            'our' => true, 'ours' => true, 'ourselves' => true, 'you' => true,
            'your' => true, 'yours' => true, 'yourself' => true, 'yourselves' => true,
            'he' => true, 'him' => true, 'his' => true, 'himself' => true,
            'she' => true, 'her' => true, 'hers' => true, 'herself' => true,
            'it' => true, 'its' => true, 'itself' => true, 'they' => true,
            'them' => true, 'their' => true, 'theirs' => true, 'themselves' => true,
            'what' => true, 'which' => true, 'who' => true, 'whom' => true,
            'this' => true, 'that' => true, 'these' => true, 'those' => true,
            'am' => true, 'as' => true, 'of' => true, 'until' => true, 'while' => true,
            'because' => true, 'although' => true, 'since' => true, 'unless' => true,
            'also' => true, 'both' => true, 'neither' => true, 'either' => true,
            'any' => true, 'every' => true, 'much' => true, 'many' => true,
            'even' => true, 'still' => true, 'yet' => true, 'rather' => true,
        ];
    }
}
