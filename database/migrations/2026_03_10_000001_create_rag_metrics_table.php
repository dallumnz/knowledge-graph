<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the rag_metrics table for tracking RAG query performance metrics.
     * Stores per-query metrics including retrieval quality, response accuracy,
     * latency, token usage, and validation results.
     */
    public function up(): void
    {
        Schema::create('rag_metrics', function (Blueprint $table) {
            $table->id();
            $table->string('query_id')->index();
            $table->text('query');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            
            // Retrieval quality metrics
            $table->float('retrieval_precision')->nullable()->comment('Percentage of retrieved chunks that were relevant');
            $table->float('retrieval_recall')->nullable()->comment('Percentage of relevant chunks that were retrieved');
            $table->float('answer_accuracy')->nullable()->comment('Did the answer correctly address the query');
            
            // Confidence and validation
            $table->float('confidence_score')->comment('Overall confidence score from validation pipeline');
            $table->json('validation_results')->comment('Detailed validation results from all nodes');
            
            // Performance metrics
            $table->integer('latency_ms')->comment('Total response time in milliseconds');
            $table->integer('tokens_input')->comment('Number of input tokens');
            $table->integer('tokens_output')->comment('Number of output tokens');
            
            // Additional metadata
            $table->integer('chunks_retrieved')->nullable()->comment('Number of chunks retrieved');
            $table->string('search_method', 50)->nullable()->comment('Search method used: vector, keyword, hybrid');
            $table->boolean('validation_passed')->nullable()->comment('Whether validation passed overall');
            
            $table->timestamps();
            
            // Additional indexes for common queries
            $table->index(['created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index(['validation_passed', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rag_metrics');
    }
};
