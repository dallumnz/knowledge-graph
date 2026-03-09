<?php

namespace App\Services\Ai\Providers;

use App\Contracts\Ai\EmbeddingProviderInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Local-ai embedding provider.
 *
 * Uses local-ai's OpenAI-compatible embeddings API at localhost:8080.
 * Supports the nomic-embed-text-v1.5 model with 768 dimensions.
 */
class LocalAiEmbeddingProvider implements EmbeddingProviderInterface
{
    /**
     * The local-ai base URL.
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
     * Create a new local-ai embedding provider instance.
     */
    public function __construct()
    {
        $config = config('ai.providers.local-ai', []);

        $this->baseUrl = rtrim($config['url'] ?? 'http://localhost:8080', '/');
        $this->model = $config['embedding_model'] ?? 'nomic-embed-text-v1.5';
        $this->dimensions = (int) ($config['embedding_dimensions'] ?? 768);
        $this->apiKey = $config['api_key'] ?? '';
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
                    Log::error('Invalid local-ai response structure', ['response' => $data]);
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
                    Log::info('Embedding generated after retry', [
                        'attempt' => $attempt,
                        'model' => $this->model,
                    ]);
                }

                return $embedding;
            } catch (\Exception $e) {
                $lastException = $e;

                Log::warning('Local-ai embedding attempt failed', [
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

        Log::error('Failed to generate embedding after all retries', [
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
            Log::debug('Local-ai availability check failed', [
                'message' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
