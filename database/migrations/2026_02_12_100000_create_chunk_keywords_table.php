<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration to create chunk_keywords table for weighted keyword storage.
 *
 * This table stores extracted and weighted keywords for text chunks,
 * enabling SEO, filtering, and faceted search capabilities.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('chunk_keywords', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('chunk_id')->comment('Reference to the text chunk (node)');
            $table->string('keyword', 255)->comment('The extracted keyword');
            $table->float('weight')->comment('Keyword weight (0-1 normalized, higher = more important)');
            $table->timestamps();

            // Foreign key constraint
            $table->foreign('chunk_id')
                ->references('id')
                ->on('nodes')
                ->onDelete('cascade');

            // Indexes for common query patterns
            $table->index('keyword');
            $table->index(['chunk_id', 'keyword']);
            $table->index(['keyword', 'weight']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chunk_keywords');
    }
};
