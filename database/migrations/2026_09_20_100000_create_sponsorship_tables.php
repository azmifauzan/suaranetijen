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
        Schema::create('sponsor_periods', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->timestamp('starts_at')->nullable()->index();
            $table->timestamp('ends_at')->nullable()->index();
            $table->string('status')->default('active')->index();
            $table->timestamps();
        });

        Schema::create('sponsored_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('period_id')->constrained('sponsor_periods')->cascadeOnDelete();
            $table->foreignId('entity_id')->constrained('entities')->cascadeOnDelete();
            $table->unsignedBigInteger('settled_total_amount')->default(0)->index();
            $table->timestamp('first_settled_at')->nullable()->index();
            $table->string('status')->default('pending')->index();
            $table->unsignedInteger('clicks_count')->default(0);
            $table->timestamps();

            $table->unique(['period_id', 'entity_id']);
            $table->index(['period_id', 'status', 'settled_total_amount']);
        });

        Schema::create('sponsorship_orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sponsored_entry_id')->constrained('sponsored_entries')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('provider')->default('sumopod');
            $table->string('provider_order_id')->unique();
            $table->string('provider_payment_id')->nullable()->index();
            $table->text('payment_link_url')->nullable();
            $table->unsignedBigInteger('amount');
            $table->string('status')->default('pending')->index();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('paid_at')->nullable()->index();
            $table->json('audit_payload')->nullable();
            $table->timestamps();

            $table->index(['sponsored_entry_id', 'status']);
            $table->index(['user_id', 'status']);
        });

        Schema::create('sponsorship_relay_events', function (Blueprint $table): void {
            $table->id();
            $table->string('svix_id')->unique();
            $table->string('event_type')->index();
            $table->string('environment')->nullable();
            $table->json('payload');
            $table->string('status')->default('received')->index();
            $table->text('last_error')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sponsorship_relay_events');
        Schema::dropIfExists('sponsorship_orders');
        Schema::dropIfExists('sponsored_entries');
        Schema::dropIfExists('sponsor_periods');
    }
};
