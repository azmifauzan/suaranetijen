<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Index the source_item_id lookups every match/classify/extract job runs.
     *
     * PostgreSQL does not index a foreign key column on its own; without this each of those
     * jobs sequentially scanned all of raw_payloads (29.8M scans on staging, 2 Nov 2026).
     */
    public function up(): void
    {
        Schema::table('raw_payloads', function (Blueprint $table) {
            $table->index('source_item_id');
        });
    }

    public function down(): void
    {
        Schema::table('raw_payloads', function (Blueprint $table) {
            $table->dropIndex(['source_item_id']);
        });
    }
};
