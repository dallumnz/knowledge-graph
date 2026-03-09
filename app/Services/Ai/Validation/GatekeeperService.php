<?php

namespace App\Services\Ai\Validation;

use App\Services\Ai\AiProviderFactory;
use Illuminate\Support\Facades\Log;

/**
 * Gatekeeper Service - Validates that responses actually answer the user's question.
 *
 * The Gatekeeper checks whether a proposed response directly addresses the
 * user's original query. This prevents the system from returning responses
 * that are off-topic, tangential, or completely unrelated.
 *
 * Purpose: Quality control at the output level
 * Input: User query + proposed response
 * Output: Pass/fail + explanation
 * On Fail: Return fallback message instead of bad response
 */
class GatekeeperService
{
    /**
     * The LLM provider instance.
     */
    private $llmProvider;

    /**
     * Temperature for validation (lower = more consistent).
     */
    private float $temperature;

    /**
     * Maximum tokens for validation response.
     */
    private int $maxTokens;

    /**
     * Create a new Gatekeeper service instance.
     */
    public function __construct()
    {
        $this->temperature = 0.1; // Low temperature for consistent validation
        $this->maxTokens = 100;
    }

    /**
     * Validate that the response answers the user's question.
     *
     * @param string $query The original user query
     * @param string $response The proposed response to validate
     * @param array<string, mixed> $context Additional context (optional)
     * @return array{pass: bool, explanation: string} Validation result
     */
    public function validate(string $query, string $response, array $context = []): array
    {
        if (empty(trim($query)) || empty(trim($response))) {
            Log::warning('Gatekeeper: Empty query or response provided');
            return [
                'pass' => false,
                'explanation' => 'Empty query or response provided',
            ];
        }

        try {
            $this->llmProvider = AiProviderFactory::makeLlmProvider();

            if (!$this->llmProvider->isAvailable()) {
                Log::warning('Gatekeeper: LLM provider not available');
                // Fail open - if we can't validate, assume it's okay
                return [
                    'pass' => true,
                    'explanation' => 'LLM provider unavailable - validation skipped',
                ];
            }

            $prompt = $this->buildPrompt($query, $response);

            $result = $this->llmProvider->generate($prompt, [
                'temperature' => $this->temperature,
                'max_tokens' => $this->maxTokens,
            ]);

            if ($result === null) {
                Log::warning('Gatekeeper: LLM returned null');
                return [
                    'pass' => true,
                    'explanation' => 'Validation failed - assuming pass',
                ];
            }

            return $this->parseResult($result);
        } catch (\Exception $e) {
            Log::error('Gatekeeper validation failed', [
                'message' => $e->getMessage(),
            ]);

            // Fail open on exception
            return [
                'pass' => true,
                'explanation' => 'Validation error: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Build the validation prompt.
     *
     * @param string $query The user query
     * @param string $response The proposed response
     * @return string The formatted prompt
     */
    private function buildPrompt(string $query, string $response): string
    {
        return <<<PROMPT
You are a quality control validator. Your job is to determine if a response actually answers the user's question.

User Question:
"{$query}"

Proposed Response:
"{$response}"

Does this response directly answer the user's question?

Answer with ONLY:
YES - <one sentence explanation>
OR
NO - <one sentence explanation>

Your answer (YES/NO):
PROMPT;
    }

    /**
     * Parse the LLM response into a validation result.
     *
     * @param string $result The raw LLM response
     * @return array{pass: bool, explanation: string}
     */
    private function parseResult(string $result): array
    {
        $result = trim($result);
        $resultLower = strtolower($result);

        // Check for YES/NO at the start
        $passes = str_starts_with($resultLower, 'yes');

        // Extract explanation after the dash or first space
        $explanation = $result;
        if (str_contains($result, ' - ')) {
            $parts = explode(' - ', $result, 2);
            $explanation = $parts[1] ?? $result;
        } elseif (str_contains($result, ' ')) {
            $parts = explode(' ', $result, 2);
            $explanation = $parts[1] ?? $result;
        }

        $explanation = trim($explanation);
        if (empty($explanation)) {
            $explanation = $passes ? 'Response answers the question' : 'Response does not answer the question';
        }

        Log::debug('Gatekeeper validation result', [
            'pass' => $passes,
            'explanation' => $explanation,
        ]);

        return [
            'pass' => $passes,
            'explanation' => $explanation,
        ];
    }

    /**
     * Get the fallback message when validation fails.
     *
     * @return string
     */
    public function getFallbackMessage(): string
    {
        return "I couldn't find a complete answer to your question. Could you rephrase or provide more details?";
    }
}
