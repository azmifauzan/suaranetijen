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
        Schema::create('sentiment_daily', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entity_id')->constrained('entities')->cascadeOnDelete();
            $table->date('date');
            $table->unsignedInteger('positive_count')->default(0);
            $table->unsignedInteger('neutral_count')->default(0);
            $table->unsignedInteger('negative_count')->default(0);
            $table->unsignedInteger('opinion_count')->default(0);
            $table->decimal('score', 5, 2)->nullable();
            $table->timestamps();

            $table->unique(['entity_id', 'date']);
            $table->index(['date']);
        });

        Schema::create('sentiment_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entity_id')->constrained('entities')->cascadeOnDelete();
            $table->string('period'); // 30d, 90d, 365d, all
            $table->unsignedInteger('positive_count')->default(0);
            $table->unsignedInteger('neutral_count')->default(0);
            $table->unsignedInteger('negative_count')->default(0);
            $table->unsignedInteger('opinion_count')->default(0);
            $table->decimal('score', 5, 2)->nullable();
            $table->string('sentiment_model_version')->default('v1');
            $table->string('score_formula_version')->default('v1');
            $table->timestamp('calculated_at');
            $table->timestamps();

            $table->unique(['entity_id', 'period']);
            $table->index(['period', 'score']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sentiment_snapshots');
        Schema::dropIfExists('sentiment_daily');
    }
};
