<?php

namespace Database\Factories;

use App\Models\Embedding;
use App\Models\Node;
use Illuminate\Database\Eloquent\Factories\Factory;

class EmbeddingFactory extends Factory
{
    protected $model = Embedding::class;

    public function definition(): array
    {
        // Generate a random 768-dimensional vector
        $vector = [];
        for ($i = 0; $i < 768; $i++) {
            $vector[] = fake()->randomFloat(6, -1, 1);
        }

        return [
            'node_id' => Node::factory(),
            'embedding' => $vector,
        ];
    }
}
