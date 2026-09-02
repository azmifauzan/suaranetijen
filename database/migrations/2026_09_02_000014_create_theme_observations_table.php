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
        Schema::create('theme_observations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entity_id')->constrained('entities')->cascadeOnDelete();
            $table->foreignId('theme_id')->constrained('themes')->cascadeOnDelete();
            $table->foreignId('source_id')->constrained('sources')->cascadeOnDelete();
            $table->foreignId('source_item_id')->nullable()->constrained('source_items')->cascadeOnDelete();
            $table->string('source_document_hash', 64)->nullable();
            $table->string('sentiment'); // positive, neutral, negative
            $table->decimal('confidence', 5, 4)->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['entity_id', 'theme_id', 'source_item_id']);
            $table->index(['entity_id', 'theme_id']);
            $table->index(['entity_id', 'created_at']);
            $table->index(['sentiment']);
            $table->index(['source_document_hash']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('theme_observations');
    }
};
