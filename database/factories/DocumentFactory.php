<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Document>
 */
class DocumentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->words(4, true),
            'source_type' => fake()->randomElement(['file', 'url', 'text', 'api']),
            'source_path' => fake()->optional()->filePath(),
            'content' => fake()->optional()->paragraphs(3, true),
            'metadata' => [
                'author' => fake()->optional()->name(),
                'document_type' => fake()->optional()->word(),
            ],
            'version' => 1,
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the document is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate that the document is a file type.
     */
    public function file(): static
    {
        return $this->state(fn (array $attributes) => [
            'source_type' => 'file',
            'source_path' => '/docs/'.fake()->word().'.pdf',
        ]);
    }

    /**
     * Indicate that the document is a URL type.
     */
    public function url(): static
    {
        return $this->state(fn (array $attributes) => [
            'source_type' => 'url',
            'source_path' => fake()->url(),
        ]);
    }
}
