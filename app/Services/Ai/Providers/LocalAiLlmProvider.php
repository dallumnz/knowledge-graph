<?php

namespace App\Services\Ai\Providers;

use App\Contracts\Ai\LlmProviderInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Local-ai LLM provider.
 *
 * Uses local-ai's OpenAI-compatible completions and chat API at localhost:8080.
 * Supports the Qwen3.5-9B-GGUF model for text generation.
 */
class LocalAiLlmProvider implements LlmProviderInterface
{
    /**
     * The local-ai base URL.
     */
    private string $baseUrl;

    /**
     * The LLM model name.
     */
    private string $model;

    /**
     * The API key (optional for local-ai).
     */
    private string $apiKey;

    /**
     * Request timeout in seconds.
     */
    private int $timeout;

    /**
     * Maximum number of retry attempts.
     */
    private int $maxRetries;

    /**
     * Base delay in milliseconds for exponential backoff.
     */
    private int $retryDelayMs;

    /**
     * Default temperature for generation.
     */
    private float $defaultTemperature;

    /**
     * Default max tokens for generation.
     */
    private int $defaultMaxTokens;

    /**
     * Create a new local-ai LLM provider instance.
     */
    public function __construct()
    {
        $config = config('ai.providers.local-ai', []);

        $this->baseUrl = rtrim($config['url'] ?? 'http://localhost:8080', '/');
        $this->model = $config['llm_model'] ?? 'Qwen3.5-9B-GGUF';
        $this->apiKey = $config['api_key'] ?? '';
        $this->timeout = (int) ($config['timeout'] ?? 60);
        $this->maxRetries = (int) config('ai.retry.max_attempts', 3);
        $this->retryDelayMs = (int) config('ai.retry.base_delay_ms', 1000);
        $this->defaultTemperature = (float) ($config['temperature'] ?? 0.7);
        $this->defaultMaxTokens = (int) ($config['max_tokens'] ?? 1024);
    }

    /**
     * @inheritDoc
     */
    public function generate(string $prompt, array $options = []): ?string
    {
        if (empty(trim($prompt))) {
            Log::warning('Empty prompt provided for LLM generation');
            return null;
        }

        $lastException = null;
        $temperature = $options['temperature'] ?? $this->defaultTemperature;
        $maxTokens = $options['max_tokens'] ?? $this->defaultMaxTokens;

        for ($attempt = 1; $attempt <= $this->maxRetries; $attempt++) {
            try {
                $headers = [
                    'Content-Type' => 'application/json',
                ];

                if (!empty($this->apiKey)) {
                    $headers['Authorization'] = "Bearer {$this->apiKey}";
                }

                // Try chat completions endpoint first (more widely supported)
                $response = Http::withHeaders($headers)
                    ->timeout($this->timeout)
                    ->post("{$this->baseUrl}/v1/chat/completions", [
                        'model' => $this->model,
                        'messages' => [
                            ['role' => 'user', 'content' => $prompt],
                        ],
                        'temperature' => $temperature,
                        'max_tokens' => $maxTokens,
                    ]);

                if ($response->failed()) {
                    // Fall back to legacy completions endpoint
                    $response = Http::withHeaders($headers)
                        ->timeout($this->timeout)
                        ->post("{$this->baseUrl}/v1/completions", [
                            'model' => $this->model,
                            'prompt' => $prompt,
                            'temperature' => $temperature,
                            'max_tokens' => $maxTokens,
                        ]);
                }

                if ($response->failed()) {
                    throw new \Exception("HTTP {$response->status()}: {$response->body()}");
                }

                $data = $response->json();

                // Parse chat completions response
                if (isset($data['choices'][0]['message']['content'])) {
                    $content = trim($data['choices'][0]['message']['content']);

                    if ($attempt > 1) {
                        Log::info('LLM generation completed after retry', [
                            'attempt' => $attempt,
                            'model' => $this->model,
                        ]);
                    }

                    return $content;
                }

                // Parse legacy completions response
                if (isset($data['choices'][0]['text'])) {
                    $content = trim($data['choices'][0]['text']);

                    if ($attempt > 1) {
                        Log::info('LLM generation completed after retry', [
                            'attempt' => $attempt,
                            'model' => $this->model,
                        ]);
                    }

                    return $content;
                }

                Log::error('Invalid local-ai LLM response structure', ['response' => $data]);
                return null;
            } catch (\Exception $e) {
                $lastException = $e;

                Log::warning('Local-ai LLM attempt failed', [
                    'attempt' => $attempt,
                    'model' => $this->model,
                    'message' => $e->getMessage(),
                ]);

                if ($attempt < $this->maxRetries) {
                    $delayMs = $this->retryDelayMs * (2 ** ($attempt - 1));
                    usleep($delayMs * 1000);
                }
            }
        }

        Log::error('Failed to generate LLM response after all retries', [
            'model' => $this->model,
            'message' => $lastException?->getMessage(),
        ]);

        return null;
    }

    /**
     * @inheritDoc
     */
    public function chat(array $messages, array $options = []): ?string
    {
        if (empty($messages)) {
            Log::warning('Empty messages array provided for LLM chat');
            return null;
        }

        $lastException = null;
        $temperature = $options['temperature'] ?? $this->defaultTemperature;
        $maxTokens = $options['max_tokens'] ?? $this->defaultMaxTokens;

        for ($attempt = 1; $attempt <= $this->maxRetries; $attempt++) {
            try {
                $headers = [
                    'Content-Type' => 'application/json',
                ];

                if (!empty($this->apiKey)) {
                    $headers['Authorization'] = "Bearer {$this->apiKey}";
                }

                $response = Http::withHeaders($headers)
                    ->timeout($this->timeout)
                    ->post("{$this->baseUrl}/v1/chat/completions", [
                        'model' => $this->model,
                        'messages' => $messages,
                        'temperature' => $temperature,
                        'max_tokens' => $maxTokens,
                    ]);

                if ($response->failed()) {
                    throw new \Exception("HTTP {$response->status()}: {$response->body()}");
                }

                $data = $response->json();

                if (!isset($data['choices'][0]['message']['content'])) {
                    Log::error('Invalid local-ai chat response structure', ['response' => $data]);
                    return null;
                }

                $content = trim($data['choices'][0]['message']['content']);

                if ($attempt > 1) {
                    Log::info('LLM chat completed after retry', [
                        'attempt' => $attempt,
                        'model' => $this->model,
                    ]);
                }

                return $content;
            } catch (\Exception $e) {
                $lastException = $e;

                Log::warning('Local-ai chat attempt failed', [
                    'attempt' => $attempt,
                    'model' => $this->model,
                    'message' => $e->getMessage(),
                ]);

                if ($attempt < $this->maxRetries) {
                    $delayMs = $this->retryDelayMs * (2 ** ($attempt - 1));
                    usleep($delayMs * 1000);
                }
            }
        }

        Log::error('Failed to generate chat response after all retries', [
            'model' => $this->model,
            'message' => $lastException?->getMessage(),
        ]);

        return null;
    }

    /**
     * @inheritDoc
     */
    public function getModel(): string
    {
        return $this->model;
    }

    /**
     * @inheritDoc
     */
    public function isAvailable(): bool
    {
        try {
            $test = $this->generate('Say "available"', ['max_tokens' => 5]);

            return $test !== null;
        } catch (\Exception $e) {
            Log::debug('Local-ai LLM availability check failed', [
                'message' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
