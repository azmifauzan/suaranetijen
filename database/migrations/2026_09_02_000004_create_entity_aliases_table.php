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
        Schema::create('entity_aliases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entity_id')->constrained('entities')->cascadeOnDelete();
            $table->string('alias');
            $table->string('normalized_alias');
            $table->string('alias_type')->default('common_variant'); // primary, common_variant, abbreviation, misspelling
            $table->timestamps();

            $table->unique(['entity_id', 'normalized_alias']);
            $table->index(['normalized_alias']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('entity_aliases');
    }
};
