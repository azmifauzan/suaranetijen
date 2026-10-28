<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('car_specs', function (Blueprint $table) {
            $table->decimal('battery_kwh', 5, 1)->nullable()->after('fuel_tank_liter');
            $table->unsignedSmallInteger('range_km')->nullable()->after('battery_kwh');
        });
    }

    public function down(): void
    {
        Schema::table('car_specs', function (Blueprint $table) {
            $table->dropColumn(['battery_kwh', 'range_km']);
        });
    }
};
