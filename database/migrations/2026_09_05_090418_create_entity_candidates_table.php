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
        Schema::create('entity_candidates', function (Blueprint $table) {
            $table->id();
            $table->string('normalized_term')->unique();
            $table->jsonb('raw_terms');
            $table->jsonb('source_types');
            $table->unsignedInteger('frequency_score')->default(0);
            $table->unsignedInteger('unmatched_mention_count')->default(0);
            $table->string('suggested_name')->nullable();
            $table->foreignId('suggested_category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('suggested_entity_type')->nullable(); // brand, product, service
            $table->jsonb('suggested_aliases')->nullable();
            $table->foreignId('suggested_parent_entity_id')->nullable()->constrained('entities')->nullOnDelete();
            $table->text('reasoning')->nullable();
            $table->string('status')->default('pending'); // pending, approved, rejected
            $table->foreignId('entity_id')->nullable()->constrained('entities')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'frequency_score']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('entity_candidates');
    }
};
