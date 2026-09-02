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
        Schema::create('sentiment_observations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entity_id')->constrained('entities')->cascadeOnDelete();
            $table->foreignId('source_id')->constrained('sources')->cascadeOnDelete();
            $table->foreignId('source_item_id')->constrained('source_items')->cascadeOnDelete();
            $table->string('sentiment'); // positive, neutral, negative
            $table->decimal('model_confidence', 5, 4)->nullable();
            $table->timestamp('observed_at');
            $table->timestamps();

            $table->unique(['entity_id', 'source_item_id']);
            $table->index(['entity_id', 'observed_at']);
            $table->index(['source_id', 'observed_at']);
            $table->index(['sentiment']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sentiment_observations');
    }
};
