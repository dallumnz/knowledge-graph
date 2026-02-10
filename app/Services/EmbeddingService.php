<?php

namespace App\Services;

use App\Models\Embedding;
use App\Models\Node;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Service for generating and managing embeddings using LMStudio.
 *
 * Makes direct HTTP calls to LMStudio's OpenAI-compatible embeddings API.
 * No Laravel AI SDK dependency needed.
 */
class EmbeddingService
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
     * Maximum number of retry attempts.
     */
    private int $maxRetries;

    /**
     * Base delay in milliseconds for exponential backoff.
     */
    private int $retryDelayMs;

    /**
     * Create a new embedding service instance.
     */
    public function __construct()
    {
        $this->baseUrl = rtrim(config('ai.providers.lmstudio.url', 'http://localhost:1234'), '/');
        $this->model = config('ai.embeddings.model', 'text-embedding-nomic-embed-text-v2');
        $this->dimensions = config('ai.embeddings.dimensions', 768);
        $this->maxRetries = config('ai.embeddings.max_retries', 3);
        $this->retryDelayMs = config('ai.embeddings.retry_delay_ms', 1000);
    }

    /**
     * Generate an embedding for the given text using LMStudio.
     *
     * Makes direct HTTP call to LMStudio's /v1/embeddings endpoint.
     *
     * @param string $text
     * @return array<float>|null
     */
    public function generateEmbedding(string $text): ?array
    {
        if (empty(trim($text))) {
            Log::warning('Empty text provided for embedding');
            return null;
        }

        $lastException = null;

        for ($attempt = 1; $attempt <= $this->maxRetries; $attempt++) {
            try {
                $response = Http::withHeaders([
                    'Content-Type' => 'application/json',
                ])->post("{$this->baseUrl}/v1/embeddings", [
                    'model' => $this->model,
                    'input' => $text,
                ]);

                if ($response->failed()) {
                    throw new \Exception($response->body());
                }

                $data = $response->json();

                if (!isset($data['data'][0]['embedding'])) {
                    Log::error('Invalid LMStudio response', ['response' => $data]);
                    return null;
                }

                $embedding = $data['data'][0]['embedding'];

                if (count($embedding) !== $this->dimensions) {
                    Log::warning('Embedding dimensions mismatch', [
                        'expected' => $this->dimensions,
                        'actual' => count($embedding),
                    ]);
                }

                if ($attempt > 1) {
                    Log::info('Embedding generated after retry', ['attempt' => $attempt]);
                }

                return $embedding;
            } catch (\Exception $e) {
                $lastException = $e;

                Log::warning('Embedding attempt failed', [
                    'attempt' => $attempt,
                    'message' => $e->getMessage(),
                ]);

                if ($attempt < $this->maxRetries) {
                    $delayMs = $this->retryDelayMs * (2 ** ($attempt - 1));
                    usleep($delayMs * 1000);
                }
            }
        }

        Log::error('Failed to generate embedding after all retries', [
            'message' => $lastException?->getMessage(),
        ]);

        return null;
    }

    /**
     * Generate embeddings for multiple texts in a batch.
     *
     * @param  array<string>  $texts  Array of texts to embed
     * @return array<int, array<float>|null> Array of embedding vectors
     */
    public function generateEmbeddingsBatch(array $texts): array
    {
        $results = [];

        foreach ($texts as $text) {
            $results[] = $this->generateEmbedding($text);
        }

        return $results;
    }

    /**
     * Create and store an embedding for a node.
     *
     * Generates an embedding from the node's content and stores it
     * in the embeddings table linked to the node.
     *
     * @param  Node  $node  The node to create an embedding for
     * @return Embedding|null The created embedding or null on failure
     */
    public function createEmbeddingForNode(Node $node): ?Embedding
    {
        if (empty($node->content)) {
            Log::warning('Node has no content for embedding', ['node_id' => $node->id]);

            return null;
        }

        $vector = $this->generateEmbedding($node->content);

        if ($vector === null) {
            Log::error('Failed to generate embedding for node', ['node_id' => $node->id]);

            return null;
        }

        try {
            $embedding = new Embedding;
            $embedding->node_id = $node->id;
            $embedding->embedding = $vector;
            $embedding->save();

            Log::info('Embedding created for node', [
                'node_id' => $node->id,
                'dimensions' => count($vector),
            ]);

            return $embedding;
        } catch (\Exception $e) {
            Log::error('Failed to store embedding for node', [
                'node_id' => $node->id,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Update an existing embedding for a node.
     *
     * @param  Node  $node  The node to update the embedding for
     * @return Embedding|null The updated embedding or null on failure
     */
    public function updateEmbeddingForNode(Node $node): ?Embedding
    {
        // Delete existing embedding if present
        Embedding::where('node_id', $node->id)->delete();

        // Create new embedding
        return $this->createEmbeddingForNode($node);
    }

    /**
     * Delete an embedding for a node.
     *
     * @param  int  $nodeId  The node ID to delete the embedding for
     * @return bool True if deleted successfully
     */
    public function deleteEmbedding(int $nodeId): bool
    {
        try {
            $deleted = Embedding::where('node_id', $nodeId)->delete();

            if ($deleted) {
                Log::info('Embedding deleted for node', ['node_id' => $nodeId]);
            }

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to delete embedding', [
                'node_id' => $nodeId,
                'message' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Get the configured embedding dimensions.
     *
     * @return int The number of dimensions in the embedding vectors
     */
    public function getDimensions(): int
    {
        return $this->dimensions;
    }

    /**
     * Get the configured embedding model name.
     *
     * @return string The model identifier
     */
    public function getModel(): string
    {
        return $this->model;
    }

    /**
     * Check if the AI SDK provider is available.
     *
     * Makes a test call to verify connectivity to LMStudio.
     *
     * @return bool True if the provider is available
     */
    public function isProviderAvailable(): bool
    {
        try {
            // Attempt to generate a test embedding
            $test = $this->generateEmbedding('test');

            return $test !== null && count($test) === $this->dimensions;
        } catch (\Exception $e) {
            return false;
        }
    }
}
