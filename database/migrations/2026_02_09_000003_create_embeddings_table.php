<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Pgvector\Laravel\Vector;

return new class extends Migration
{
    public function up(): void
    {
        // For pgvector, we use the vector type with specified dimensions
        Schema::create('embeddings', function (Blueprint $table) {
            $table->foreignId('node_id')->primary()->constrained('nodes')->onDelete('cascade');
            $table->vector('embedding', 768);  // 768 dimensions for nomic-embed-text-v2
            $table->timestamps();

            $table->index('node_id', 'idx_embeddings_node_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('embeddings');
    }
};
