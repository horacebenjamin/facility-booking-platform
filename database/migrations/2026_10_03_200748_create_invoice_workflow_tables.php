<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_invoice_terms', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('centre_id')->constrained()->restrictOnDelete();
            $table->boolean('enabled');
            $table->unsignedSmallInteger('term_days');
            $table->foreignId('authorised_by')->constrained('users')->restrictOnDelete();
            $table->dateTime('authorised_at');
            $table->unique(['customer_id', 'centre_id']);
            $table->timestamps();
        });
        DB::statement('ALTER TABLE customer_invoice_terms ADD CONSTRAINT invoice_terms_days_valid CHECK (term_days BETWEEN 1 AND 365)');
        Schema::table('bookings', function (Blueprint $table): void {
            $table->string('billing_method', 16)->default('card');
            $table->unsignedSmallInteger('invoice_term_days')->nullable();
        });
        DB::statement('ALTER TABLE bookings DROP CHECK bookings_financial_status_supported');
        DB::statement("ALTER TABLE bookings ADD CONSTRAINT bookings_financial_status_supported CHECK (financial_status IN ('not_due', 'awaiting_payment', 'invoice_outstanding', 'invoiced', 'paid'))");
        DB::statement("ALTER TABLE bookings ADD CONSTRAINT bookings_billing_terms_valid CHECK ((billing_method = 'card' AND invoice_term_days IS NULL) OR (billing_method = 'invoice' AND invoice_term_days IS NOT NULL AND invoice_term_days BETWEEN 1 AND 365 AND payment_due_at IS NULL))");
        Schema::create('invoices', function (Blueprint $table): void {
            $table->id();
            $table->string('reference', 64)->unique();
            $table->foreignId('customer_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('issued_by')->constrained('users')->restrictOnDelete();
            $table->date('issue_date');
            $table->date('due_date');
            $table->string('status', 16)->default('issued');
            $table->char('currency', 3)->charset('ascii')->collation('ascii_bin');
            $table->unsignedBigInteger('total_minor');
            $table->dateTime('paid_at')->nullable();
            $table->index(['customer_id', 'issue_date']);
            $table->index(['status', 'due_date']);
            $table->timestamps();
        });
        DB::statement("ALTER TABLE invoices ADD CONSTRAINT invoices_valid CHECK (((status = 'issued' AND paid_at IS NULL) OR (status = 'paid' AND paid_at IS NOT NULL)) AND total_minor > 0 AND currency REGEXP '^[A-Z]{3}$' AND due_date >= issue_date)");
        Schema::create('invoice_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->restrictOnDelete();
            $table->foreignId('booking_id')->constrained()->restrictOnDelete();
            $table->string('charge_kind', 32)->default('booking_total');
            $table->text('description');
            $table->unsignedBigInteger('amount_minor');
            $table->unique(['booking_id', 'charge_kind']);
            $table->timestamps();
        });
        DB::statement('ALTER TABLE invoice_lines ADD CONSTRAINT invoice_lines_money_valid CHECK (amount_minor > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_lines');
        Schema::dropIfExists('invoices');
        DB::statement('ALTER TABLE bookings DROP CHECK bookings_billing_terms_valid');
        DB::statement('ALTER TABLE bookings DROP CHECK bookings_financial_status_supported');
        DB::statement("ALTER TABLE bookings ADD CONSTRAINT bookings_financial_status_supported CHECK (financial_status IN ('not_due', 'awaiting_payment', 'paid'))");
        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropColumn(['billing_method', 'invoice_term_days']);
        });
        Schema::dropIfExists('customer_invoice_terms');
    }
};
