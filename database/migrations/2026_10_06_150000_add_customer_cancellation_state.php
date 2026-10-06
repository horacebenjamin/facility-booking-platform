<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE bookings DROP CHECK bookings_status_supported');
        DB::statement("ALTER TABLE bookings ADD CONSTRAINT bookings_status_supported CHECK (status IN ('requested', 'approved', 'confirmed', 'rejected', 'cancelled'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE bookings DROP CHECK bookings_status_supported');
        DB::statement("ALTER TABLE bookings ADD CONSTRAINT bookings_status_supported CHECK (status IN ('requested', 'approved', 'confirmed', 'rejected'))");
    }
};
