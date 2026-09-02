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
        Schema::create('entity_theme_daily', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entity_id')->constrained('entities')->cascadeOnDelete();
            $table->foreignId('theme_id')->constrained('themes')->cascadeOnDelete();
            $table->date('date');
            $table->unsignedInteger('positive_count')->default(0);
            $table->unsignedInteger('neutral_count')->default(0);
            $table->unsignedInteger('negative_count')->default(0);
            $table->unsignedInteger('observation_count')->default(0);
            $table->timestamps();

            $table->unique(['entity_id', 'theme_id', 'date']);
            $table->index(['entity_id', 'date']);
            $table->index(['date']);
        });

        Schema::create('entity_theme_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entity_id')->constrained('entities')->cascadeOnDelete();
            $table->foreignId('theme_id')->constrained('themes')->cascadeOnDelete();
            $table->string('window'); // 30d, 90d, 365d, all
            $table->unsignedInteger('observation_count')->default(0);
            $table->unsignedInteger('positive_count')->default(0);
            $table->unsignedInteger('neutral_count')->default(0);
            $table->unsignedInteger('negative_count')->default(0);
            $table->unsignedSmallInteger('rank')->default(1);
            $table->timestamp('calculated_at');
            $table->timestamps();

            $table->unique(['entity_id', 'theme_id', 'window']);
            $table->index(['entity_id', 'window', 'rank']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('entity_theme_snapshots');
        Schema::dropIfExists('entity_theme_daily');
    }
};
