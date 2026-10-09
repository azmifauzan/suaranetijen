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
        Schema::table('entity_review_videos', function (Blueprint $table) {
            // auto = found by entities:fetch-review-videos, manual = added by an admin.
            $table->string('source', 10)->default('auto')->after('title');
            // An auto video an admin removed is hidden, not deleted, so the daily job cannot re-add it.
            $table->timestamp('hidden_at')->nullable()->after('published_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('entity_review_videos', function (Blueprint $table) {
            $table->dropColumn(['source', 'hidden_at']);
        });
    }
};
