<?php

namespace App\Services\Metadata;

use InvalidArgumentException;

/**
 * MetadataService implementation.
 *
 * Generates summaries and extracts keywords from text using
 * simple extraction-based approaches (no external AI).
 */
class MetadataService implements IMetadataService
{
    /**
     * Minimum summary length in characters.
     */
    private const MIN_SUMMARY_LENGTH = 80;

    /**
     * Maximum summary length in characters.
     */
    private const MAX_SUMMARY_LENGTH = 120;

    /**
     * Minimum keyword length (characters) to be considered.
     */
    private const MIN_KEYWORD_LENGTH = 3;

    /**
     * {@inheritdoc}
     */
    public function generateSummary(string $text): string
    {
        if (trim($text) === '') {
            return '';
        }

        // Clean the text
        $cleanText = $this->cleanText($text);

        // If text is shorter than minimum summary, return truncated text
        if (strlen($cleanText) <= self::MAX_SUMMARY_LENGTH) {
            return $cleanText;
        }

        // Try to find a natural break point near target length
        $targetLength = (self::MIN_SUMMARY_LENGTH + self::MAX_SUMMARY_LENGTH) / 2;

        // Look for sentence endings near the target length
        $periods = strpos($cleanText, '.', (int) ($targetLength * 0.7));
        $exclamations = strpos($cleanText, '!', (int) ($targetLength * 0.7));
        $questions = strpos($cleanText, '?', (int) ($targetLength * 0.7));

        // Find the earliest punctuation mark after the minimum length
        $candidates = [];
        if ($periods !== false && $periods <= self::MAX_SUMMARY_LENGTH) {
            $candidates[] = $periods;
        }
        if ($exclamations !== false && $exclamations <= self::MAX_SUMMARY_LENGTH) {
            $candidates[] = $exclamations;
        }
        if ($questions !== false && $questions <= self::MAX_SUMMARY_LENGTH) {
            $candidates[] = $questions;
        }

        if (!empty($candidates)) {
            $breakPoint = min($candidates) + 1;
            return trim(substr($cleanText, 0, $breakPoint));
        }

        // If no natural break, truncate at word boundary
        $truncated = substr($cleanText, 0, self::MAX_SUMMARY_LENGTH);
        $lastSpace = strrpos($truncated, ' ');

        if ($lastSpace !== false && $lastSpace > self::MIN_SUMMARY_LENGTH) {
            $truncated = substr($truncated, 0, $lastSpace);
        }

        return trim($truncated);
    }

    /**
     * {@inheritdoc}
     */
    public function extractKeywords(string $text, int $count = 5): array
    {
        if (trim($text) === '') {
            return [];
        }

        if ($count < 1) {
            throw new InvalidArgumentException('Keyword count must be at least 1');
        }

        // Clean and tokenize the text
        $words = $this->tokenize($text);

        if (empty($words)) {
            return [];
        }

        // Filter stop words
        $stopWords = $this->getStopWords();
        $filteredWords = array_filter($words, function ($word) use ($stopWords) {
            $lowerWord = strtolower($word);
            return strlen($lowerWord) >= self::MIN_KEYWORD_LENGTH
                && !in_array($lowerWord, $stopWords, true);
        });

        // Count word frequencies
        $wordCounts = array_count_values($filteredWords);

        // Sort by frequency (descending) and then alphabetically for consistency
        uasort($wordCounts, function ($a, $b) {
            if ($a === $b) {
                return strcmp((string) $a, (string) $b);
            }
            return $b <=> $a;
        });

        // Extract top N words
        $keywords = array_slice(array_keys($wordCounts), 0, $count, true);

        return array_values($keywords);
    }

    /**
     * Clean text by removing extra whitespace and special characters.
     */
    private function cleanText(string $text): string
    {
        // Replace multiple whitespace with single space
        $text = preg_replace('/\s+/', ' ', $text);

        // Remove leading/trailing whitespace
        $text = trim($text);

        return $text;
    }

    /**
     * Tokenize text into words.
     *
     * @return array<int, string>
     */
    private function tokenize(string $text): array
    {
        // Convert to lowercase
        $text = strtolower($text);

        // Remove punctuation but keep spaces
        $text = preg_replace('/[^\p{L}\p{N}\s]/u', '', $text);

        // Split into words
        $words = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);

        return $words ?? [];
    }

    /**
     * Get common English stop words.
     *
     * @return array<string, bool>
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
            'one' => true, 'two' => true, 'first' => true, 'second' => true,
            'new' => true, 'old' => true, 'good' => true, 'bad' => true, 'great' => true,
        ];
    }
}
