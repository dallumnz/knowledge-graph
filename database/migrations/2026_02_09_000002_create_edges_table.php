<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('edges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_id')->constrained('nodes')->onDelete('cascade');
            $table->foreignId('target_id')->constrained('nodes')->onDelete('cascade');
            $table->string('relation');
            $table->float('weight')->default(1.0);
            $table->timestamps();

            $table->index(['source_id', 'target_id'], 'idx_edges_source_target');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('edges');
    }
};
