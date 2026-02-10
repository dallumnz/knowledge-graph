<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nodes', function (Blueprint $table) {
            $table->id();
            $table->string('type')->index();
            $table->text('content');
            $table->timestamps();

            $table->index('type', 'idx_nodes_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nodes');
    }
};
