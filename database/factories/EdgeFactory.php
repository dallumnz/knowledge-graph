<?php

namespace Database\Factories;

use App\Models\Edge;
use App\Models\Node;
use Illuminate\Database\Eloquent\Factories\Factory;

class EdgeFactory extends Factory
{
    protected $model = Edge::class;

    public function definition(): array
    {
        return [
            'source_id' => Node::factory(),
            'target_id' => Node::factory(),
            'relation' => fake()->randomElement(['related_to', 'followed_by', 'tagged_with', 'references']),
            'weight' => fake()->randomFloat(2, 0, 1),
        ];
    }
}
