<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Create documents table
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->string('title', 500);
            $table->string('source_type', 50); // 'file', 'url', 'text', 'api'
            $table->text('source_path')->nullable(); // file path, URL, or external ID
            $table->longText('content')->nullable(); // full original document content
            $table->jsonb('metadata')->default('{}'); // author, date, document_type, tags, etc.
            $table->integer('version')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Indexes for document lookups
            $table->index('source_type', 'idx_documents_type');
            $table->index('is_active', 'idx_documents_active');
            $table->index('created_at', 'idx_documents_created');
        });

        // Add document_id to nodes table
        Schema::table('nodes', function (Blueprint $table) {
            $table->foreignId('document_id')
                ->nullable()
                ->after('content')
                ->constrained('documents')
                ->nullOnDelete();

            $table->index('document_id', 'idx_nodes_document_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove document_id from nodes
        Schema::table('nodes', function (Blueprint $table) {
            $table->dropForeign(['document_id']);
            $table->dropIndex('idx_nodes_document_id');
            $table->dropColumn('document_id');
        });

        // Drop documents table
        Schema::dropIfExists('documents');
    }
};
