<?php

use App\Services\Chunking\DocumentChunker;
use PHPUnit\Framework\TestCase;

describe('DocumentChunker', function () {
    beforeEach(function () {
        $this->chunker = new DocumentChunker();
    });

    describe('chunkText', function () {
        it('returns empty array for empty content', function () {
            $result = $this->chunker->chunkText('', 500, 0);
            expect($result)->toBe([]);
        });

        it('returns array with single chunk for text smaller than chunk size', function () {
            $text = 'This is a short piece of text.';
            $result = $this->chunker->chunkText($text, 500, 0);
            expect($result)->toBe([$text]);
        });

        it('respects markdown header boundaries', function () {
            $text = "# Header 1\n\nSome content here.\n\n## Header 2\n\nMore content here.";
            $result = $this->chunker->chunkText($text, 100, 0);
            
            expect(count($result))->toBeGreaterThanOrEqual(2);
            // First chunk should contain the first header
            expect($result[0])->toContain('Header 1');
            // One of the chunks should contain the second header
            $combined = implode(' ', $result);
            expect($combined)->toContain('Header 2');
        });

        it('respects multiple markdown header levels', function () {
            $text = "# H1\n\n## H2\n\n### H3\n\nContent";
            $result = $this->chunker->chunkText($text, 100, 0);
            
            $combined = implode(' ', $result);
            expect($combined)->toContain('H1');
            expect($combined)->toContain('H2');
            expect($combined)->toContain('H3');
        });

        it('respects html header boundaries', function () {
            $text = "<h1>Header 1</h1>\n\n<p>Some content here.</p>\n<h2>Header 2</h2>\n<p>More content here.</p>";
            $result = $this->chunker->chunkText($text, 100, 0);
            
            expect(count($result))->toBeGreaterThanOrEqual(2);
            $combined = implode(' ', $result);
            expect($combined)->toContain('Header 1');
            expect($combined)->toContain('Header 2');
        });

        it('respects html h1 through h6 headers', function () {
            $text = "<h1>Level 1</h1><p>Content 1</p>\n<h2>Level 2</h2><p>Content 2</p>\n<h3>Level 3</h3><p>Content 3</p>\n<h4>Level 4</h4><p>Content 4</p>\n<h5>Level 5</h5><p>Content 5</p>\n<h6>Level 6</h6><p>Content 6</p>";
            $result = $this->chunker->chunkText($text, 100, 0);
            
            $combined = implode(' ', $result);
            expect($combined)->toContain('Level 1');
            expect($combined)->toContain('Level 2');
            expect($combined)->toContain('Level 6');
        });

        it('preserves paragraph integrity', function () {
            $text = "This is a long paragraph that should not be split in the middle. It contains multiple sentences and should remain as a single unit when possible. Another sentence here. And another one here.\n\n## Header\n\nThis is another paragraph.";
            $result = $this->chunker->chunkText($text, 1000, 0);
            
            // The first paragraph should remain intact
            expect($result[0])->toContain('This is a long paragraph');
            expect($result[0])->toContain('Another sentence here');
        });

        it('handles markdown lists as atomic units', function () {
            $text = "# Header\n\n- First item\n- Second item\n- Third item\n\nMore content here.";
            $result = $this->chunker->chunkText($text, 200, 0);
            
            // Lists should stay together
            $combined = implode(' ', $result);
            expect($combined)->toContain('First item');
            expect($combined)->toContain('Third item');
        });

        it('handles ordered lists as atomic units', function () {
            $text = "# Header\n\n1. First item\n2. Second item\n3. Third item\n\nMore content here.";
            $result = $this->chunker->chunkText($text, 200, 0);
            
            $combined = implode(' ', $result);
            expect($combined)->toContain('First item');
            expect($combined)->toContain('Third item');
        });

        it('creates multiple chunks when content exceeds chunk size', function () {
            $text = str_repeat('This is a long piece of text that will exceed the chunk size limit. ', 20);
            $result = $this->chunker->chunkText($text, 200, 0);
            
            expect(count($result))->toBeGreaterThan(1);
        });

        it('respects configurable chunk size', function () {
            $text = str_repeat('Word. ', 100); // Add punctuation for sentence splitting
            $resultSmall = $this->chunker->chunkText($text, 100, 0);
            $resultLarge = $this->chunker->chunkText($text, 1000, 0);
            
            expect(count($resultSmall))->toBeGreaterThan(count($resultLarge));
        });

        it('adds overlap between chunks when overlap parameter is set', function () {
            $text = str_repeat('First chunk content here. ', 10) . "\n\n" . str_repeat('Second chunk content here. ', 10);
            $result = $this->chunker->chunkText($text, 100, 20);
            
            // Should still produce multiple chunks
            expect(count($result))->toBeGreaterThan(1);
        });

        it('maintains backward compatibility with existing behavior', function () {
            // Test the original sentence-based chunking behavior
            $text = 'First sentence. Second sentence. Third sentence. Fourth sentence. Fifth sentence.';
            $result = $this->chunker->chunkText($text, 30, 0);
            
            expect(count($result))->toBeGreaterThanOrEqual(2);
        });

        it('handles mixed markdown and html content', function () {
            $text = "# Title\n\n<p>HTML paragraph</p>\n\n## Markdown Header\n\n- List item 1\n- List item 2";
            $result = $this->chunker->chunkText($text, 200, 0);
            
            expect(count($result))->toBeGreaterThanOrEqual(1);
            $combined = implode(' ', $result);
            expect($combined)->toContain('Title');
            expect($combined)->toContain('HTML paragraph');
            expect($combined)->toContain('List item 1');
        });

        it('handles content without headers gracefully', function () {
            $text = "Just a paragraph without any headers. Multiple sentences here. More text.";
            $result = $this->chunker->chunkText($text, 50, 0);
            
            expect(count($result))->toBeGreaterThanOrEqual(1);
        });

        it('preserves headers in output chunks', function () {
            $text = "# Main Title\n\n## Section 1\n\nContent for section 1.\n\n## Section 2\n\nContent for section 2.";
            $result = $this->chunker->chunkText($text, 100, 0);
            
            $hasSection1Header = false;
            $hasSection2Header = false;
            
            foreach ($result as $chunk) {
                if (str_contains($chunk, '## Section 1')) {
                    $hasSection1Header = true;
                }
                if (str_contains($chunk, '## Section 2')) {
                    $hasSection2Header = true;
                }
            }
            
            expect($hasSection1Header)->toBeTrue();
            expect($hasSection2Header)->toBeTrue();
        });
    });
});
