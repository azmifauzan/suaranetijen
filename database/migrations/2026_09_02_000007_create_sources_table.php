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
        Schema::create('sources', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->string('adapter');
            $table->string('source_type'); // forum, social, video_comments, rss, mock
            $table->boolean('enabled')->default(true);
            $table->unsignedInteger('priority')->default(100);
            $table->jsonb('crawl_policy')->nullable();
            $table->jsonb('retention_policy')->nullable();
            $table->string('health_state')->default('healthy'); // healthy, degraded, blocked, policy_disabled, parser_broken, quota_limited
            $table->timestamp('last_preflight_at')->nullable();
            $table->timestamps();

            $table->index(['enabled', 'health_state']);
            $table->index(['priority']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sources');
    }
};
