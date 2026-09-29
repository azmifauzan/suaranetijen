<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sentiment_observations', function (Blueprint $table) {
            $table->string('matched_term', 120)->nullable()->after('source_item_id');
            $table->timestamp('themes_extracted_at')->nullable()->after('observed_at');
        });

        // Rows that exist before batched live extraction were already handled per-opinion
        // or by backfill; only new rows should be picked up by themes:extract-pending.
        DB::table('sentiment_observations')->update(['themes_extracted_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('sentiment_observations', function (Blueprint $table) {
            $table->dropColumn(['matched_term', 'themes_extracted_at']);
        });
    }
};
