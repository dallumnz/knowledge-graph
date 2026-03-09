<?php

namespace App\Contracts\Ai;

/**
 * Interface for LLM (Large Language Model) providers.
 *
 * All LLM providers (local-ai, OpenAI, Anthropic, etc.) must implement
 * this interface to ensure consistent behavior across the application.
 */
interface LlmProviderInterface
{
    /**
     * Generate a completion for a single prompt.
     *
     * @param string $prompt The prompt to send to the LLM
     * @param array<string, mixed> $options Optional parameters (temperature, max_tokens, etc.)
     * @return string|null The generated text or null on failure
     */
    public function generate(string $prompt, array $options = []): ?string;

    /**
     * Generate a chat completion from a conversation.
     *
     * @param array<int, array{role: string, content: string}> $messages Array of message objects
     * @param array<string, mixed> $options Optional parameters (temperature, max_tokens, etc.)
     * @return string|null The generated response or null on failure
     */
    public function chat(array $messages, array $options = []): ?string;

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
