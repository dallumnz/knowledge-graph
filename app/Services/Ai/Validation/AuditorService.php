<?php

namespace App\Services\Ai\Validation;

use App\Services\Ai\AiProviderFactory;
use Illuminate\Support\Facades\Log;

/**
 * Auditor Service - Anti-hallucination validation.
 *
 * The Auditor verifies that each claim in a response is supported by
 * the retrieved context chunks. This prevents the LLM from making up
 * information or drawing conclusions not present in the source material.
 *
 * Purpose: Prevent hallucinations and ensure factual grounding
 * Input: Response + retrieved context chunks
 * Output: Pass/fail + list of unsupported claims
 * On Fail: Regenerate with stricter grounding or return "I don't have enough information"
 */
class AuditorService
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
     * Maximum context length to include in prompt.
     */
    private int $maxContextLength;

    /**
     * Create a new Auditor service instance.
     */
    public function __construct()
    {
        $this->temperature = 0.1;
        $this->maxTokens = 500;
        $this->maxContextLength = 4000;
    }

    /**
     * Validate that response claims are supported by context.
     *
     * @param string $query The original user query
     * @param string $response The proposed response to validate
     * @param array<int, array<string, mixed>> $contextChunks Retrieved context chunks
     * @return array{pass: bool, explanation: string, unsupported_claims: array<string>} Validation result
     */
    public function validate(string $query, string $response, array $contextChunks = []): array
    {
        if (empty(trim($response))) {
            Log::warning('Auditor: Empty response provided');
            return [
                'pass' => false,
                'explanation' => 'Empty response provided',
                'unsupported_claims' => [],
            ];
        }

        if (empty($contextChunks)) {
            Log::warning('Auditor: No context chunks provided');
            // If no context, we can't validate - fail closed
            return [
                'pass' => false,
                'explanation' => 'No source context available for validation',
                'unsupported_claims' => ['Response generated without source context'],
            ];
        }

        try {
            $this->llmProvider = AiProviderFactory::makeLlmProvider();

            if (!$this->llmProvider->isAvailable()) {
                Log::warning('Auditor: LLM provider not available');
                // Fail open - if we can't validate, assume it's okay
                return [
                    'pass' => true,
                    'explanation' => 'LLM provider unavailable - validation skipped',
                    'unsupported_claims' => [],
                ];
            }

            $context = $this->formatContext($contextChunks);
            $prompt = $this->buildPrompt($response, $context);

            $result = $this->llmProvider->generate($prompt, [
                'temperature' => $this->temperature,
                'max_tokens' => $this->maxTokens,
            ]);

            if ($result === null) {
                Log::warning('Auditor: LLM returned null');
                return [
                    'pass' => true,
                    'explanation' => 'Validation failed - assuming pass',
                    'unsupported_claims' => [],
                ];
            }

            return $this->parseResult($result);
        } catch (\Exception $e) {
            Log::error('Auditor validation failed', [
                'message' => $e->getMessage(),
            ]);

            // Fail open on exception
            return [
                'pass' => true,
                'explanation' => 'Validation error: ' . $e->getMessage(),
                'unsupported_claims' => [],
            ];
        }
    }

    /**
     * Format context chunks for the prompt.
     *
     * @param array<int, array<string, mixed>> $chunks Context chunks
     * @return string Formatted context
     */
    private function formatContext(array $chunks): string
    {
        $formatted = [];
        $totalLength = 0;

        foreach ($chunks as $index => $chunk) {
            $content = $chunk['content'] ?? $chunk['node']->content ?? '';
            $source = $chunk['document']['title'] ?? $chunk['source'] ?? 'Unknown source';

            $chunkText = "[Chunk " . ($index + 1) . " from {$source}]:\n{$content}\n";

            // Check if adding this chunk would exceed limit
            if ($totalLength + strlen($chunkText) > $this->maxContextLength) {
                $remaining = $this->maxContextLength - $totalLength;
                if ($remaining > 100) {
                    $truncated = substr($chunkText, 0, $remaining - 10) . "...\n";
                    $formatted[] = $truncated;
                }
                break;
            }

            $formatted[] = $chunkText;
            $totalLength += strlen($chunkText);
        }

        return implode("\n---\n", $formatted);
    }

    /**
     * Build the validation prompt.
     *
     * @param string $response The proposed response
     * @param string $context The formatted context
     * @return string The formatted prompt
     */
    private function buildPrompt(string $response, string $context): string
    {
        return <<<PROMPT
You are a fact-checker. Your job is to verify that every claim in a response is supported by the provided context.

Context from source documents:
---
{$context}
---

Response to validate:
"{$response}"

Task:
1. Identify every factual claim in the response
2. Check if each claim is directly supported by the context above
3. List any claims that are NOT supported by the context

Important: A claim is "supported" only if the context explicitly contains the information or can directly infer it. Do not use outside knowledge.

Response format:
ALL_SUPPORTED
OR
UNSUPPORTED:
- <first unsupported claim>
- <second unsupported claim>
(etc.)

Your analysis:
PROMPT;
    }

    /**
     * Parse the LLM response into a validation result.
     *
     * @param string $result The raw LLM response
     * @return array{pass: bool, explanation: string, unsupported_claims: array<string>}
     */
    private function parseResult(string $result): array
    {
        $result = trim($result);
        $resultLower = strtolower($result);

        // Check if all claims are supported
        $passes = str_contains($resultLower, 'all_supported');

        // Extract unsupported claims
        $unsupportedClaims = [];

        if (!$passes && str_contains($resultLower, 'unsupported:')) {
            // Extract list items after "UNSUPPORTED:"
            $parts = preg_split('/unsupported:/i', $result, 2);
            if (isset($parts[1])) {
                $claimsText = trim($parts[1]);

                // Parse bullet points or numbered items
                preg_match_all('/^[\s]*[-\d\.]\s*(.+)$/m', $claimsText, $matches);

                foreach ($matches[1] as $claim) {
                    $claim = trim($claim);
                    if (!empty($claim)) {
                        $unsupportedClaims[] = $claim;
                    }
                }

                // If no bullet points found, treat whole text as one claim
                if (empty($unsupportedClaims) && !empty($claimsText)) {
                    $unsupportedClaims[] = $claimsText;
                }
            }
        }

        // Build explanation
        if ($passes) {
            $explanation = 'All claims are supported by the source context';
        } else {
            $claimCount = count($unsupportedClaims);
            if ($claimCount === 0) {
                $explanation = 'Potential hallucinations detected but no specific claims identified';
            } else {
                $explanation = "Found {$claimCount} unsupported claim" . ($claimCount > 1 ? 's' : '');
            }
        }

        Log::debug('Auditor validation result', [
            'pass' => $passes,
            'unsupported_count' => count($unsupportedClaims),
        ]);

        return [
            'pass' => $passes,
            'explanation' => $explanation,
            'unsupported_claims' => $unsupportedClaims,
        ];
    }

    /**
     * Get the fallback message when validation fails.
     *
     * @return string
     */
    public function getFallbackMessage(): string
    {
        return "I don't have enough information in my sources to fully answer this question. The response may contain information not found in the retrieved context.";
    }

    /**
     * Check if response should be regenerated with stricter grounding.
     *
     * @param array<string, mixed> $validationResult The validation result
     * @return bool
     */
    public function shouldRegenerate(array $validationResult): bool
    {
        // Regenerate if there are unsupported claims but not too many
        $unsupportedCount = count($validationResult['unsupported_claims'] ?? []);
        return !$validationResult['pass'] && $unsupportedCount > 0 && $unsupportedCount <= 3;
    }
}
