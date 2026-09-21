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
        Schema::table('entities', function (Blueprint $table): void {
            $table->string('website_url', 2048)->nullable()->after('description');
        });

        Schema::table('sponsored_entries', function (Blueprint $table): void {
            $table->unsignedInteger('views_count')->default(0)->after('clicks_count');
        });

        Schema::table('user_ratings', function (Blueprint $table): void {
            $table->text('review')->nullable()->after('rating');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_ratings', function (Blueprint $table): void {
            $table->dropColumn('review');
        });

        Schema::table('sponsored_entries', function (Blueprint $table): void {
            $table->dropColumn('views_count');
        });

        Schema::table('entities', function (Blueprint $table): void {
            $table->dropColumn('website_url');
        });
    }
};
