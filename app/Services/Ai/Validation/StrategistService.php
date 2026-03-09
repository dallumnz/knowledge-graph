<?php

namespace App\Services\Ai\Validation;

use App\Services\Ai\AiProviderFactory;
use Illuminate\Support\Facades\Log;

/**
 * Strategist Service - Validates broader context and business sense.
 *
 * The Strategist evaluates whether a response makes sense given the broader
 * context including document sources, dates, reliability, and business logic.
 * This catches issues like outdated information, unreliable sources, or
 * recommendations that don't fit the business context.
 *
 * Purpose: Ensure responses are appropriate and strategically sound
 * Input: Response + user query + document metadata (source, date, type)
 * Output: Pass/fail + strategic concerns
 * On Fail: Add disclaimer or suggest alternative approach
 */
class StrategistService
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
     * Create a new Strategist service instance.
     */
    public function __construct()
    {
        $this->temperature = 0.1;
        $this->maxTokens = 300;
    }

    /**
     * Validate that the response is appropriate given broader context.
     *
     * @param string $query The original user query
     * @param string $response The proposed response to validate
     * @param array<string, mixed> $context Additional context including document metadata
     * @return array{pass: bool, explanation: string, concerns: array<string>, disclaimer: string|null} Validation result
     */
    public function validate(string $query, string $response, array $context = []): array
    {
        if (empty(trim($response))) {
            Log::warning('Strategist: Empty response provided');
            return [
                'pass' => false,
                'explanation' => 'Empty response provided',
                'concerns' => [],
                'disclaimer' => null,
            ];
        }

        try {
            $this->llmProvider = AiProviderFactory::makeLlmProvider();

            if (!$this->llmProvider->isAvailable()) {
                Log::warning('Strategist: LLM provider not available');
                // Fail open
                return [
                    'pass' => true,
                    'explanation' => 'LLM provider unavailable - validation skipped',
                    'concerns' => [],
                    'disclaimer' => null,
                ];
            }

            $sources = $this->formatSources($context);
            $prompt = $this->buildPrompt($query, $response, $sources);

            $result = $this->llmProvider->generate($prompt, [
                'temperature' => $this->temperature,
                'max_tokens' => $this->maxTokens,
            ]);

            if ($result === null) {
                Log::warning('Strategist: LLM returned null');
                return [
                    'pass' => true,
                    'explanation' => 'Validation failed - assuming pass',
                    'concerns' => [],
                    'disclaimer' => null,
                ];
            }

            return $this->parseResult($result);
        } catch (\Exception $e) {
            Log::error('Strategist validation failed', [
                'message' => $e->getMessage(),
            ]);

            // Fail open on exception
            return [
                'pass' => true,
                'explanation' => 'Validation error: ' . $e->getMessage(),
                'concerns' => [],
                'disclaimer' => null,
            ];
        }
    }

    /**
     * Format document sources for the prompt.
     *
     * @param array<string, mixed> $context Context including document metadata
     * @return string Formatted sources
     */
    private function formatSources(array $context): string
    {
        $documents = $context['documents'] ?? $context['chunks'] ?? [];

        if (empty($documents)) {
            return 'No source document metadata available';
        }

        $formatted = [];
        $seenSources = [];

        foreach ($documents as $doc) {
            $source = $doc['source'] ?? $doc['document']['title'] ?? 'Unknown';

            // Skip duplicates
            if (in_array($source, $seenSources)) {
                continue;
            }
            $seenSources[] = $source;

            $date = $doc['date'] ?? $doc['document']['created_at'] ?? 'Unknown date';
            $type = $doc['type'] ?? $doc['document']['source_type'] ?? 'Unknown type';
            $reliability = $doc['reliability'] ?? 'Not assessed';

            $formatted[] = "- {$source} (Type: {$type}, Date: {$date}, Reliability: {$reliability})";
        }

        return implode("\n", $formatted);
    }

    /**
     * Build the validation prompt.
     *
     * @param string $query The user query
     * @param string $response The proposed response
     * @param string $sources Formatted source information
     * @return string The formatted prompt
     */
    private function buildPrompt(string $query, string $response, string $sources): string
    {
        return <<<PROMPT
You are a strategic advisor evaluating whether an answer is appropriate given its sources and context.

User Question:
"{$query}"

Proposed Response:
"{$response}"

Document Sources:
{$sources}

Evaluate the response considering:
1. Is the information potentially outdated? (check dates)
2. Are the sources reliable and appropriate for this question?
3. Does the answer make business/strategic sense?
4. Is there any conflicting information between sources?
5. Should the user be warned about any limitations?

Response format:
APPROPRIATE - <brief explanation>
OR
CONCERNS:
- <first concern>
- <second concern>
(etc.)

DISCLAIMER: <suggested disclaimer text if needed, or "NONE">

Your evaluation:
PROMPT;
    }

    /**
     * Parse the LLM response into a validation result.
     *
     * @param string $result The raw LLM response
     * @return array{pass: bool, explanation: string, concerns: array<string>, disclaimer: string|null}
     */
    private function parseResult(string $result): array
    {
        $result = trim($result);
        $resultLower = strtolower($result);

        // Check if appropriate
        $passes = str_starts_with($resultLower, 'appropriate');

        // Extract concerns
        $concerns = [];
        $disclaimer = null;

        if (!$passes) {
            // Look for CONCERNS section
            if (preg_match('/concerns:?\s*(.+?)(?=disclaimer:|$)/is', $result, $matches)) {
                $concernsText = $matches[1];

                // Parse bullet points
                preg_match_all('/^[\s]*[-\*]\s*(.+)$/m', $concernsText, $bulletMatches);

                foreach ($bulletMatches[1] as $concern) {
                    $concern = trim($concern);
                    if (!empty($concern) && strtolower($concern) !== 'none') {
                        $concerns[] = $concern;
                    }
                }
            }
        }

        // Extract disclaimer
        if (preg_match('/disclaimer:\s*(.+?)$/is', $result, $matches)) {
            $disclaimerText = trim($matches[1]);
            if (strtolower($disclaimerText) !== 'none' && !empty($disclaimerText)) {
                $disclaimer = $disclaimerText;
            }
        }

        // Build explanation
        if ($passes) {
            $explanation = 'Response is appropriate given sources and context';
        } else {
            $concernCount = count($concerns);
            if ($concernCount === 0) {
                $explanation = 'Potential strategic concerns detected';
            } else {
                $explanation = "Found {$concernCount} strategic concern" . ($concernCount > 1 ? 's' : '');
            }
        }

        Log::debug('Strategist validation result', [
            'pass' => $passes,
            'concerns_count' => count($concerns),
            'has_disclaimer' => $disclaimer !== null,
        ]);

        return [
            'pass' => $passes,
            'explanation' => $explanation,
            'concerns' => $concerns,
            'disclaimer' => $disclaimer,
        ];
    }

    /**
     * Get a disclaimer message based on validation result.
     *
     * @param array<string, mixed> $validationResult The validation result
     * @return string|null
     */
    public function getDisclaimer(array $validationResult): ?string
    {
        // Use the LLM-generated disclaimer if available
        if (!empty($validationResult['disclaimer'])) {
            return $validationResult['disclaimer'];
        }

        // Generate a generic disclaimer based on concerns
        $concerns = $validationResult['concerns'] ?? [];
        if (empty($concerns)) {
            return null;
        }

        $concernText = implode('; ', array_slice($concerns, 0, 2));
        return "Note: {$concernText}. Please verify this information before making decisions.";
    }

    /**
     * Check if response needs to be flagged for review.
     *
     * @param array<string, mixed> $validationResult The validation result
     * @return bool
     */
    public function needsReview(array $validationResult): bool
    {
        $concerns = $validationResult['concerns'] ?? [];
        $seriousKeywords = ['outdated', 'unreliable', 'conflicting', 'incorrect', 'false'];

        foreach ($concerns as $concern) {
            $concernLower = strtolower($concern);
            foreach ($seriousKeywords as $keyword) {
                if (str_contains($concernLower, $keyword)) {
                    return true;
                }
            }
        }

        return false;
    }
}
