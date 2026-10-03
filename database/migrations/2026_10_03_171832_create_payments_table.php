<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('reference')->unique();
            $table->foreignId('booking_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained('users')->restrictOnDelete();
            $table->string('provider', 32);
            $table->string('provider_session_id')->nullable()->collation('utf8mb4_bin');
            $table->string('provider_payment_intent_id')->nullable()->collation('utf8mb4_bin');
            $table->text('checkout_url')->nullable();
            $table->json('checkout_parameters');
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3)->charset('ascii')->collation('ascii_bin');
            $table->string('status', 32)->default('pending');
            $table->boolean('live_mode');
            $table->dateTime('session_expires_at');
            $table->dateTime('succeeded_at')->nullable();
            $table->string('reconciliation_issue')->nullable();
            $table->unique(['provider', 'provider_session_id']);
            $table->unique(['provider', 'provider_payment_intent_id']);
            $table->index(['booking_id', 'status']);
            $table->timestamps();
        });
        DB::statement("ALTER TABLE payments ADD CONSTRAINT payments_status_supported CHECK (status IN ('pending', 'processing', 'succeeded', 'failed', 'expired'))");
        DB::statement("ALTER TABLE payments ADD CONSTRAINT payments_money_valid CHECK (amount_minor > 0 AND currency REGEXP '^[A-Z]{3}$')");
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
