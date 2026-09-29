<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('llm_settings', function (Blueprint $table) {
            $table->string('fallback_model')->nullable()->after('model');
        });
    }

    public function down(): void
    {
        Schema::table('llm_settings', function (Blueprint $table) {
            $table->dropColumn('fallback_model');
        });
    }
};
