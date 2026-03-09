<?php

namespace App\Services\Ai\Validation;

use Illuminate\Support\Facades\Log;

/**
 * Validation Pipeline - Orchestrates all validation nodes.
 *
 * The pipeline coordinates the Gatekeeper, Auditor, and Strategist nodes
 * to provide comprehensive response validation. It supports configurable
 * modes, early exit, and result aggregation.
 *
 * Features:
 * - Configurable node selection
 * - Strict vs lenient mode
 * - Early exit on first failure
 * - Aggregated validation results
 */
class ValidationPipeline
{
    /**
     * The Gatekeeper service instance.
     */
    private GatekeeperService $gatekeeper;

    /**
     * The Auditor service instance.
     */
    private AuditorService $auditor;

    /**
     * The Strategist service instance.
     */
    private StrategistService $strategist;

    /**
     * Whether validation is enabled.
     */
    private bool $enabled;

    /**
     * Strict mode flag.
     */
    private bool $strictMode;

    /**
     * Nodes to run in the pipeline.
     *
     * @var array<string>
     */
    private array $nodes;

    /**
     * Whether to stop on first failure.
     */
    private bool $earlyExit;

    /**
     * Action to take on validation failure.
     * 'fallback': Return fallback message
     * 'retry': Attempt regeneration
     * 'reject': Return error
     */
    private string $failAction;

    /**
     * Create a new validation pipeline instance.
     */
    public function __construct()
    {
        $this->gatekeeper = new GatekeeperService();
        $this->auditor = new AuditorService();
        $this->strategist = new StrategistService();

        // Load configuration
        $this->enabled = config('ai.validation.enabled', true);
        $this->strictMode = config('ai.validation.strict_mode', false);
        $this->nodes = config('ai.validation.nodes', ['gatekeeper', 'auditor', 'strategist']);
        $this->failAction = config('ai.validation.fail_action', 'fallback');
        $this->earlyExit = config('ai.validation.early_exit', false);
    }

    /**
     * Run the full validation pipeline.
     *
     * @param string $query The original user query
     * @param string $response The proposed response
     * @param array<string, mixed> $context Context including chunks and metadata
     * @return array<string, mixed> Complete validation results
     */
    public function validate(string $query, string $response, array $context = []): array
    {
        if (!$this->enabled) {
            Log::debug('Validation pipeline disabled');
            return $this->buildResult(true, [], $response, null);
        }

        $results = [];
        $finalResponse = $response;
        $overallPass = true;

        // Run each configured node
        foreach ($this->nodes as $nodeName) {
            $nodeResult = $this->runNode($nodeName, $query, $finalResponse, $context);
            $results[$nodeName] = $nodeResult;

            if (!$nodeResult['pass']) {
                $overallPass = false;

                // Handle failure based on fail action
                if ($this->failAction === 'fallback') {
                    $finalResponse = $this->getFallbackResponse($nodeName, $nodeResult);
                }

                // Early exit if configured
                if ($this->earlyExit) {
                    Log::info('Validation pipeline early exit', ['node' => $nodeName]);
                    break;
                }
            }
        }

        // In strict mode, all nodes must pass
        if ($this->strictMode) {
            $overallPass = !in_array(false, array_column($results, 'pass'), true);
        }

        // Calculate confidence score
        $confidenceScore = $this->calculateConfidenceScore($results);

        return $this->buildResult($overallPass, $results, $finalResponse, $confidenceScore);
    }

    /**
     * Run a single validation node.
     *
     * @param string $nodeName The name of the node to run
     * @param string $query The user query
     * @param string $response The response to validate
     * @param array<string, mixed> $context Additional context
     * @return array<string, mixed> Node validation result
     */
    private function runNode(string $nodeName, string $query, string $response, array $context): array
    {
        try {
            return match ($nodeName) {
                'gatekeeper' => $this->runGatekeeper($query, $response),
                'auditor' => $this->runAuditor($query, $response, $context),
                'strategist' => $this->runStrategist($query, $response, $context),
                default => [
                    'pass' => true,
                    'explanation' => "Unknown node: {$nodeName}",
                ],
            };
        } catch (\Exception $e) {
            Log::error("Validation node {$nodeName} failed", [
                'message' => $e->getMessage(),
            ]);

            return [
                'pass' => !$this->strictMode, // Fail closed in strict mode
                'explanation' => 'Node error: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Run the Gatekeeper node.
     *
     * @param string $query The user query
     * @param string $response The response to validate
     * @return array<string, mixed>
     */
    private function runGatekeeper(string $query, string $response): array
    {
        $result = $this->gatekeeper->validate($query, $response);

        return [
            'pass' => $result['pass'],
            'explanation' => $result['explanation'],
        ];
    }

    /**
     * Run the Auditor node.
     *
     * @param string $query The user query
     * @param string $response The response to validate
     * @param array<string, mixed> $context Context with chunks
     * @return array<string, mixed>
     */
    private function runAuditor(string $query, string $response, array $context): array
    {
        $chunks = $context['chunks'] ?? [];
        $result = $this->auditor->validate($query, $response, $chunks);

        return [
            'pass' => $result['pass'],
            'explanation' => $result['explanation'],
            'unsupported_claims' => $result['unsupported_claims'] ?? [],
        ];
    }

    /**
     * Run the Strategist node.
     *
     * @param string $query The user query
     * @param string $response The response to validate
     * @param array<string, mixed> $context Context with metadata
     * @return array<string, mixed>
     */
    private function runStrategist(string $query, string $response, array $context): array
    {
        $result = $this->strategist->validate($query, $response, $context);

        return [
            'pass' => $result['pass'],
            'explanation' => $result['explanation'],
            'concerns' => $result['concerns'] ?? [],
            'disclaimer' => $result['disclaimer'] ?? null,
        ];
    }

    /**
     * Get fallback response for a failed node.
     *
     * @param string $nodeName The node that failed
     * @param array<string, mixed> $result The validation result
     * @return string Fallback response
     */
    private function getFallbackResponse(string $nodeName, array $result): string
    {
        return match ($nodeName) {
            'gatekeeper' => $this->gatekeeper->getFallbackMessage(),
            'auditor' => $this->auditor->getFallbackMessage(),
            'strategist' => $this->strategist->getDisclaimer($result) ?? 'Please verify this information.',
            default => 'Unable to provide a validated response.',
        };
    }

    /**
     * Calculate overall confidence score from validation results.
     *
     * @param array<string, array<string, mixed>> $results All node results
     * @return float Confidence score (0.0-1.0)
     */
    private function calculateConfidenceScore(array $results): float
    {
        if (empty($results)) {
            return 0.5; // Neutral if no validation
        }

        $totalScore = 0;
        $nodeCount = 0;

        foreach ($results as $name => $result) {
            $nodeWeight = match ($name) {
                'auditor' => 0.4, // Highest weight - factual accuracy matters most
                'gatekeeper' => 0.35,
                'strategist' => 0.25,
                default => 0.33,
            };

            $nodeScore = $result['pass'] ? 1.0 : 0.0;

            // Adjust score based on severity of issues
            if (!$result['pass']) {
                if (isset($result['unsupported_claims']) && count($result['unsupported_claims']) > 0) {
                    $nodeScore = max(0, 1.0 - (count($result['unsupported_claims']) * 0.2));
                }
                if (isset($result['concerns']) && count($result['concerns']) > 0) {
                    $nodeScore = max(0, $nodeScore - (count($result['concerns']) * 0.1));
                }
            }

            $totalScore += $nodeScore * $nodeWeight;
            $nodeCount += $nodeWeight;
        }

        return $nodeCount > 0 ? round($totalScore / $nodeCount, 2) : 0.5;
    }

    /**
     * Build the final validation result.
     *
     * @param bool $pass Overall pass/fail
     * @param array<string, array<string, mixed>> $results Individual node results
     * @param string $response The final response (may be modified)
     * @param float|null $confidenceScore Calculated confidence score
     * @return array<string, mixed>
     */
    private function buildResult(bool $pass, array $results, string $response, ?float $confidenceScore): array
    {
        $issues = [];
        $recommendations = [];

        foreach ($results as $nodeName => $result) {
            if (!$result['pass']) {
                $issues[] = "{$nodeName}: {$result['explanation']}";

                // Add specific recommendations
                if ($nodeName === 'auditor' && !empty($result['unsupported_claims'])) {
                    $recommendations[] = 'Remove unsupported claims or add source citations';
                }
                if ($nodeName === 'gatekeeper') {
                    $recommendations[] = 'Reformulate response to directly answer the question';
                }
                if ($nodeName === 'strategist' && !empty($result['concerns'])) {
                    $recommendations[] = 'Add disclaimer about source limitations';
                }
            }
        }

        return [
            'pass' => $pass,
            'confidence_score' => $confidenceScore ?? 0.5,
            'response' => $response,
            'results' => $results,
            'issues' => $issues,
            'recommendations' => $recommendations,
            'metadata' => [
                'enabled' => $this->enabled,
                'strict_mode' => $this->strictMode,
                'nodes_run' => array_keys($results),
                'fail_action' => $this->failAction,
            ],
        ];
    }

    /**
     * Check if the pipeline should attempt regeneration.
     *
     * @param array<string, mixed> $validationResult The full validation result
     * @return bool
     */
    public function shouldRegenerate(array $validationResult): bool
    {
        if ($this->failAction !== 'retry') {
            return false;
        }

        // Check auditor for regeneration opportunity
        if (isset($validationResult['results']['auditor'])) {
            return $this->auditor->shouldRegenerate($validationResult['results']['auditor']);
        }

        return false;
    }

    /**
     * Get the current configuration.
     *
     * @return array<string, mixed>
     */
    public function getConfig(): array
    {
        return [
            'enabled' => $this->enabled,
            'strict_mode' => $this->strictMode,
            'nodes' => $this->nodes,
            'fail_action' => $this->failAction,
            'early_exit' => $this->earlyExit,
        ];
    }

    /**
     * Update pipeline configuration dynamically.
     *
     * @param array<string, mixed> $config New configuration values
     */
    public function setConfig(array $config): void
    {
        if (isset($config['enabled'])) {
            $this->enabled = (bool) $config['enabled'];
        }
        if (isset($config['strict_mode'])) {
            $this->strictMode = (bool) $config['strict_mode'];
        }
        if (isset($config['nodes'])) {
            $this->nodes = array_intersect($config['nodes'], ['gatekeeper', 'auditor', 'strategist']);
        }
        if (isset($config['fail_action'])) {
            $this->failAction = in_array($config['fail_action'], ['fallback', 'retry', 'reject'])
                ? $config['fail_action']
                : 'fallback';
        }
        if (isset($config['early_exit'])) {
            $this->earlyExit = (bool) $config['early_exit'];
        }
    }
}
