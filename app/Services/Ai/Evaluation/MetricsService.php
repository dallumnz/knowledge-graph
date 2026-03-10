<?php

namespace App\Services\Ai\Evaluation;

use App\Models\RagMetrics;
use App\Services\Ai\RagQueryService;
use Illuminate\Support\Facades\Log;

/**
 * Metrics Collection Service
 *
 * Tracks RAG performance automatically after each query.
 * Records per-query metrics including retrieval quality, accuracy,
 * latency, token usage, and validation results.
 *
 * Metrics tracked:
 * - retrieval_precision: % of retrieved chunks that were relevant
 * - retrieval_recall: % of relevant chunks that were retrieved
 * - answer_accuracy: Did the answer correctly address the query?
 * - latency_ms: Total response time
 * - token_usage: Input/output tokens
 * - validation_pass_rate: % of queries passing all validation nodes
 */
class MetricsService
{
    /**
     * Record metrics for a RAG query.
     *
     * @param array $metrics Metrics data to record
     *   - query_id: string (required) - Unique query identifier
     *   - query: string (required) - The actual query text
     *   - user_id: int|null (optional) - User ID
     *   - retrieval_precision: float|null (optional) - Precision score 0.0-1.0
     *   - retrieval_recall: float|null (optional) - Recall score 0.0-1.0
     *   - answer_accuracy: float|null (optional) - Accuracy score 0.0-1.0
     *   - confidence_score: float (required) - Overall confidence 0.0-1.0
     *   - latency_ms: int (required) - Response time in milliseconds
     *   - tokens_input: int (required) - Input token count
     *   - tokens_output: int (required) - Output token count
     *   - validation_results: array (required) - Validation node results
     *   - chunks_retrieved: int|null (optional) - Number of chunks retrieved
     *   - search_method: string|null (optional) - Search method used
     * @return RagMetrics|null The created metrics record or null on failure
     */
    public function record(array $metrics): ?RagMetrics
    {
        try {
            $record = RagMetrics::create([
                'query_id' => $metrics['query_id'],
                'query' => $metrics['query'],
                'user_id' => $metrics['user_id'] ?? null,
                'retrieval_precision' => $metrics['retrieval_precision'] ?? null,
                'retrieval_recall' => $metrics['retrieval_recall'] ?? null,
                'answer_accuracy' => $metrics['answer_accuracy'] ?? null,
                'confidence_score' => $metrics['confidence_score'],
                'latency_ms' => $metrics['latency_ms'],
                'tokens_input' => $metrics['tokens_input'],
                'tokens_output' => $metrics['tokens_output'],
                'validation_results' => $metrics['validation_results'] ?? [],
                'chunks_retrieved' => $metrics['chunks_retrieved'] ?? null,
                'search_method' => $metrics['search_method'] ?? null,
                'validation_passed' => $this->determineValidationPassed($metrics['validation_results'] ?? []),
            ]);

            Log::debug('RAG metrics recorded', [
                'query_id' => $metrics['query_id'],
                'latency_ms' => $metrics['latency_ms'],
                'confidence_score' => $metrics['confidence_score'],
            ]);

            return $record;
        } catch (\Exception $e) {
            Log::error('Failed to record RAG metrics', [
                'query_id' => $metrics['query_id'] ?? 'unknown',
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Record metrics from a RAG query result.
     * Convenience method that extracts metrics from RagQueryService output.
     *
     * @param array $ragResult The result from RagQueryService::query()
     * @param string $queryId Unique identifier for this query
     * @param int|null $userId User ID if authenticated
     * @param int|null $tokensInput Number of input tokens (if available)
     * @param int|null $tokensOutput Number of output tokens (if available)
     * @return RagMetrics|null The created metrics record
     */
    public function recordFromRagResult(
        array $ragResult,
        string $queryId,
        ?int $userId = null,
        ?int $tokensInput = null,
        ?int $tokensOutput = null,
    ): ?RagMetrics {
        $metrics = [
            'query_id' => $queryId,
            'query' => $ragResult['query'] ?? '',
            'user_id' => $userId,
            'confidence_score' => $ragResult['confidence_score'] ?? 0.0,
            'latency_ms' => $ragResult['timing']['total_ms'] ?? 0,
            'tokens_input' => $tokensInput ?? $this->estimateInputTokens($ragResult),
            'tokens_output' => $tokensOutput ?? $this->estimateOutputTokens($ragResult['response'] ?? ''),
            'validation_results' => $ragResult['validation'] ?? [],
            'chunks_retrieved' => count($ragResult['sources'] ?? []),
            'search_method' => 'hybrid',
            'validation_passed' => $ragResult['validation']['pass'] ?? null,
        ];

        return $this->record($metrics);
    }

    /**
     * Generate a unique query ID.
     */
    public function generateQueryId(): string
    {
        return 'rag_' . uniqid() . '_' . bin2hex(random_bytes(4));
    }

    /**
     * Get aggregate metrics for a time period.
     *
     * @param \Carbon\Carbon $startDate
     * @param \Carbon\Carbon $endDate
     * @return array Aggregate metrics
     */
    public function getAggregateMetrics(\Carbon\Carbon $startDate, \Carbon\Carbon $endDate): array
    {
        $metrics = RagMetrics::inDateRange($startDate, $endDate);

        $totalQueries = $metrics->count();
        
        if ($totalQueries === 0) {
            return [
                'total_queries' => 0,
                'avg_confidence' => 0,
                'avg_latency_ms' => 0,
                'validation_pass_rate' => 0,
                'total_tokens' => 0,
                'estimated_cost' => 0,
            ];
        }

        return [
            'total_queries' => $totalQueries,
            'avg_confidence' => round($metrics->avg('confidence_score'), 3),
            'avg_latency_ms' => round($metrics->avg('latency_ms'), 0),
            'validation_pass_rate' => round(
                $metrics->where('validation_passed', true)->count() / $totalQueries,
                3
            ),
            'total_tokens' => $metrics->sum('tokens_input') + $metrics->sum('tokens_output'),
            'estimated_cost' => round(
                $metrics->get()->sum(fn ($m) => $m->estimatedCost()),
                4
            ),
        ];
    }

    /**
     * Get daily metrics for the last N days.
     *
     * @param int $days Number of days to look back
     * @return array Daily metrics
     */
    public function getDailyMetrics(int $days = 30): array
    {
        $startDate = now()->subDays($days)->startOfDay();
        $endDate = now()->endOfDay();

        return RagMetrics::inDateRange($startDate, $endDate)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count, AVG(confidence_score) as avg_confidence, AVG(latency_ms) as avg_latency')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->toArray();
    }

    /**
     * Get top failing queries.
     *
     * @param int $limit Number of results to return
     * @param int $days Look back period in days
     * @return array List of failing queries
     */
    public function getTopFailingQueries(int $limit = 10, int $days = 7): array
    {
        return RagMetrics::inDateRange(now()->subDays($days), now())
            ->failedValidation()
            ->orWhere(fn ($q) => $q->where('confidence_score', '<', 0.5))
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(fn ($m) => [
                'query_id' => $m->query_id,
                'query' => $m->query,
                'confidence_score' => $m->confidence_score,
                'validation_passed' => $m->validation_passed,
                'created_at' => $m->created_at->toIso8601String(),
            ])
            ->toArray();
    }

    /**
     * Get latency trends.
     *
     * @param int $days Number of days to analyze
     * @return array Latency statistics
     */
    public function getLatencyTrends(int $days = 30): array
    {
        $metrics = RagMetrics::inDateRange(now()->subDays($days), now());

        return [
            'avg_ms' => round($metrics->avg('latency_ms'), 0),
            'min_ms' => $metrics->min('latency_ms'),
            'max_ms' => $metrics->max('latency_ms'),
            'p95_ms' => $this->calculatePercentile($metrics->pluck('latency_ms')->toArray(), 95),
            'p99_ms' => $this->calculatePercentile($metrics->pluck('latency_ms')->toArray(), 99),
        ];
    }

    /**
     * Determine if validation passed based on results.
     */
    private function determineValidationPassed(array $validationResults): ?bool
    {
        if (empty($validationResults)) {
            return null;
        }

        return $validationResults['pass'] ?? false;
    }

    /**
     * Estimate input tokens from context and query.
     * Rough approximation: ~4 characters per token.
     */
    private function estimateInputTokens(array $ragResult): int
    {
        $text = $ragResult['query'] ?? '';
        
        // Add source content
        foreach ($ragResult['sources'] ?? [] as $source) {
            $text .= $source['content'] ?? '';
        }

        return (int) ceil(strlen($text) / 4);
    }

    /**
     * Estimate output tokens from response.
     * Rough approximation: ~4 characters per token.
     */
    private function estimateOutputTokens(string $response): int
    {
        return (int) ceil(strlen($response) / 4);
    }

    /**
     * Calculate percentile from an array of values.
     */
    private function calculatePercentile(array $values, int $percentile): ?int
    {
        if (empty($values)) {
            return null;
        }

        sort($values);
        $index = (int) ceil(($percentile / 100) * count($values));
        
        return $values[max(0, $index - 1)];
    }
}
