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
        Schema::table('allocation_occupancies', function (Blueprint $table) {
            $table->foreignId('booking_id')->nullable()->after('id')->constrained()->restrictOnDelete();
            $table->unique('booking_id');
        });

        Schema::table('equipment_allocations', function (Blueprint $table) {
            $table->foreignId('booking_id')->nullable()->after('id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('booking_equipment_id')->nullable()->after('booking_id');
            $table->unique('booking_equipment_id');
            $table->foreign(['booking_equipment_id', 'booking_id'], 'equipment_allocations_booking_equipment_booking_fk')
                ->references(['id', 'booking_id'])
                ->on('booking_equipment')
                ->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('equipment_allocations', function (Blueprint $table) {
            $table->dropForeign('equipment_allocations_booking_equipment_booking_fk');
            $table->dropColumn('booking_equipment_id');
            $table->dropConstrainedForeignId('booking_id');
        });

        Schema::table('allocation_occupancies', function (Blueprint $table) {
            $table->dropConstrainedForeignId('booking_id');
        });
    }
};
