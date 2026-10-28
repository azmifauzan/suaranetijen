<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entity_candidates', function (Blueprint $table) {
            $table->string('review_note', 255)->nullable()->after('reviewed_at');
        });
    }

    public function down(): void
    {
        Schema::table('entity_candidates', function (Blueprint $table) {
            $table->dropColumn('review_note');
        });
    }
};
