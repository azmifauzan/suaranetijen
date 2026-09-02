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
        Schema::create('source_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_id')->constrained('sources')->cascadeOnDelete();
            $table->foreignId('source_document_id')->nullable()->constrained('source_documents')->nullOnDelete();
            $table->string('external_id');
            $table->string('raw_payload_ref')->nullable()->index();
            $table->string('content_hash', 64)->index();
            $table->string('processing_state')->default('pending'); // pending, processed, skipped, failed
            $table->timestamp('published_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();

            $table->unique(['source_id', 'external_id']);
            $table->index(['source_id', 'processing_state']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('source_items');
    }
};
