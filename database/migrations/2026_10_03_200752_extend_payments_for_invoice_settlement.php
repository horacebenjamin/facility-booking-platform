<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->unsignedBigInteger('booking_id')->nullable()->change();
            $table->dateTime('session_expires_at')->nullable()->change();
            $table->foreignId('invoice_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('external_reference')->nullable();
            $table->text('recording_note')->nullable();
            $table->index(['invoice_id', 'status']);
        });
        DB::statement('ALTER TABLE payments ADD CONSTRAINT payments_obligation_valid CHECK ((booking_id IS NOT NULL AND invoice_id IS NULL) OR (booking_id IS NULL AND invoice_id IS NOT NULL))');
        DB::statement("ALTER TABLE payments ADD CONSTRAINT payments_provider_context_valid CHECK ((provider = 'stripe' AND booking_id IS NOT NULL AND session_expires_at IS NOT NULL AND recorded_by IS NULL) OR (provider = 'manual' AND invoice_id IS NOT NULL AND recorded_by IS NOT NULL AND external_reference IS NOT NULL AND recording_note IS NOT NULL AND status = 'succeeded' AND succeeded_at IS NOT NULL AND provider_session_id IS NULL AND provider_payment_intent_id IS NULL AND checkout_url IS NULL AND session_expires_at IS NULL AND live_mode = 0))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE payments DROP CHECK payments_provider_context_valid');
        DB::statement('ALTER TABLE payments DROP CHECK payments_obligation_valid');
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropForeign(['invoice_id']);
            $table->dropForeign(['recorded_by']);
            $table->dropIndex(['invoice_id', 'status']);
            $table->dropColumn(['invoice_id', 'recorded_by', 'external_reference', 'recording_note']);
            $table->unsignedBigInteger('booking_id')->nullable(false)->change();
            $table->dateTime('session_expires_at')->nullable(false)->change();
        });
    }
};
