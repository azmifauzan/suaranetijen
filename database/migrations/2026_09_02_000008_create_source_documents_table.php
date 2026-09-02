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
        Schema::create('source_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_id')->constrained('sources')->cascadeOnDelete();
            $table->string('external_id');
            $table->string('canonical_url', 2048)->nullable();
            $table->string('title')->nullable();
            $table->string('title_hash', 64)->nullable();
            $table->string('content_hash', 64)->nullable();
            $table->string('state')->default('discovered'); // discovered, fetching, fetched, failed, expired
            $table->timestamp('published_at')->nullable();
            $table->timestamp('discovered_at')->useCurrent();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->unique(['source_id', 'external_id']);
            $table->index(['source_id', 'state']);
            $table->index(['source_id', 'discovered_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('source_documents');
    }
};
