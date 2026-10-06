<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->foreignId('organisation_id')->nullable()->after('customer_id')->constrained()->restrictOnDelete();
            $table->index(['organisation_id', 'status', 'starts_at'], 'bookings_organisation_status_period_idx');
        });

        Schema::table('booking_series', function (Blueprint $table): void {
            $table->foreignId('organisation_id')->nullable()->after('customer_id')->constrained()->restrictOnDelete();
            $table->index(['organisation_id', 'first_starts_at'], 'booking_series_organisation_period_idx');
        });

        Schema::table('customer_invoice_terms', function (Blueprint $table): void {
            $table->foreignId('customer_id')->nullable()->change();
            $table->foreignId('organisation_id')->nullable()->after('customer_id')->constrained()->restrictOnDelete();
            $table->unique(['organisation_id', 'centre_id'], 'invoice_terms_organisation_centre_unique');
        });

        DB::statement('ALTER TABLE customer_invoice_terms ADD CONSTRAINT customer_invoice_terms_owner_valid CHECK ((customer_id IS NOT NULL AND organisation_id IS NULL) OR (customer_id IS NULL AND organisation_id IS NOT NULL))');

        Schema::table('invoices', function (Blueprint $table): void {
            $table->foreignId('organisation_id')->nullable()->after('customer_id')->constrained()->restrictOnDelete();
            $table->index(['organisation_id', 'issue_date'], 'invoices_organisation_issue_date_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropIndex('invoices_organisation_issue_date_idx');
            $table->dropConstrainedForeignId('organisation_id');
        });

        DB::statement('ALTER TABLE customer_invoice_terms DROP CHECK customer_invoice_terms_owner_valid');

        Schema::table('customer_invoice_terms', function (Blueprint $table): void {
            $table->dropUnique('invoice_terms_organisation_centre_unique');
            $table->dropConstrainedForeignId('organisation_id');
            $table->foreignId('customer_id')->nullable(false)->change();
        });

        Schema::table('booking_series', function (Blueprint $table): void {
            $table->dropIndex('booking_series_organisation_period_idx');
            $table->dropConstrainedForeignId('organisation_id');
        });

        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropIndex('bookings_organisation_status_period_idx');
            $table->dropConstrainedForeignId('organisation_id');
        });
    }
};
