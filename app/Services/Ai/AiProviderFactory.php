<?php

namespace App\Services\Ai;

use App\Contracts\Ai\EmbeddingProviderInterface;
use App\Contracts\Ai\LlmProviderInterface;
use App\Services\Ai\Providers\LocalAiEmbeddingProvider;
use App\Services\Ai\Providers\LocalAiLlmProvider;
use App\Services\Ai\Providers\LmStudioEmbeddingProvider;

/**
 * Factory for creating AI provider instances.
 *
 * Centralizes provider creation logic and configuration resolution.
 * Supports multiple embedding and LLM providers with runtime switching.
 */
class AiProviderFactory
{
    /**
     * Create an embedding provider instance.
     *
     * @param string|null $provider The provider name (local-ai, lmstudio, etc.) or null for default
     * @return EmbeddingProviderInterface
     * @throws \InvalidArgumentException If the provider is unknown
     */
    public static function makeEmbeddingProvider(?string $provider = null): EmbeddingProviderInterface
    {
        $provider ??= config('ai.default_embedding_provider', 'local-ai');

        return match ($provider) {
            'local-ai' => new LocalAiEmbeddingProvider(),
            'lmstudio' => new LmStudioEmbeddingProvider(),
            default => throw new \InvalidArgumentException("Unknown embedding provider: {$provider}"),
        };
    }

    /**
     * Create an LLM provider instance.
     *
     * @param string|null $provider The provider name (local-ai, etc.) or null for default
     * @return LlmProviderInterface
     * @throws \InvalidArgumentException If the provider is unknown
     */
    public static function makeLlmProvider(?string $provider = null): LlmProviderInterface
    {
        $provider ??= config('ai.default_llm_provider', 'local-ai');

        return match ($provider) {
            'local-ai' => new LocalAiLlmProvider(),
            default => throw new \InvalidArgumentException("Unknown LLM provider: {$provider}"),
        };
    }

    /**
     * Get the list of available embedding providers.
     *
     * @return array<string>
     */
    public static function availableEmbeddingProviders(): array
    {
        return ['local-ai', 'lmstudio'];
    }

    /**
     * Get the list of available LLM providers.
     *
     * @return array<string>
     */
    public static function availableLlmProviders(): array
    {
        return ['local-ai'];
    }
}
