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
        Schema::create('ingestion_failures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_id')->constrained('sources')->cascadeOnDelete();
            $table->foreignId('source_document_id')->nullable()->constrained('source_documents')->nullOnDelete();
            $table->foreignId('source_item_id')->nullable()->constrained('source_items')->nullOnDelete();
            $table->string('stage'); // discovery, fetch, extract, match, classify
            $table->text('error_message');
            $table->string('exception_class')->nullable();
            $table->jsonb('context')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['source_id', 'stage', 'created_at']);
            $table->index('resolved_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ingestion_failures');
    }
};
