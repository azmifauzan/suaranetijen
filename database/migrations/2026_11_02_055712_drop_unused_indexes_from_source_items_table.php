<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * No query filters source_items by content_hash or expires_at; both are read by id only.
     * The two indexes cost 615MB and a write on each of ~100k inserts a day while serving
     * 24 and 9 scans in two months (pg_stat_user_indexes on staging, 2 Nov 2026).
     */
    public function up(): void
    {
        Schema::table('source_items', function (Blueprint $table) {
            $table->dropIndex(['content_hash']);
            $table->dropIndex(['expires_at']);
        });
    }

    public function down(): void
    {
        Schema::table('source_items', function (Blueprint $table) {
            $table->index('content_hash');
            $table->index('expires_at');
        });
    }
};
