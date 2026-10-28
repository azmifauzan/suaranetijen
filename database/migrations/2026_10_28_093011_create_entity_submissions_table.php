<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entity_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('entity_candidate_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 120);
            $table->string('entity_type');
            $table->foreignId('category_id')->constrained();
            $table->foreignId('parent_entity_id')->nullable()->constrained('entities')->nullOnDelete();
            $table->string('website_url')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_owner')->default(false);
            $table->timestamps();

            $table->unique(['user_id', 'entity_candidate_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entity_submissions');
    }
};
