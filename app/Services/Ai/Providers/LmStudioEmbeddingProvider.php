<?php

namespace App\Services\Ai\Providers;

use App\Contracts\Ai\EmbeddingProviderInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * LMStudio embedding provider (legacy adapter).
 *
 * Provides backward compatibility with existing LMStudio installations.
 * Uses LMStudio's OpenAI-compatible embeddings API at localhost:1234.
 *
 * @deprecated Use LocalAiEmbeddingProvider for new installations.
 */
class LmStudioEmbeddingProvider implements EmbeddingProviderInterface
{
    /**
     * The LMStudio base URL.
     */
    private string $baseUrl;

    /**
     * The embedding model name.
     */
    private string $model;

    /**
     * The embedding dimensions.
     */
    private int $dimensions;

    /**
     * The API key.
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
     * Create a new LMStudio embedding provider instance.
     */
    public function __construct()
    {
        $config = config('ai.providers.lmstudio', []);

        $this->baseUrl = rtrim($config['url'] ?? 'http://localhost:1234', '/');
        $this->model = $config['embedding_model'] ?? 'text-embedding-nomic-embed-text-v2';
        $this->dimensions = (int) ($config['embedding_dimensions'] ?? 768);
        $this->apiKey = $config['api_key'] ?? 'lmstudio';
        $this->timeout = (int) ($config['timeout'] ?? 60);
        $this->maxRetries = (int) config('ai.retry.max_attempts', 3);
        $this->retryDelayMs = (int) config('ai.retry.base_delay_ms', 1000);
    }

    /**
     * @inheritDoc
     */
    public function embed(string $text): ?array
    {
        if (empty(trim($text))) {
            Log::warning('Empty text provided for embedding');
            return null;
        }

        $lastException = null;

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
                    ->post("{$this->baseUrl}/v1/embeddings", [
                        'model' => $this->model,
                        'input' => $text,
                    ]);

                if ($response->failed()) {
                    throw new \Exception("HTTP {$response->status()}: {$response->body()}");
                }

                $data = $response->json();

                if (!isset($data['data'][0]['embedding'])) {
                    Log::error('Invalid LMStudio response structure', ['response' => $data]);
                    return null;
                }

                $embedding = $data['data'][0]['embedding'];

                if (count($embedding) !== $this->dimensions) {
                    Log::warning('Embedding dimensions mismatch', [
                        'expected' => $this->dimensions,
                        'actual' => count($embedding),
                        'model' => $this->model,
                    ]);
                }

                if ($attempt > 1) {
                    Log::info('LMStudio embedding generated after retry', [
                        'attempt' => $attempt,
                        'model' => $this->model,
                    ]);
                }

                return $embedding;
            } catch (\Exception $e) {
                $lastException = $e;

                Log::warning('LMStudio embedding attempt failed', [
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

        Log::error('Failed to generate LMStudio embedding after all retries', [
            'model' => $this->model,
            'message' => $lastException?->getMessage(),
        ]);

        return null;
    }

    /**
     * @inheritDoc
     */
    public function embedBatch(array $texts): array
    {
        $results = [];

        foreach ($texts as $text) {
            $results[] = $this->embed($text);
        }

        return $results;
    }

    /**
     * @inheritDoc
     */
    public function getDimensions(): int
    {
        return $this->dimensions;
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
            $test = $this->embed('test');

            return $test !== null && count($test) === $this->dimensions;
        } catch (\Exception $e) {
            Log::debug('LMStudio availability check failed', [
                'message' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
