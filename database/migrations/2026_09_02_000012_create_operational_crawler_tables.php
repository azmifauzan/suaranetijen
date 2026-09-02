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
        Schema::create('crawl_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_id')->constrained('sources')->cascadeOnDelete();
            $table->string('cursor_key')->default('default');
            $table->text('cursor_value')->nullable();
            $table->string('last_external_id')->nullable();
            $table->timestamp('last_crawled_at')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->unique(['source_id', 'cursor_key']);
        });

        Schema::create('source_preflight_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_id')->constrained('sources')->cascadeOnDelete();
            $table->string('status'); // healthy, degraded, blocked, policy_disabled, parser_broken, quota_limited
            $table->unsignedInteger('response_time_ms')->nullable();
            $table->text('message')->nullable();
            $table->jsonb('details')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['source_id', 'created_at']);
        });

        Schema::create('raw_payloads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_id')->constrained('sources')->cascadeOnDelete();
            $table->foreignId('source_item_id')->nullable()->constrained('source_items')->nullOnDelete();
            $table->string('payload_ref')->unique();
            $table->longText('payload');
            $table->string('content_type')->default('text/html');
            $table->timestamp('expires_at')->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('raw_payloads');
        Schema::dropIfExists('source_preflight_logs');
        Schema::dropIfExists('crawl_states');
    }
};
