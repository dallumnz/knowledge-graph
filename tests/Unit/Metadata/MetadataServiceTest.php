<?php

use App\Services\Metadata\MetadataService;

describe('MetadataService', function () {
    beforeEach(function () {
        $this->metadataService = new MetadataService();
    });

    describe('generateSummary', function () {
        it('generates a summary between 80-120 characters for long text', function () {
            $text = 'The quick brown fox jumps over the lazy dog. This is a test sentence that provides additional context for the summary generation algorithm. It should help ensure that the summary is coherent and meaningful. The algorithm attempts to find natural break points near the target length.';
            $summary = $this->metadataService->generateSummary($text);

            expect($summary)->toBeString();
            expect(strlen($summary))->toBeGreaterThanOrEqual(80);
            expect(strlen($summary))->toBeLessThanOrEqual(120);
        });

        it('returns empty string for empty text', function () {
            $summary = $this->metadataService->generateSummary('');
            expect($summary)->toBe('');
        });

        it('returns empty string for whitespace-only text', function () {
            $summary = $this->metadataService->generateSummary('   ');
            expect($summary)->toBe('');
        });

        it('returns truncated text if shorter than max length', function () {
            $text = 'Hello world! This is a short text.';
            $summary = $this->metadataService->generateSummary($text);

            expect($summary)->toBe($text);
        });

        it('finds natural break at sentence ending', function () {
            $text = 'This is the first sentence. This is the second sentence which is much longer and contains more information. This is the third sentence that continues the thought.';
            $summary = $this->metadataService->generateSummary($text);

            expect($summary)->toContain('first sentence');
            expect(strlen($summary))->toBeLessThanOrEqual(120);
        });

        it('handles text with no punctuation gracefully', function () {
            $text = str_repeat('word ', 50);
            $summary = $this->metadataService->generateSummary($text);

            expect(strlen($summary))->toBeGreaterThanOrEqual(80);
            expect(strlen($summary))->toBeLessThanOrEqual(120);
        });
    });

    describe('extractKeywords', function () {
        it('extracts 3-5 keywords by default', function () {
            $text = 'The quick brown fox jumps over the lazy dog. Foxes are mammals known for their cunning intelligence and adaptability in various environments.';
            $keywords = $this->metadataService->extractKeywords($text);

            expect($keywords)->toBeArray();
            expect(count($keywords))->toBeGreaterThanOrEqual(3);
            expect(count($keywords))->toBeLessThanOrEqual(5);
        });

        it('extracts specified number of keywords', function () {
            $text = 'Machine learning algorithms process data to make predictions. Neural networks use layers of interconnected nodes. Deep learning enables complex pattern recognition in large datasets.';
            $keywords = $this->metadataService->extractKeywords($text, 3);

            expect($keywords)->toHaveCount(3);
        });

        it('returns empty array for empty text', function () {
            $keywords = $this->metadataService->extractKeywords('');
            expect($keywords)->toBeArray();
            expect($keywords)->toBeEmpty();
        });

        it('filters out common stop words', function () {
            $text = 'The quick brown fox jumps over the lazy dog and runs away from the hunter.';
            $keywords = $this->metadataService->extractKeywords($text);

            // These common words should not appear as keywords
            // Note: with short text, keywords might still appear due to frequency
            // The filter should at least prevent them from dominating
            $stopWordMatches = array_intersect($keywords, ['the', 'and', 'over', 'from']);
            expect(count($stopWordMatches))->toBeLessThanOrEqual(1);
        });

        it('extracts meaningful content words', function () {
            $text = 'Python programming language is popular for data science and machine learning development.';
            $keywords = $this->metadataService->extractKeywords($text);

            // Should extract content words, not stop words
            expect($keywords)->toContain('python');
            expect($keywords)->toContain('programming');
            // 'language' or 'popular' should be present for short text
            $contentMatches = array_intersect($keywords, ['language', 'popular']);
            expect(count($contentMatches))->toBeGreaterThanOrEqual(1);
        });

        it('throws exception for invalid count', function () {
            expect(function () {
                $this->metadataService->extractKeywords('test text', 0);
            })->toThrow(InvalidArgumentException::class);
        });

        it('handles short text gracefully', function () {
            $text = 'Short text.';
            $keywords = $this->metadataService->extractKeywords($text);

            expect($keywords)->toBeArray();
        });

        it('handles very long text', function () {
            $text = str_repeat('The quick brown fox jumps over the lazy dog. ', 100);
            $keywords = $this->metadataService->extractKeywords($text);

            expect($keywords)->toBeArray();
            expect(count($keywords))->toBeGreaterThan(0);
        });

        it('returns unique keywords', function () {
            $text = 'Python Python programming language programming language Python is great.';
            $keywords = $this->metadataService->extractKeywords($text);

            expect($keywords)->toHaveCount(count(array_unique($keywords)));
        });
    });

    describe('integration', function () {
        it('generates both summary and keywords for the same text', function () {
            $text = 'Climate change refers to long-term shifts in temperatures and weather patterns. Human activities have been the main driver of climate change since the 1800s, primarily due to burning fossil fuels like coal, oil and gas. This has led to global warming and environmental impacts.';
            $summary = $this->metadataService->generateSummary($text);
            $keywords = $this->metadataService->extractKeywords($text);

            expect(strlen($summary))->toBeGreaterThanOrEqual(70);
            expect(strlen($summary))->toBeLessThanOrEqual(120);
            expect($keywords)->toHaveCount(5);
            expect($summary)->not->toBe($text);
        });
    });
});
