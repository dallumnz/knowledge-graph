<?php

namespace App\Livewire;

use App\Models\RagMetrics;
use App\Models\UserFeedback;
use App\Services\Ai\Evaluation\MetricsService;
use App\Services\Ai\Evaluation\FeedbackService;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

/**
 * RAG Dashboard Component
 *
 * Monitoring dashboard for RAG quality metrics.
 * Displays daily query volume, confidence scores, validation rates,
 * user satisfaction, failing queries, token usage, and latency trends.
 */
class RagDashboard extends Component
{
    /**
     * Time period filter (7, 30, 90 days).
     */
    public int $timePeriod = 7;

    /**
     * Metrics service.
     */
    protected MetricsService $metricsService;

    /**
     * Feedback service.
     */
    protected FeedbackService $feedbackService;

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->metricsService = new MetricsService();
        $this->feedbackService = new FeedbackService();
    }

    /**
     * Render the dashboard.
     */
    public function render()
    {
        $startDate = now()->subDays($this->timePeriod)->startOfDay();
        $endDate = now()->endOfDay();

        return view('livewire.rag-dashboard', [
            'summary' => $this->getSummaryMetrics($startDate, $endDate),
            'dailyVolume' => $this->getDailyVolume($startDate, $endDate),
            'confidenceTrend' => $this->getConfidenceTrend($startDate, $endDate),
            'latencyTrend' => $this->getLatencyTrend($startDate, $endDate),
            'satisfactionData' => $this->getSatisfactionData($startDate, $endDate),
            'tokenUsage' => $this->getTokenUsage($startDate, $endDate),
            'failingQueries' => $this->getFailingQueries($startDate, $endDate),
            'recentFeedback' => $this->getRecentFeedback(),
            'validationStats' => $this->getValidationStats($startDate, $endDate),
        ]);
    }

    /**
     * Update time period.
     */
    public function setTimePeriod(int $days): void
    {
        $this->timePeriod = $days;
    }

    /**
     * Get summary metrics.
     */
    private function getSummaryMetrics(CarbonInterface $startDate, CarbonInterface $endDate): array
    {
        $metrics = RagMetrics::inDateRange($startDate, $endDate);
        $totalQueries = $metrics->count();

        if ($totalQueries === 0) {
            return [
                'total_queries' => 0,
                'avg_confidence' => 0,
                'validation_pass_rate' => 0,
                'satisfaction_rate' => 0,
                'avg_latency_ms' => 0,
                'estimated_cost' => 0,
            ];
        }

        $satisfaction = $this->feedbackService->getSatisfactionScore($this->timePeriod);

        return [
            'total_queries' => $totalQueries,
            'avg_confidence' => round($metrics->avg('confidence_score'), 3),
            'validation_pass_rate' => round(
                $metrics->where('validation_passed', true)->count() / $totalQueries,
                3
            ),
            'satisfaction_rate' => $satisfaction,
            'avg_latency_ms' => round($metrics->avg('latency_ms'), 0),
            'estimated_cost' => round(
                $metrics->get()->sum(fn ($m) => $m->estimatedCost()),
                4
            ),
        ];
    }

    /**
     * Get daily query volume.
     */
    private function getDailyVolume(Carbon $startDate, Carbon $endDate): array
    {
        return RagMetrics::inDateRange($startDate, $endDate)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn ($row) => [
                'date' => $row->date,
                'count' => $row->count,
            ])
            ->toArray();
    }

    /**
     * Get confidence score trend.
     */
    private function getConfidenceTrend(Carbon $startDate, Carbon $endDate): array
    {
        return RagMetrics::inDateRange($startDate, $endDate)
            ->selectRaw('DATE(created_at) as date, AVG(confidence_score) as avg_confidence')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn ($row) => [
                'date' => $row->date,
                'confidence' => round($row->avg_confidence, 3),
            ])
            ->toArray();
    }

    /**
     * Get latency trend.
     */
    private function getLatencyTrend(Carbon $startDate, Carbon $endDate): array
    {
        return RagMetrics::inDateRange($startDate, $endDate)
            ->selectRaw('
                DATE(created_at) as date, 
                AVG(latency_ms) as avg_latency,
                MAX(latency_ms) as max_latency,
                MIN(latency_ms) as min_latency
            ')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn ($row) => [
                'date' => $row->date,
                'avg' => round($row->avg_latency, 0),
                'max' => $row->max_latency,
                'min' => $row->min_latency,
            ])
            ->toArray();
    }

    /**
     * Get satisfaction data.
     */
    private function getSatisfactionData(Carbon $startDate, Carbon $endDate): array
    {
        $feedback = UserFeedback::inDateRange($startDate, $endDate);
        
        $positive = $feedback->clone()->positive()->count();
        $negative = $feedback->clone()->negative()->count();
        $total = $positive + $negative;

        return [
            'positive' => $positive,
            'negative' => $negative,
            'total' => $total,
            'rate' => $total > 0 ? round($positive / $total, 3) : 0,
        ];
    }

    /**
     * Get token usage statistics.
     */
    private function getTokenUsage(Carbon $startDate, Carbon $endDate): array
    {
        $metrics = RagMetrics::inDateRange($startDate, $endDate);

        return [
            'total_input' => $metrics->sum('tokens_input'),
            'total_output' => $metrics->sum('tokens_output'),
            'total' => $metrics->sum('tokens_input') + $metrics->sum('tokens_output'),
            'avg_per_query' => round($metrics->avg('tokens_input') + $metrics->avg('tokens_output'), 0),
        ];
    }

    /**
     * Get top failing queries.
     */
    private function getFailingQueries(Carbon $startDate, Carbon $endDate): array
    {
        return RagMetrics::inDateRange($startDate, $endDate)
            ->where(function ($query) {
                $query->where('validation_passed', false)
                    ->orWhere('confidence_score', '<', 0.5);
            })
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(fn ($m) => [
                'query_id' => $m->query_id,
                'query' => Str($m->query)->limit(100)->value(),
                'confidence_score' => $m->confidence_score,
                'validation_passed' => $m->validation_passed,
                'created_at' => $m->created_at->format('Y-m-d H:i'),
            ])
            ->toArray();
    }

    /**
     * Get recent feedback.
     */
    private function getRecentFeedback(): array
    {
        return UserFeedback::with('user:id,name')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(fn ($f) => [
                'query_id' => $f->query_id,
                'rating' => $f->rating,
                'comment' => Str($f->comment)->limit(100)->value(),
                'user_name' => $f->user?->name ?? 'Anonymous',
                'created_at' => $f->created_at->format('Y-m-d H:i'),
            ])
            ->toArray();
    }

    /**
     * Get validation statistics.
     */
    private function getValidationStats(Carbon $startDate, Carbon $endDate): array
    {
        $metrics = RagMetrics::inDateRange($startDate, $endDate)
            ->whereNotNull('validation_results')
            ->get();

        $nodeStats = [
            'gatekeeper' => ['passed' => 0, 'failed' => 0],
            'auditor' => ['passed' => 0, 'failed' => 0],
            'strategist' => ['passed' => 0, 'failed' => 0],
        ];

        foreach ($metrics as $metric) {
            $results = $metric->validation_results['results'] ?? [];
            
            foreach ($results as $node => $result) {
                if (isset($nodeStats[$node])) {
                    if ($result['pass'] ?? false) {
                        $nodeStats[$node]['passed']++;
                    } else {
                        $nodeStats[$node]['failed']++;
                    }
                }
            }
        }

        // Calculate rates
        foreach ($nodeStats as $node => $stats) {
            $total = $stats['passed'] + $stats['failed'];
            $nodeStats[$node]['pass_rate'] = $total > 0 ? round($stats['passed'] / $total, 3) : 0;
        }

        return $nodeStats;
    }
}
