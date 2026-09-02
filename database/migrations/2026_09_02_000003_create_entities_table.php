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
        Schema::create('entities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('categories')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('entities')->cascadeOnUpdate()->nullOnDelete();
            $table->string('type'); // brand, product, service
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('status')->default('active'); // active, disabled
            $table->boolean('searchable')->default(true);
            $table->boolean('rankable')->default(true);
            $table->timestamps();

            $table->index(['category_id']);
            $table->index(['parent_id']);
            $table->index(['type']);
            $table->index(['status', 'searchable']);
            $table->index(['status', 'rankable']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('entities');
    }
};
