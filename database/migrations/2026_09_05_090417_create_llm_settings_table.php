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
        Schema::create('llm_settings', function (Blueprint $table) {
            $table->id();
            $table->string('base_url')->nullable();
            $table->string('model')->nullable();
            $table->text('api_key')->nullable();
            $table->unsignedInteger('max_tokens')->default(1024);
            $table->decimal('temperature', 3, 2)->default(0.20);
            $table->unsignedInteger('timeout_seconds')->default(30);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('llm_settings');
    }
};
