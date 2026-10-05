<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('attendance_state', 32)->default('expected');
            $table->dateTime('arrived_at')->nullable();
            $table->dateTime('no_show_recorded_at')->nullable();
            $table->dateTime('completed_at')->nullable();
        });

        DB::statement("ALTER TABLE bookings ADD CONSTRAINT bookings_attendance_state_supported CHECK (attendance_state IN ('expected', 'arrived', 'no_show', 'completed'))");
        DB::statement("ALTER TABLE bookings ADD CONSTRAINT bookings_attendance_timestamp_shape CHECK ((attendance_state = 'expected' AND arrived_at IS NULL AND no_show_recorded_at IS NULL AND completed_at IS NULL) OR (attendance_state = 'arrived' AND arrived_at IS NOT NULL AND no_show_recorded_at IS NULL AND completed_at IS NULL) OR (attendance_state = 'no_show' AND arrived_at IS NULL AND no_show_recorded_at IS NOT NULL AND completed_at IS NULL) OR (attendance_state = 'completed' AND arrived_at IS NOT NULL AND no_show_recorded_at IS NULL AND completed_at IS NOT NULL AND completed_at >= arrived_at))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE bookings DROP CHECK bookings_attendance_timestamp_shape');
        DB::statement('ALTER TABLE bookings DROP CHECK bookings_attendance_state_supported');

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['attendance_state', 'arrived_at', 'no_show_recorded_at', 'completed_at']);
        });
    }
};
