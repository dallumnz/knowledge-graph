<?php

namespace App\Services\Ai\Evaluation;

use App\Models\UserFeedback;
use App\Models\RagMetrics;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;

/**
 * User Feedback Service
 *
 * Collects and aggregates explicit user feedback on RAG responses.
 * Links feedback to metrics for correlation analysis and generates
 * improvement recommendations.
 *
 * Features:
 * - Submit feedback (thumbs up/down with optional comments)
 * - Aggregate feedback by time period
 * - Calculate satisfaction scores
 * - Identify problematic queries
 * - Generate improvement recommendations
 */
class FeedbackService
{
    /**
     * Submit user feedback for a RAG query.
     *
     * @param string $queryId The query identifier
     * @param int $userId User providing feedback
     * @param string $rating 'thumbs_up' or 'thumbs_down'
     * @param string|null $comment Optional user comment
     * @param string|null $expectedAnswer What the user expected
     * @param string|null $category Feedback category
     * @return UserFeedback|null The created feedback record or null on failure
     */
    public function submitFeedback(
        string $queryId,
        int $userId,
        string $rating,
        ?string $comment = null,
        ?string $expectedAnswer = null,
        ?string $category = null,
    ): ?UserFeedback {
        try {
            // Validate rating
            if (! in_array($rating, ['thumbs_up', 'thumbs_down'])) {
                throw new \InvalidArgumentException("Invalid rating: {$rating}");
            }

            $feedback = UserFeedback::create([
                'query_id' => $queryId,
                'user_id' => $userId,
                'rating' => $rating,
                'comment' => $comment,
                'expected_answer' => $expectedAnswer,
                'feedback_category' => $category,
            ]);

            Log::info('User feedback submitted', [
                'query_id' => $queryId,
                'user_id' => $userId,
                'rating' => $rating,
            ]);

            return $feedback;
        } catch (\Exception $e) {
            Log::error('Failed to submit user feedback', [
                'query_id' => $queryId,
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Check if a user has already provided feedback for a query.
     */
    public function hasFeedback(string $queryId, int $userId): bool
    {
        return UserFeedback::where('query_id', $queryId)
            ->where('user_id', $userId)
            ->exists();
    }

    /**
     * Get feedback for a specific query.
     *
     * @param string $queryId The query identifier
     * @return Collection Collection of feedback records
     */
    public function getFeedbackForQuery(string $queryId): Collection
    {
        return UserFeedback::where('query_id', $queryId)
            ->with('user:id,name,email')
            ->get();
    }

    /**
     * Aggregate feedback by time period.
     *
     * @param \Carbon\Carbon $startDate
     * @param \Carbon\Carbon $endDate
     * @return array Aggregated feedback data
     */
    public function aggregateFeedback(\Carbon\Carbon $startDate, \Carbon\Carbon $endDate): array
    {
        $feedback = UserFeedback::inDateRange($startDate, $endDate);

        $total = $feedback->count();
        
        if ($total === 0) {
            return [
                'total_feedback' => 0,
                'positive_count' => 0,
                'negative_count' => 0,
                'satisfaction_rate' => 0,
                'with_comments' => 0,
            ];
        }

        $positive = $feedback->clone()->positive()->count();
        $withComments = $feedback->clone()->whereNotNull('comment')->count();

        return [
            'total_feedback' => $total,
            'positive_count' => $positive,
            'negative_count' => $total - $positive,
            'satisfaction_rate' => round($positive / $total, 3),
            'with_comments' => $withComments,
            'comment_rate' => round($withComments / $total, 3),
        ];
    }

    /**
     * Get satisfaction score for a time period.
     * Returns a score from 0.0 to 1.0.
     *
     * @param int $days Number of days to look back
     * @return float Satisfaction score (0.0-1.0)
     */
    public function getSatisfactionScore(int $days = 30): float
    {
        $startDate = now()->subDays($days)->startOfDay();
        $endDate = now()->endOfDay();

        $aggregate = $this->aggregateFeedback($startDate, $endDate);

        return $aggregate['satisfaction_rate'] ?? 0.0;
    }

    /**
     * Identify problematic queries based on negative feedback.
     *
     * @param int $limit Number of queries to return
     * @param int $days Look back period in days
     * @return array List of problematic queries with details
     */
    public function identifyProblematicQueries(int $limit = 10, int $days = 7): array
    {
        $startDate = now()->subDays($days)->startOfDay();
        $endDate = now()->endOfDay();

        // Get queries with negative feedback
        $problematic = UserFeedback::inDateRange($startDate, $endDate)
            ->negative()
            ->with(['metrics', 'user:id,name'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($feedback) {
                return [
                    'query_id' => $feedback->query_id,
                    'query' => $feedback->metrics?->query ?? 'Unknown query',
                    'comment' => $feedback->comment,
                    'expected_answer' => $feedback->expected_answer,
                    'confidence_score' => $feedback->metrics?->confidence_score,
                    'user_name' => $feedback->user?->name ?? 'Unknown',
                    'created_at' => $feedback->created_at->toIso8601String(),
                    'has_comment' => ! empty($feedback->comment),
                ];
            });

        return $problematic->toArray();
    }

    /**
     * Get daily satisfaction trends.
     *
     * @param int $days Number of days to analyze
     * @return array Daily satisfaction data
     */
    public function getDailySatisfaction(int $days = 30): array
    {
        $startDate = now()->subDays($days)->startOfDay();
        $endDate = now()->endOfDay();

        return UserFeedback::inDateRange($startDate, $endDate)
            ->selectRaw('
                DATE(created_at) as date,
                COUNT(*) as total,
                SUM(CASE WHEN rating = \'thumbs_up\' THEN 1 ELSE 0 END) as positive
            ')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn ($row) => [
                'date' => $row->date,
                'total' => $row->total,
                'positive' => $row->positive,
                'satisfaction_rate' => $row->total > 0 ? round($row->positive / $row->total, 3) : 0,
            ])
            ->toArray();
    }

    /**
     * Get feedback categories distribution.
     *
     * @param int $days Number of days to analyze
     * @return array Category distribution
     */
    public function getCategoryDistribution(int $days = 30): array
    {
        $startDate = now()->subDays($days)->startOfDay();
        $endDate = now()->endOfDay();

        return UserFeedback::inDateRange($startDate, $endDate)
            ->selectRaw('
                COALESCE(feedback_category, \'uncategorized\') as category,
                rating,
                COUNT(*) as count
            ')
            ->groupBy('category', 'rating')
            ->get()
            ->groupBy('category')
            ->map(function ($items) {
                $positive = $items->where('rating', 'thumbs_up')->sum('count');
                $total = $items->sum('count');

                return [
                    'total' => $total,
                    'positive' => $positive,
                    'negative' => $total - $positive,
                    'satisfaction_rate' => $total > 0 ? round($positive / $total, 3) : 0,
                ];
            })
            ->toArray();
    }

    /**
     * Generate improvement recommendations based on feedback analysis.
     *
     * @param int $days Analysis period in days
     * @return array List of recommendations
     */
    public function generateRecommendations(int $days = 7): array
    {
        $recommendations = [];
        $startDate = now()->subDays($days)->startOfDay();
        $endDate = now()->endOfDay();

        // Analyze satisfaction rate
        $satisfaction = $this->getSatisfactionScore($days);
        if ($satisfaction < 0.7) {
            $recommendations[] = [
                'priority' => 'high',
                'category' => 'satisfaction',
                'message' => "User satisfaction is low ({$satisfaction}). Review recent negative feedback and identify common issues.",
                'action' => 'Review problematic queries and consider retraining or updating knowledge base.',
            ];
        }

        // Analyze confidence scores correlation with feedback
        $lowConfidenceFeedback = RagMetrics::inDateRange($startDate, $endDate)
            ->lowConfidence(0.5)
            ->whereHas('feedback', fn ($q) => $q->negative())
            ->count();

        if ($lowConfidenceFeedback > 3) {
            $recommendations[] = [
                'priority' => 'medium',
                'category' => 'confidence',
                'message' => "{$lowConfidenceFeedback} low-confidence queries received negative feedback.",
                'action' => 'Review validation thresholds and consider adjusting confidence requirements.',
            ];
        }

        // Analyze common complaints
        $problematic = $this->identifyProblematicQueries(20, $days);
        $withComments = array_filter($problematic, fn ($q) => $q['has_comment']);

        if (count($withComments) > 5) {
            $recommendations[] = [
                'priority' => 'medium',
                'category' => 'documentation',
                'message' => 'Multiple users are providing detailed negative feedback.',
                'action' => 'Analyze comment patterns to identify gaps in knowledge base or retrieval issues.',
            ];
        }

        // Check for missing expected answers
        $withExpectedAnswers = UserFeedback::inDateRange($startDate, $endDate)
            ->negative()
            ->whereNotNull('expected_answer')
            ->count();

        if ($withExpectedAnswers > 2) {
            $recommendations[] = [
                'priority' => 'high',
                'category' => 'accuracy',
                'message' => "{$withExpectedAnswers} users provided expected answers that differ from system responses.",
                'action' => 'Compare expected answers with retrieved content to identify retrieval or generation issues.',
            ];
        }

        // Add timestamp
        foreach ($recommendations as &$rec) {
            $rec['generated_at'] = now()->toIso8601String();
        }

        return $recommendations;
    }

    /**
     * Get comprehensive feedback report.
     *
     * @param int $days Analysis period in days
     * @return array Complete feedback report
     */
    public function getFeedbackReport(int $days = 30): array
    {
        $startDate = now()->subDays($days)->startOfDay();
        $endDate = now()->endOfDay();

        return [
            'period' => [
                'start' => $startDate->toIso8601String(),
                'end' => $endDate->toIso8601String(),
                'days' => $days,
            ],
            'summary' => $this->aggregateFeedback($startDate, $endDate),
            'satisfaction_score' => $this->getSatisfactionScore($days),
            'daily_trends' => $this->getDailySatisfaction($days),
            'problematic_queries' => $this->identifyProblematicQueries(10, $days),
            'categories' => $this->getCategoryDistribution($days),
            'recommendations' => $this->generateRecommendations($days),
            'generated_at' => now()->toIso8601String(),
        ];
    }
}
