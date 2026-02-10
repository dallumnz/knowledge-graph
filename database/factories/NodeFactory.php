<?php

namespace Database\Factories;

use App\Models\Node;
use Illuminate\Database\Eloquent\Factories\Factory;

class NodeFactory extends Factory
{
    protected $model = Node::class;

    public function definition(): array
    {
        return [
            'type' => fake()->randomElement(['text_chunk', 'tag', 'entity', 'concept']),
            'content' => fake()->paragraph(),
        ];
    }

    public function textChunk(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'text_chunk',
        ]);
    }

    public function tag(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'tag',
            'content' => fake()->word(),
        ]);
    }
}
