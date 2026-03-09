<?php

namespace App\Contracts\Ai;

/**
 * Interface for embedding providers.
 *
 * All embedding providers (local-ai, OpenAI, LMStudio, etc.) must implement
 * this interface to ensure consistent behavior across the application.
 */
interface EmbeddingProviderInterface
{
    /**
     * Generate an embedding for a single text.
     *
     * @param string $text The text to embed
     * @return array<float>|null The embedding vector or null on failure
     */
    public function embed(string $text): ?array;

    /**
     * Generate embeddings for multiple texts in a batch.
     *
     * @param array<string> $texts Array of texts to embed
     * @return array<int, array<float>|null> Array of embedding vectors
     */
    public function embedBatch(array $texts): array;

    /**
     * Get the embedding dimensions.
     *
     * @return int The number of dimensions in the embedding vectors
     */
    public function getDimensions(): int;

    /**
     * Get the model name used by this provider.
     *
     * @return string The model identifier
     */
    public function getModel(): string;

    /**
     * Check if the provider is available and responsive.
     *
     * @return bool True if the provider can be used
     */
    public function isAvailable(): bool;
}
