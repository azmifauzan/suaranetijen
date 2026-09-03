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
        Schema::create('user_ratings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('entity_id')->constrained('entities')->cascadeOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->timestamps();

            $table->unique(['user_id', 'entity_id']);
            $table->index(['entity_id', 'rating']);
        });

        Schema::create('rating_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('entity_id')->unique()->constrained('entities')->cascadeOnDelete();
            $table->unsignedInteger('rating_count')->default(0);
            $table->decimal('rating_average', 3, 2)->nullable();
            $table->timestamp('calculated_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rating_snapshots');
        Schema::dropIfExists('user_ratings');
    }
};
