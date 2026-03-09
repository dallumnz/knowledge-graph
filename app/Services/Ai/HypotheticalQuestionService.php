<?php

namespace App\Services\Ai;

use App\Services\Ai\AiProviderFactory;
use Illuminate\Support\Facades\Log;

/**
 * Service for generating hypothetical questions from text chunks.
 *
 * This service uses an LLM provider to generate questions that a given
 * text chunk could answer. These questions improve query-to-chunk matching
 * in the RAG pipeline by providing alternative ways to match user queries.
 */
class HypotheticalQuestionService
{
    /**
     * The LLM provider instance.
     */
    private $llmProvider;

    /**
     * Number of questions to generate per chunk.
     */
    private int $questionsPerChunk;

    /**
     * Whether the feature is enabled.
     */
    private bool $enabled;

    /**
     * Temperature for question generation (lower = more focused).
     */
    private float $temperature;

    /**
     * Create a new hypothetical question service instance.
     */
    public function __construct()
    {
        $this->enabled = config('ai.features.hypothetical_questions', true);
        $this->questionsPerChunk = config('ai.features.questions_per_chunk', 4);
        $this->temperature = (float) config('ai.providers.local-ai.temperature', 0.7);
    }

    /**
     * Generate hypothetical questions for a text chunk.
     *
     * @param string $text The text chunk to generate questions for
     * @param int|null $count Number of questions to generate (null uses config default)
     * @return array<int, string> Array of generated questions
     */
    public function generateQuestions(string $text, ?int $count = null): array
    {
        if (!$this->enabled) {
            Log::debug('Hypothetical question generation disabled via feature flag');
            return [];
        }

        if (empty(trim($text))) {
            Log::warning('Empty text provided for question generation');
            return [];
        }

        $count ??= $this->questionsPerChunk;
        $count = max(1, min(10, $count)); // Clamp between 1 and 10

        try {
            $this->llmProvider = AiProviderFactory::makeLlmProvider();

            if (!$this->llmProvider->isAvailable()) {
                Log::warning('LLM provider not available for question generation');
                return [];
            }

            $prompt = $this->buildPrompt($text, $count);
            $response = $this->llmProvider->generate($prompt, [
                'temperature' => $this->temperature,
                'max_tokens' => 1024,
            ]);

            if ($response === null) {
                Log::error('LLM returned null response for question generation');
                return [];
            }

            $questions = $this->parseQuestions($response, $count);

            Log::info('Generated hypothetical questions', [
                'count' => count($questions),
                'requested' => $count,
            ]);

            return $questions;
        } catch (\Exception $e) {
            Log::error('Failed to generate hypothetical questions', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [];
        }
    }

    /**
     * Build the prompt for question generation.
     *
     * @param string $text The text chunk
     * @param int $count Number of questions to generate
     * @return string The formatted prompt
     */
    private function buildPrompt(string $text, int $count): string
    {
        return <<<PROMPT
Given the following text chunk, generate {$count} questions that this chunk could answer.

The questions should:
- Be directly answerable using ONLY the information in the text
- Cover different aspects and key points of the content
- Be specific and concrete (not vague or generic)
- Be phrased as actual questions a user might ask

Return ONLY the questions, one per line, without numbering or bullet points.
Do not include any preamble, explanation, or formatting - just the questions.

TEXT CHUNK:
---
{$text}
---

QUESTIONS:
PROMPT;
    }

    /**
     * Parse the LLM response into an array of questions.
     *
     * @param string $response The raw LLM response
     * @param int $maxCount Maximum number of questions to return
     * @return array<int, string> Array of cleaned questions
     */
    private function parseQuestions(string $response, int $maxCount): array
    {
        // Split by newlines and clean up
        $lines = preg_split('/\r?\n/', $response);
        $questions = [];

        foreach ($lines as $line) {
            $line = trim($line);

            // Skip empty lines
            if (empty($line)) {
                continue;
            }

            // Remove common list markers (numbers, bullets, dashes)
            $line = preg_replace('/^\s*[\d\*\-]+[\.\)\s]*/', '', $line);
            $line = trim($line);

            // Skip if it's not a question (doesn't end with ?)
            if (!str_ends_with($line, '?')) {
                // Check if it's a sentence that looks like a question without the mark
                if (preg_match('/^(what|how|when|where|why|who|which|can|does|is|are|do|did|will|would|could|should)\s/i', $line)) {
                    $line .= '?';
                } else {
                    continue;
                }
            }

            // Clean up the question
            $line = $this->cleanQuestion($line);

            if (!empty($line) && strlen($line) > 10) {
                $questions[] = $line;
            }

            // Stop if we have enough questions
            if (count($questions) >= $maxCount) {
                break;
            }
        }

        return array_values($questions);
    }

    /**
     * Clean up a question string.
     *
     * @param string $question The raw question
     * @return string The cleaned question
     */
    private function cleanQuestion(string $question): string
    {
        // Remove extra whitespace
        $question = preg_replace('/\s+/', ' ', $question);

        // Remove quotes if they wrap the entire question
        $question = preg_replace('/^[\'"\x{2018}\x{2019}`]+|[\'"\x{2018}\x{2019}`]+$/u', '', $question);

        // Ensure first letter is capitalized
        $question = ucfirst(trim($question));

        // Ensure it ends with a question mark
        if (!str_ends_with($question, '?')) {
            $question .= '?';
        }

        return $question;
    }

    /**
     * Check if the service is enabled.
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Get the configured number of questions per chunk.
     *
     * @return int
     */
    public function getQuestionsPerChunk(): int
    {
        return $this->questionsPerChunk;
    }
}
