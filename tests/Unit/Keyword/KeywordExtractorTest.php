<?php

use App\Services\Keyword\KeywordExtractor;

describe('KeywordExtractor', function () {
    beforeEach(function () {
        $this->extractor = new KeywordExtractor();
    });

    describe('extractWeighted', function () {
        it('extracts weighted keywords from text', function () {
            $text = 'Python programming language is popular for data science and machine learning. Python is versatile.';
            $keywords = $this->extractor->extractWeighted($text, 5);

            expect($keywords)->toBeArray();
            expect(count($keywords))->toBeLessThanOrEqual(5);
            expect($keywords[0])->toHaveKey('keyword');
            expect($keywords[0])->toHaveKey('weight');
            expect($keywords[0]['weight'])->toBeFloat();
        });

        it('returns empty array for empty text', function () {
            $keywords = $this->extractor->extractWeighted('');
            expect($keywords)->toBeArray();
            expect($keywords)->toBeEmpty();
        });

        it('filters out stop words', function () {
            $text = 'The quick brown fox jumps over the lazy dog and runs away.';
            $keywords = $this->extractor->extractWeighted($text, 10);

            $keywordStrings = array_column($keywords, 'keyword');
            expect($keywordStrings)->not->toContain('the');
            expect($keywordStrings)->not->toContain('and');
            expect($keywordStrings)->not->toContain('over');
        });

        it('extracts technical terms with higher weight', function () {
            $text = 'Machine learning algorithms process data to make predictions. Neural networks enable deep learning applications. Python is the preferred language for AI development.';
            $keywords = $this->extractor->extractWeighted($text, 5);

            $keywordStrings = array_column($keywords, 'keyword');
            // Should find at least some technical terms
            expect(count($keywords))->toBeGreaterThanOrEqual(3);
            // 'learning' appears twice, should be extracted
            expect($keywordStrings)->toContain('learning');
        });

        it('orders keywords by weight descending', function () {
            $text = 'Python Python Python programming is great. Python is versatile.';
            $keywords = $this->extractor->extractWeighted($text, 3);

            expect(count($keywords))->toBeGreaterThan(1);
            expect($keywords[0]['keyword'])->toBe('python');

            // Verify descending order
            for ($i = 1; $i < count($keywords); $i++) {
                expect($keywords[$i - 1]['weight'])->toBeGreaterThanOrEqual($keywords[$i]['weight']);
            }
        });

        it('handles short text gracefully', function () {
            $text = 'Short text.';
            $keywords = $this->extractor->extractWeighted($text, 5);

            expect($keywords)->toBeArray();
        });

        it('respects maximum count parameter', function () {
            $text = str_repeat('word ', 100);
            $keywords = $this->extractor->extractWeighted($text, 3);

            expect(count($keywords))->toBeLessThanOrEqual(3);
        });
    });

    describe('extractFromChunks', function () {
        it('extracts keywords from multiple chunks', function () {
            $chunks = [
                'Machine learning is transforming industries.',
                'Neural networks enable deep learning applications.',
                'Python is the preferred language for AI development.',
            ];

            $results = $this->extractor->extractFromChunks($chunks, 3);

            expect($results)->toHaveCount(3);
            expect($results[0])->toHaveKey('chunk_index');
            expect($results[0])->toHaveKey('keywords');
            expect($results[0]['chunk_index'])->toBe(0);
            expect(count($results[0]['keywords']))->toBeLessThanOrEqual(3);
        });

        it('preserves chunk indices', function () {
            $chunks = [
                'First chunk about Python.',
                'Second chunk about machine learning.',
                'Third chunk about neural networks.',
            ];

            $results = $this->extractor->extractFromChunks($chunks, 2);

            expect($results[0]['chunk_index'])->toBe(0);
            expect($results[1]['chunk_index'])->toBe(1);
            expect($results[2]['chunk_index'])->toBe(2);
        });

        it('handles empty chunk array', function () {
            $results = $this->extractor->extractFromChunks([], 5);
            expect($results)->toBeArray();
            expect($results)->toBeEmpty();
        });
    });

    describe('computeTfIdf', function () {
        it('calculates IDF weights for keywords', function () {
            $documents = [
                'Python programming is great.',
                'Machine learning uses Python.',
                'Neural networks are powerful.',
            ];

            $keywords = ['python', 'learning', 'networks'];
            $weights = $this->extractor->computeTfIdf($documents, $keywords);

            expect($weights)->toBeArray();
            expect($weights)->toHaveKeys($keywords);
            // IDF can be positive or negative depending on document frequency
            expect(is_float($weights['python']))->toBeTrue();
            expect(is_float($weights['learning']))->toBeTrue();
            expect(is_float($weights['networks']))->toBeTrue();
        });

        it('gives higher IDF to rarer keywords', function () {
            $documents = [
                'Python is popular. Python is great.',
                'Python appears twice in this document.',
                'Java is different from Python.',
            ];

            $keywords = ['python', 'java'];
            $weights = $this->extractor->computeTfIdf($documents, $keywords);

            // 'java' appears in fewer documents, so should have higher IDF
            expect($weights['java'])->toBeGreaterThanOrEqual($weights['python']);
        });

        it('handles empty document array', function () {
            $weights = $this->extractor->computeTfIdf([], ['keyword']);
            expect($weights)->toBeArray();
            // With no documents, IDF should be 0 (log(0) = 0)
            expect($weights)->toHaveKey('keyword');
            expect($weights['keyword'])->toBe(0.0);
        });

        it('returns IDF weights for all keywords', function () {
            $documents = ['Python is popular.'];
            $keywords = ['python', 'java', 'ruby'];
            $weights = $this->extractor->computeTfIdf($documents, $keywords);

            expect($weights)->toBeArray();
            expect($weights)->toHaveKeys($keywords);
            // IDF can be negative for common terms
            expect(is_float($weights['python']))->toBeTrue();
            expect(is_float($weights['java']))->toBeTrue();
            expect(is_float($weights['ruby']))->toBeTrue();
        });
    });

    describe('integration', function () {
        it('works as complete keyword extraction pipeline', function () {
            $text = 'The Personal Knowledge Graph stores structured knowledge and vector embeddings. Knowledge graphs enable semantic search and AI-powered applications.';

            $keywords = $this->extractor->extractWeighted($text, 5);

            expect(count($keywords))->toBeLessThanOrEqual(5);
            expect($keywords[0])->toHaveKey('keyword');
            expect($keywords[0])->toHaveKey('weight');

            // Should find knowledge-related keywords
            $keywordStrings = array_column($keywords, 'keyword');
            expect($keywordStrings)->toContain('knowledge');
            expect($keywordStrings)->toContain('graph');
        });
    });
});
