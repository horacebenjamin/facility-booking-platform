<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->dateTime('payment_due_at')->nullable();
        });
        DB::statement('ALTER TABLE bookings DROP CHECK bookings_status_supported');
        DB::statement("ALTER TABLE bookings ADD CONSTRAINT bookings_status_supported CHECK (status IN ('requested', 'approved', 'confirmed', 'rejected'))");
        DB::statement('ALTER TABLE bookings DROP CHECK bookings_financial_status_supported');
        DB::statement("ALTER TABLE bookings ADD CONSTRAINT bookings_financial_status_supported CHECK (financial_status IN ('not_due', 'awaiting_payment', 'paid'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE bookings DROP CHECK bookings_status_supported');
        DB::statement("ALTER TABLE bookings ADD CONSTRAINT bookings_status_supported CHECK (status IN ('requested', 'approved', 'rejected'))");
        DB::statement('ALTER TABLE bookings DROP CHECK bookings_financial_status_supported');
        DB::statement("ALTER TABLE bookings ADD CONSTRAINT bookings_financial_status_supported CHECK (financial_status IN ('not_due', 'awaiting_payment'))");
        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropColumn('payment_due_at');
        });
    }
};
