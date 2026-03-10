<?php

namespace App\Console\Commands;

use App\Services\Ai\RagQueryService;
use App\Services\Ai\Evaluation\MetricsService;
use App\Services\Ai\Evaluation\FeedbackService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * RAG Quality Evaluation Command
 *
 * Runs automated quality checks on the RAG system.
 * Evaluates retrieval quality, answer accuracy, and overall performance
 * against known-good queries and edge cases.
 *
 * Usage:
 *   php artisan rag:evaluate
 *   php artisan rag:evaluate --sample=100
 *   php artisan rag:evaluate --dataset=golden-set
 *   php artisan rag:evaluate --report
 *
 * Scheduling:
 *   $schedule->command('rag:evaluate')->dailyAt('02:00');
 */
class EvaluateRagQuality extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rag:evaluate
                            {--sample=50 : Number of recent queries to sample}
                            {--dataset= : Specific dataset to use (golden-set, test-queries)}
                            {--report : Generate detailed report}
                            {--output= : Output file for report}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run RAG quality evaluation against benchmarks';

    /**
     * RAG query service.
     */
    private RagQueryService $ragService;

    /**
     * Metrics service.
     */
    private MetricsService $metricsService;

    /**
     * Feedback service.
     */
    private FeedbackService $feedbackService;

    /**
     * Evaluation results.
     */
    private array $results = [];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->ragService = new RagQueryService();
        $this->metricsService = new MetricsService();
        $this->feedbackService = new FeedbackService();

        $this->info('🔍 RAG Quality Evaluation');
        $this->info(str_repeat('=', 50));

        // Load evaluation datasets
        $datasets = $this->loadDatasets();

        if (empty($datasets)) {
            $this->warn('No evaluation datasets found. Creating default datasets...');
            $this->createDefaultDatasets();
            $datasets = $this->loadDatasets();
        }

        // Run evaluations
        $this->evaluateGoldenSet($datasets['golden-set'] ?? []);
        $this->evaluateTestQueries($datasets['test-queries'] ?? []);
        $this->evaluateRecentQueries();

        // Generate report
        $this->generateReport();

        return self::SUCCESS;
    }

    /**
     * Load evaluation datasets from storage.
     */
    private function loadDatasets(): array
    {
        $datasets = [];
        $basePath = storage_path('rag-evaluation');

        if (! is_dir($basePath)) {
            return $datasets;
        }

        $files = ['golden-set.json', 'test-queries.json'];
        foreach ($files as $file) {
            $path = $basePath . '/' . $file;
            if (file_exists($path)) {
                $content = file_get_contents($path);
                $data = json_decode($content, true);
                $datasets[str_replace('.json', '', $file)] = $data ?? [];
            }
        }

        return $datasets;
    }

    /**
     * Create default evaluation datasets.
     */
    private function createDefaultDatasets(): void
    {
        $basePath = storage_path('rag-evaluation');
        
        if (! is_dir($basePath)) {
            mkdir($basePath, 0755, true);
        }

        // Golden set - known good Q&A pairs
        $goldenSet = [
            [
                'id' => 'gs_001',
                'query' => 'What is the main purpose of this knowledge graph system?',
                'expected_answer_contains' => ['knowledge', 'graph', 'document', 'search'],
                'category' => 'general',
                'difficulty' => 'easy',
            ],
            [
                'id' => 'gs_002',
                'query' => 'How does the hybrid search work?',
                'expected_answer_contains' => ['vector', 'keyword', 'semantic', 'search'],
                'category' => 'technical',
                'difficulty' => 'medium',
            ],
            [
                'id' => 'gs_003',
                'query' => 'What are the validation nodes in the RAG pipeline?',
                'expected_answer_contains' => ['gatekeeper', 'auditor', 'strategist', 'validation'],
                'category' => 'technical',
                'difficulty' => 'medium',
            ],
            [
                'id' => 'gs_004',
                'query' => 'How can I ingest a new document?',
                'expected_answer_contains' => ['ingest', 'document', 'upload', 'API'],
                'category' => 'usage',
                'difficulty' => 'easy',
            ],
        ];

        // Test queries - edge cases and challenging questions
        $testQueries = [
            [
                'id' => 'tq_001',
                'query' => '',
                'expected_behavior' => 'reject_empty',
                'category' => 'edge_case',
                'description' => 'Empty query should be handled gracefully',
            ],
            [
                'id' => 'tq_002',
                'query' => 'a',
                'expected_behavior' => 'handle_short',
                'category' => 'edge_case',
                'description' => 'Single character query',
            ],
            [
                'id' => 'tq_003',
                'query' => 'Tell me about quantum computing and neural networks and machine learning and deep learning',
                'expected_behavior' => 'handle_complex',
                'category' => 'edge_case',
                'description' => 'Very long multi-topic query',
            ],
            [
                'id' => 'tq_004',
                'query' => 'What is the capital of France?',
                'expected_behavior' => 'unknown_topic',
                'category' => 'out_of_scope',
                'description' => 'Question outside knowledge base',
            ],
            [
                'id' => 'tq_005',
                'query' => 'Explain the system architecture using code examples',
                'expected_behavior' => 'technical_detail',
                'category' => 'technical',
                'description' => 'Technical query expecting code',
            ],
        ];

        file_put_contents(
            $basePath . '/golden-set.json',
            json_encode($goldenSet, JSON_PRETTY_PRINT)
        );

        file_put_contents(
            $basePath . '/test-queries.json',
            json_encode($testQueries, JSON_PRETTY_PRINT)
        );

        $this->info('Created default evaluation datasets.');
    }

    /**
     * Evaluate against golden set.
     */
    private function evaluateGoldenSet(array $dataset): void
    {
        if (empty($dataset)) {
            $this->warn('No golden set data to evaluate.');
            return;
        }

        $this->info("\n🎯 Evaluating Golden Set (" . count($dataset) . " queries)");
        $this->info(str_repeat('-', 50));

        $passed = 0;
        $failed = 0;

        foreach ($dataset as $item) {
            $result = $this->evaluateQuery($item['query']);
            
            // Check if answer contains expected terms
            $answerLower = strtolower($result['response']);
            $foundTerms = 0;
            foreach ($item['expected_answer_contains'] ?? [] as $term) {
                if (strpos($answerLower, strtolower($term)) !== false) {
                    $foundTerms++;
                }
            }

            $termCoverage = count($item['expected_answer_contains'] ?? []) > 0
                ? $foundTerms / count($item['expected_answer_contains'])
                : 0;

            $success = $termCoverage >= 0.5 && $result['confidence_score'] >= 0.5;

            if ($success) {
                $passed++;
                $this->info("✅ {$item['id']}: PASSED ({$termCoverage}, confidence: {$result['confidence_score']})");
            } else {
                $failed++;
                $this->warn("❌ {$item['id']}: FAILED ({$termCoverage}, confidence: {$result['confidence_score']})");
            }

            $this->results['golden_set'][] = [
                'id' => $item['id'],
                'query' => $item['query'],
                'success' => $success,
                'term_coverage' => $termCoverage,
                'confidence_score' => $result['confidence_score'],
                'latency_ms' => $result['latency_ms'],
            ];
        }

        $this->results['golden_set_summary'] = [
            'total' => count($dataset),
            'passed' => $passed,
            'failed' => $failed,
            'pass_rate' => round($passed / count($dataset), 3),
        ];

        $this->info("\nGolden Set Summary: {$passed}/" . count($dataset) . " passed (" . round($passed / count($dataset) * 100, 1) . "%)");
    }

    /**
     * Evaluate test queries (edge cases).
     */
    private function evaluateTestQueries(array $dataset): void
    {
        if (empty($dataset)) {
            $this->warn('No test queries to evaluate.');
            return;
        }

        $this->info("\n🧪 Evaluating Test Queries (" . count($dataset) . " cases)");
        $this->info(str_repeat('-', 50));

        $passed = 0;

        foreach ($dataset as $item) {
            $result = $this->evaluateQuery($item['query']);

            // Check expected behavior
            $success = $this->checkExpectedBehavior($item, $result);

            if ($success) {
                $passed++;
                $this->info("✅ {$item['id']}: {$item['expected_behavior']}");
            } else {
                $this->warn("❌ {$item['id']}: {$item['expected_behavior']}");
            }

            $this->results['test_queries'][] = [
                'id' => $item['id'],
                'query' => $item['query'],
                'expected_behavior' => $item['expected_behavior'],
                'success' => $success,
                'response_length' => strlen($result['response']),
                'confidence_score' => $result['confidence_score'],
            ];
        }

        $this->results['test_queries_summary'] = [
            'total' => count($dataset),
            'passed' => $passed,
            'failed' => count($dataset) - $passed,
            'pass_rate' => round($passed / count($dataset), 3),
        ];

        $this->info("\nTest Queries Summary: {$passed}/" . count($dataset) . " passed");
    }

    /**
     * Evaluate recent production queries.
     */
    private function evaluateRecentQueries(): void
    {
        $sample = (int) $this->option('sample');
        
        $this->info("\n📊 Analyzing Recent Queries (sample: {$sample})");
        $this->info(str_repeat('-', 50));

        $metrics = \App\Models\RagMetrics::orderBy('created_at', 'desc')
            ->limit($sample)
            ->get();

        if ($metrics->isEmpty()) {
            $this->warn('No recent query metrics found.');
            return;
        }

        $stats = [
            'avg_confidence' => round($metrics->avg('confidence_score'), 3),
            'avg_latency' => round($metrics->avg('latency_ms'), 0),
            'validation_pass_rate' => round(
                $metrics->where('validation_passed', true)->count() / $metrics->count(),
                3
            ),
            'low_confidence_count' => $metrics->where('confidence_score', '<', 0.5)->count(),
            'high_latency_count' => $metrics->where('latency_ms', '>', 5000)->count(),
        ];

        $this->info("Total queries analyzed: {$metrics->count()}");
        $this->info("Average confidence: {$stats['avg_confidence']}");
        $this->info("Average latency: {$stats['avg_latency']}ms");
        $this->info("Validation pass rate: {$stats['validation_pass_rate']}");
        $this->info("Low confidence queries: {$stats['low_confidence_count']}");
        $this->info("High latency queries (>5s): {$stats['high_latency_count']}");

        $this->results['recent_queries'] = $stats;

        // Add feedback analysis
        $feedbackReport = $this->feedbackService->getFeedbackReport(7);
        $this->results['feedback_summary'] = $feedbackReport['summary'];
        
        if ($feedbackReport['summary']['total_feedback'] > 0) {
            $this->info("\n📈 User Feedback (last 7 days):");
            $this->info("Total feedback: {$feedbackReport['summary']['total_feedback']}");
            $this->info("Satisfaction rate: " . round($feedbackReport['satisfaction_score'] * 100, 1) . "%");
        }
    }

    /**
     * Evaluate a single query and return results.
     */
    private function evaluateQuery(string $query): array
    {
        $startTime = microtime(true);
        
        $result = $this->ragService->query($query, [
            'validate' => true,
            'context_chunks' => 5,
            'rerank' => true,
        ]);

        return [
            'response' => $result['response'] ?? '',
            'confidence_score' => $result['confidence_score'] ?? 0,
            'latency_ms' => round((microtime(true) - $startTime) * 1000),
            'validation_passed' => $result['validation']['pass'] ?? false,
        ];
    }

    /**
     * Check if result matches expected behavior.
     */
    private function checkExpectedBehavior(array $testCase, array $result): bool
    {
        return match ($testCase['expected_behavior']) {
            'reject_empty' => strlen($result['response']) > 0 || $result['confidence_score'] < 0.5,
            'handle_short' => strlen($result['response']) > 0,
            'handle_complex' => strlen($result['response']) > 100,
            'unknown_topic' => $result['confidence_score'] < 0.5 || stripos($result['response'], 'not found') !== false,
            'technical_detail' => strlen($result['response']) > 50,
            default => true,
        };
    }

    /**
     * Generate and output evaluation report.
     */
    private function generateReport(): void
    {
        $this->info("\n📋 EVALUATION REPORT");
        $this->info(str_repeat('=', 50));

        $report = [
            'timestamp' => now()->toIso8601String(),
            'summary' => [
                'golden_set_pass_rate' => $this->results['golden_set_summary']['pass_rate'] ?? 0,
                'test_queries_pass_rate' => $this->results['test_queries_summary']['pass_rate'] ?? 0,
                'avg_confidence' => $this->results['recent_queries']['avg_confidence'] ?? 0,
                'validation_pass_rate' => $this->results['recent_queries']['validation_pass_rate'] ?? 0,
            ],
            'details' => $this->results,
        ];

        // Display summary
        $this->info("\nOverall Quality Score: " . $this->calculateOverallScore() . "/100");
        $this->info("Golden Set Pass Rate: " . round(($report['summary']['golden_set_pass_rate'] ?? 0) * 100, 1) . "%");
        $this->info("Test Queries Pass Rate: " . round(($report['summary']['test_queries_pass_rate'] ?? 0) * 100, 1) . "%");
        $this->info("Average Confidence: " . round(($report['summary']['avg_confidence'] ?? 0) * 100, 1) . "%");

        // Save report if requested
        if ($this->option('output')) {
            $outputPath = $this->option('output');
            file_put_contents($outputPath, json_encode($report, JSON_PRETTY_PRINT));
            $this->info("\nReport saved to: {$outputPath}");
        }

        if ($this->option('report')) {
            $reportPath = storage_path('rag-evaluation/reports/evaluation_' . now()->format('Y-m-d_H-i-s') . '.json');
            $dir = dirname($reportPath);
            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            file_put_contents($reportPath, json_encode($report, JSON_PRETTY_PRINT));
            $this->info("Report saved to: {$reportPath}");
        }

        $this->info("\n✅ Evaluation complete!");
    }

    /**
     * Calculate overall quality score.
     */
    private function calculateOverallScore(): int
    {
        $scores = [];

        if (isset($this->results['golden_set_summary'])) {
            $scores[] = $this->results['golden_set_summary']['pass_rate'] * 100;
        }

        if (isset($this->results['test_queries_summary'])) {
            $scores[] = $this->results['test_queries_summary']['pass_rate'] * 100;
        }

        if (isset($this->results['recent_queries'])) {
            $scores[] = $this->results['recent_queries']['avg_confidence'] * 100;
            $scores[] = $this->results['recent_queries']['validation_pass_rate'] * 100;
        }

        return empty($scores) ? 0 : (int) round(array_sum($scores) / count($scores));
    }
}
