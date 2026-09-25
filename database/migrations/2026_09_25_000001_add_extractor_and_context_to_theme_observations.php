<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('theme_observations', function (Blueprint $table) {
            $table->string('extractor', 16)->default('keyword')->after('confidence');
            $table->string('context', 200)->nullable()->after('extractor');
            $table->index(['entity_id', 'extractor']);
        });
    }

    public function down(): void
    {
        Schema::table('theme_observations', function (Blueprint $table) {
            $table->dropIndex(['entity_id', 'extractor']);
            $table->dropColumn(['extractor', 'context']);
        });
    }
};
