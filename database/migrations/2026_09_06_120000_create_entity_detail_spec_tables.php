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
        Schema::create('smartphone_specs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('entity_id')->unique()->constrained('entities')->cascadeOnDelete();
            $table->string('chipset')->nullable();
            $table->string('ram')->nullable();
            $table->string('storage')->nullable();
            $table->decimal('screen_size_inch', 4, 2)->nullable();
            $table->string('screen_type')->nullable();
            $table->string('rear_camera')->nullable();
            $table->string('front_camera')->nullable();
            $table->unsignedInteger('battery_mah')->nullable();
            $table->unsignedInteger('fast_charging_watt')->nullable();
            $table->string('os')->nullable();
            $table->string('network')->nullable();
            $table->unsignedSmallInteger('release_year')->nullable();
            $table->timestamps();
        });

        Schema::create('car_specs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('entity_id')->unique()->constrained('entities')->cascadeOnDelete();
            $table->string('body_type')->nullable();
            $table->unsignedInteger('engine_cc')->nullable();
            $table->unsignedTinyInteger('cylinder_count')->nullable();
            $table->string('fuel_type')->nullable();
            $table->decimal('power_hp', 6, 1)->nullable();
            $table->decimal('torque_nm', 6, 1)->nullable();
            $table->string('transmission')->nullable();
            $table->string('drivetrain')->nullable();
            $table->unsignedSmallInteger('fuel_tank_liter')->nullable();
            $table->unsignedTinyInteger('seating_capacity')->nullable();
            $table->string('dimensions_mm')->nullable();
            $table->unsignedSmallInteger('release_year')->nullable();
            $table->timestamps();
        });

        Schema::create('motorcycle_specs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('entity_id')->unique()->constrained('entities')->cascadeOnDelete();
            $table->string('body_type')->nullable();
            $table->unsignedInteger('engine_cc')->nullable();
            $table->string('cooling_system')->nullable();
            $table->string('fuel_type')->nullable();
            $table->decimal('power_hp', 6, 1)->nullable();
            $table->decimal('torque_nm', 6, 1)->nullable();
            $table->string('transmission')->nullable();
            $table->decimal('fuel_tank_liter', 4, 1)->nullable();
            $table->unsignedSmallInteger('weight_kg')->nullable();
            $table->string('braking_system')->nullable();
            $table->unsignedSmallInteger('release_year')->nullable();
            $table->timestamps();
        });

        Schema::create('person_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('entity_id')->unique()->constrained('entities')->cascadeOnDelete();
            $table->date('birth_date')->nullable();
            $table->string('birth_place')->nullable();
            $table->string('occupation')->nullable();
            $table->string('affiliation')->nullable();
            $table->unsignedSmallInteger('active_since_year')->nullable();
            $table->string('official_website')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('person_profiles');
        Schema::dropIfExists('motorcycle_specs');
        Schema::dropIfExists('car_specs');
        Schema::dropIfExists('smartphone_specs');
    }
};
