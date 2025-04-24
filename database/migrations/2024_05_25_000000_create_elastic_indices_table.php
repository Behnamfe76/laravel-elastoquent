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
        Schema::create('elastic_indices', function (Blueprint $table) {
            $table->id();
            $table->string('index_name')->unique();
            $table->string('model_class')->nullable();
            $table->string('status')->default('active');
            $table->json('mapping')->nullable();
            $table->json('settings')->nullable();
            $table->integer('document_count')->default(0);
            $table->timestamp('last_indexed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('elastic_indices');
    }
}; 