<?php

namespace App\Services\Keyword;

/**
 * KeywordExtractor implementation.
 *
 * Extracts and weights keywords from text using TF-IDF and positional analysis.
 * Supports keyword weighting by position, frequency, and document frequency.
 */
class KeywordExtractor implements IKeywordExtractor
{
    /**
     * Minimum keyword length (characters).
     */
    private const MIN_KEYWORD_LENGTH = 3;

    /**
     * Default number of keywords to extract.
     */
    private const DEFAULT_KEYWORD_COUNT = 5;

    /**
     * Position weight decay factor (earlier = higher weight).
     */
    private const POSITION_DECAY = 0.1;

    /**
     * Common English stop words.
     */
    private const STOP_WORDS = [
        'a', 'an', 'the', 'and', 'or', 'but', 'if', 'then', 'else', 'when',
        'at', 'by', 'for', 'with', 'about', 'against', 'between', 'into', 'through',
        'during', 'before', 'after', 'above', 'below', 'to', 'from', 'up', 'down',
        'in', 'out', 'on', 'off', 'over', 'under', 'again', 'further', 'then', 'once',
        'here', 'there', 'where', 'why', 'how', 'all', 'each', 'few', 'more', 'most',
        'other', 'some', 'such', 'no', 'nor', 'not', 'only', 'own', 'same', 'so',
        'than', 'too', 'very', 'can', 'will', 'just', 'should', 'could', 'would',
        'might', 'must', 'shall', 'have', 'has', 'had', 'having', 'do', 'does', 'did',
        'doing', 'be', 'is', 'are', 'was', 'were', 'been', 'being', 'i', 'me', 'my',
        'myself', 'we', 'our', 'ours', 'ourselves', 'you', 'your', 'yours',
        'yourself', 'yourselves', 'he', 'him', 'his', 'himself', 'she', 'her',
        'hers', 'herself', 'it', 'its', 'itself', 'they', 'them', 'their', 'theirs',
        'themselves', 'what', 'which', 'who', 'whom', 'this', 'that', 'these',
        'those', 'am', 'as', 'of', 'until', 'while', 'because', 'although',
        'since', 'unless', 'also', 'both', 'neither', 'either', 'one', 'two',
        'first', 'second', 'new', 'old', 'good', 'bad', 'great',
    ];

    /**
     * {@inheritdoc}
     */
    public function extractWeighted(string $text, int $count = 5): array
    {
        if (trim($text) === '') {
            return [];
        }

        $words = $this->tokenize($text);
        $wordCounts = $this->countWords($words);
        $totalWords = count($words);

        if ($totalWords === 0) {
            return [];
        }

        // Calculate position weights (earlier words get higher weight)
        $positionWeights = $this->calculatePositionWeights($totalWords);

        // Calculate TF-IDF-like weights
        $keywordWeights = [];
        foreach ($wordCounts as $word => $frequency) {
            if (strlen($word) < self::MIN_KEYWORD_LENGTH) {
                continue;
            }

            $tf = $frequency / $totalWords;
            $positionWeight = $positionWeights[$word] ?? 1.0;

            // Combined weight: TF * position * frequency boost
            $weight = $tf * $positionWeight * (1 + log(1 + $frequency));

            $keywordWeights[$word] = $weight;
        }

        // Sort by weight descending
        arsort($keywordWeights);

        // Return top N keywords with weights
        $result = [];
        $keywords = array_slice(array_keys($keywordWeights), 0, $count, true);
        foreach ($keywords as $keyword) {
            $result[] = [
                'keyword' => $keyword,
                'weight' => round($keywordWeights[$keyword], 4),
            ];
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function extractFromChunks(array $chunks, int $count = 5): array
    {
        $results = [];

        foreach ($chunks as $index => $chunk) {
            $keywords = $this->extractWeighted($chunk, $count);
            $results[] = [
                'chunk_index' => $index,
                'keywords' => $keywords,
            ];
        }

        return $results;
    }

    /**
     * {@inheritdoc}
     */
    public function computeTfIdf(array $documents, array $keywords): array
    {
        $docCount = count($documents);

        // Count document frequency for each keyword
        $docFrequency = [];
        foreach ($keywords as $keyword) {
            $docFrequency[$keyword] = 0;
        }

        foreach ($documents as $document) {
            $words = $this->tokenize($document);
            $uniqueWords = array_unique(array_map('strtolower', $words));

            foreach ($keywords as $keyword) {
                $lowerKeyword = strtolower($keyword);
                if (in_array($lowerKeyword, $uniqueWords, true)) {
                    $docFrequency[$keyword]++;
                }
            }
        }

        // Calculate IDF for each keyword
        $idfWeights = [];
        foreach ($keywords as $keyword) {
            $df = $docFrequency[$keyword];
            // IDF formula: log(N / (1 + df))
            // Handle edge case: if N=0, IDF is 0
            if ($docCount === 0) {
                $idf = 0.0;
            } else {
                $idf = log($docCount / (1 + $df));
            }
            $idfWeights[$keyword] = round($idf, 4);
        }

        return $idfWeights;
    }

    /**
     * Tokenize text into words.
     */
    private function tokenize(string $text): array
    {
        $text = strtolower($text ?? '');
        $text = preg_replace('/[^\p{L}\p{N}\s]/u', '', $text);
        $words = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);

        return array_filter($words, fn($word) => !in_array($word, self::STOP_WORDS, true));
    }

    /**
     * Count word frequencies.
     */
    private function countWords(array $words): array
    {
        $counts = [];
        foreach ($words as $word) {
            $lowerWord = strtolower($word);
            $counts[$lowerWord] = ($counts[$lowerWord] ?? 0) + 1;
        }
        return $counts;
    }

    /**
     * Calculate position-based weights for words.
     *
     * Words appearing earlier in the document get higher weights.
     *
     * @return array<string, float>
     */
    private function calculatePositionWeights(int $totalWords): array
    {
        $weights = [];

        for ($i = 1; $i <= $totalWords; $i++) {
            // Earlier words (lower index) get higher weight
            // Position weight decays as we go further into the document
            $normalizedPosition = $i / $totalWords;
            $weight = 1.0 - (self::POSITION_DECAY * $normalizedPosition);
            $weights[$normalizedPosition] = max(0.5, $weight); // Minimum 50% weight
        }

        return $weights;
    }
}
