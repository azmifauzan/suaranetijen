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
        Schema::create('search_landing_pages', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('keyword');
            $table->string('normalized_keyword')->unique();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('title')->nullable();
            $table->string('meta_description')->nullable();
            $table->text('intro')->nullable();
            $table->string('status')->default('candidate')->index();
            $table->string('source')->index();
            $table->integer('candidate_signal')->default(0);
            $table->timestamp('llm_drafted_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        Schema::create('search_landing_page_themes', function (Blueprint $table) {
            $table->foreignId('search_landing_page_id')->constrained('search_landing_pages')->cascadeOnDelete();
            $table->foreignId('theme_id')->constrained('themes')->cascadeOnDelete();
            $table->primary(['search_landing_page_id', 'theme_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('search_landing_page_themes');
        Schema::dropIfExists('search_landing_pages');
    }
};
