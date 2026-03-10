<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the user_feedback table for collecting explicit user feedback
     * on RAG responses. Links to rag_metrics via query_id.
     */
    public function up(): void
    {
        Schema::create('user_feedback', function (Blueprint $table) {
            $table->id();
            $table->string('query_id')->index();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            
            // Feedback data
            $table->enum('rating', ['thumbs_up', 'thumbs_down']);
            $table->text('comment')->nullable();
            $table->text('expected_answer')->nullable()->comment('What the user expected to see');
            
            // Optional categorization
            $table->string('feedback_category', 50)->nullable()->comment('e.g., accuracy, relevance, completeness');
            
            $table->timestamps();
            
            // Additional indexes
            $table->index(['rating', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index(['query_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_feedback');
    }
};
