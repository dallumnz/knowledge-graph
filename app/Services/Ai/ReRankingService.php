<?php

namespace App\Services\Ai;

use App\Models\Node;
use Illuminate\Support\Facades\Log;

/**
 * Re-ranking Service using LLM for relevance scoring.
 *
 * Takes search results and re-ranks them using an LLM to score
 * relevance to the original query. This provides better ranking
 * than pure vector/keyword similarity by understanding context.
 */
class ReRankingService
{
    /**
     * The LLM provider instance.
     */
    private $llmProvider;

    /**
     * Number of results to consider for re-ranking.
     */
    private int $topN;

    /**
     * Number of final results to return.
     */
    private int $finalResults;

    /**
     * Whether the service is enabled.
     */
    private bool $enabled;

    /**
     * Temperature for LLM scoring (lower = more consistent).
     */
    private float $temperature;

    /**
     * Maximum chunk content length to send to LLM.
     */
    private int $maxChunkLength;

    /**
     * Create a new re-ranking service instance.
     */
    public function __construct()
    {
        $this->enabled = config('ai.search.enable_reranking', true);
        $this->topN = config('ai.search.rerank_top_n', 20);
        $this->finalResults = config('ai.search.final_results', 5);
        $this->temperature = 0.1; // Low temperature for consistent scoring
        $this->maxChunkLength = 2000; // Limit content length for LLM
    }

    /**
     * Re-rank search results using LLM relevance scoring.
     *
     * @param string $query The original search query
     * @param array<int, array{node: Node, score: float}> $results Results from hybrid search
     * @param int|null $topN Number of results to consider (null uses config)
     * @param int|null $finalCount Number of final results (null uses config)
     * @return array<int, array{node: Node, score: float, relevance_score: float, metadata: array}>
     */
    public function rerank(string $query, array $results, ?int $topN = null, ?int $finalCount = null): array
    {
        if (!$this->enabled) {
            Log::debug('Re-ranking disabled via config');
            // Return original results with dummy relevance scores
            return array_map(fn ($r) => [
                'node' => $r['node'],
                'score' => $r['score'],
                'relevance_score' => $r['score'],
                'metadata' => $r['metadata'] ?? [],
            ], $results);
        }

        if (empty($results)) {
            return [];
        }

        $topN ??= $this->topN;
        $finalCount ??= $this->finalResults;

        // Limit to top N candidates for re-ranking
        $candidates = array_slice($results, 0, $topN);

        try {
            $this->llmProvider = AiProviderFactory::makeLlmProvider();

            if (!$this->llmProvider->isAvailable()) {
                Log::warning('LLM provider not available for re-ranking');
                return $this->fallbackRanking($results, $finalCount);
            }

            // Score each candidate
            $scoredResults = [];
            foreach ($candidates as $index => $result) {
                $relevanceScore = $this->scoreRelevance(
                    $query,
                    $result['node']->content,
                    $index + 1
                );

                // Combine original score with LLM relevance (weighted average)
                // Original score provides baseline, LLM provides refinement
                $originalScore = $result['score'];
                $combinedScore = ($originalScore * 0.3) + ($relevanceScore * 0.7);

                $scoredResults[] = [
                    'node' => $result['node'],
                    'score' => round($combinedScore, 4),
                    'relevance_score' => round($relevanceScore, 4),
                    'original_score' => round($originalScore, 4),
                    'metadata' => array_merge(
                        $result['metadata'] ?? [],
                        ['llm_relevance' => $relevanceScore]
                    ),
                ];

                // Small delay to avoid overwhelming the LLM
                if ($index < count($candidates) - 1) {
                    usleep(100000); // 100ms delay
                }
            }

            // Sort by combined score descending
            usort($scoredResults, fn ($a, $b) => $b['score'] <=> $a['score']);

            // Return top finalCount results
            return array_slice($scoredResults, 0, $finalCount);
        } catch (\Exception $e) {
            Log::error('Re-ranking failed', [
                'message' => $e->getMessage(),
            ]);

            return $this->fallbackRanking($results, $finalCount);
        }
    }

    /**
     * Score the relevance of a chunk to a query using LLM.
     *
     * @param string $query The search query
     * @param string $content The chunk content
     * @param int $index Result index for logging
     * @return float Relevance score (0.0-1.0)
     */
    private function scoreRelevance(string $query, string $content, int $index): float
    {
        // Truncate content if too long
        $truncatedContent = $this->truncateContent($content, $this->maxChunkLength);

        $prompt = $this->buildPrompt($query, $truncatedContent);

        try {
            $response = $this->llmProvider->generate($prompt, [
                'temperature' => $this->temperature,
                'max_tokens' => 10,
            ]);

            if ($response === null) {
                Log::warning('LLM returned null for relevance scoring', ['index' => $index]);
                return 0.5; // Neutral score on failure
            }

            $score = $this->parseScore($response);

            Log::debug('LLM relevance score', [
                'index' => $index,
                'score' => $score,
            ]);

            return $score;
        } catch (\Exception $e) {
            Log::warning('Failed to score relevance', [
                'index' => $index,
                'message' => $e->getMessage(),
            ]);
            return 0.5;
        }
    }

    /**
     * Build the prompt for relevance scoring.
     *
     * @param string $query The search query
     * @param string $content The chunk content
     * @return string The formatted prompt
     */
    private function buildPrompt(string $query, string $content): string
    {
        return <<<PROMPT
Rate how relevant the following text is to answering the query.

Query: "{$query}"

Text:
---
{$content}
---

Rate relevance on a scale of 0-10 where:
- 0-2: Not relevant at all
- 3-4: Slightly relevant, tangential
- 5-6: Moderately relevant, partially answers
- 7-8: Very relevant, directly answers
- 9-10: Perfect match, completely answers

Respond with ONLY a number 0-10. No explanation.

Score (0-10):
PROMPT;
    }

    /**
     * Parse the LLM response into a normalized score.
     *
     * @param string $response The raw LLM response
     * @return float Normalized score (0.0-1.0)
     */
    private function parseScore(string $response): float
    {
        // Extract first number from response
        if (preg_match('/(\d+(?:\.\d+)?)/', $response, $matches)) {
            $score = (float) $matches[1];

            // Clamp to 0-10 range
            $score = max(0, min(10, $score));

            // Normalize to 0-1
            return $score / 10;
        }

        // Fallback: try to parse as direct number
        $score = (float) trim($response);
        if ($score > 0) {
            return min(1.0, $score / 10);
        }

        return 0.5; // Default neutral score
    }

    /**
     * Truncate content to maximum length while preserving meaning.
     *
     * @param string $content The content to truncate
     * @param int $maxLength Maximum length
     * @return string Truncated content
     */
    private function truncateContent(string $content, int $maxLength): string
    {
        if (strlen($content) <= $maxLength) {
            return $content;
        }

        // Try to truncate at sentence boundary
        $truncated = substr($content, 0, $maxLength);
        $lastPeriod = strrpos($truncated, '.');
        $lastNewline = strrpos($truncated, "\n");
        $boundary = max($lastPeriod, $lastNewline);

        if ($boundary > $maxLength * 0.8) {
            return substr($truncated, 0, $boundary + 1);
        }

        return $truncated . '...';
    }

    /**
     * Fallback ranking when LLM is unavailable.
     *
     * @param array<int, array{node: Node, score: float}> $results Original results
     * @param int $limit Number of results to return
     * @return array<int, array{node: Node, score: float, relevance_score: float, metadata: array}>
     */
    private function fallbackRanking(array $results, int $limit): array
    {
        return array_map(fn ($r) => [
            'node' => $r['node'],
            'score' => $r['score'],
            'relevance_score' => $r['score'],
            'metadata' => array_merge(
                $r['metadata'] ?? [],
                ['llm_relevance' => null, 'fallback' => true]
            ),
        ], array_slice($results, 0, $limit));
    }

    /**
     * Check if re-ranking is enabled.
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Get configuration values.
     *
     * @return array<string, mixed>
     */
    public function getConfig(): array
    {
        return [
            'enabled' => $this->enabled,
            'top_n' => $this->topN,
            'final_results' => $this->finalResults,
            'temperature' => $this->temperature,
        ];
    }
}
