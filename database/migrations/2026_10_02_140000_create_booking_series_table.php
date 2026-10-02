<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_series', function (Blueprint $table) {
            $table->id();
            $table->uuid('identifier')->unique();
            $table->foreignId('customer_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('centre_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('facility_id');
            $table->unsignedBigInteger('resource_id');
            $table->string('recurrence_frequency', 32);
            $table->unsignedTinyInteger('interval_weeks');
            $table->unsignedSmallInteger('occurrence_count');
            $table->string('timezone', 64);
            $table->dateTime('first_starts_at');
            $table->dateTime('first_ends_at');
            $table->timestamps();

            $table->foreign(['facility_id', 'centre_id'], 'booking_series_facility_centre_fk')
                ->references(['id', 'centre_id'])
                ->on('facilities')
                ->restrictOnDelete();
            $table->foreign(['resource_id', 'facility_id'], 'booking_series_resource_facility_fk')
                ->references(['id', 'facility_id'])
                ->on('resources')
                ->restrictOnDelete();
            $table->index(['customer_id', 'first_starts_at'], 'booking_series_customer_period_idx');
            $table->index(['resource_id', 'first_starts_at'], 'booking_series_resource_period_idx');
            $table->unique(
                ['id', 'customer_id', 'centre_id', 'facility_id', 'resource_id'],
                'booking_series_occurrence_context_unique',
            );
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->unsignedBigInteger('booking_series_id')->nullable()->after('id');
            $table->unsignedSmallInteger('occurrence_index')->nullable()->after('booking_series_id');
            $table->unique(['booking_series_id', 'occurrence_index'], 'bookings_series_occurrence_unique');
            $table->index(['booking_series_id', 'starts_at'], 'bookings_series_period_idx');
            $table->foreign(
                ['booking_series_id', 'customer_id', 'centre_id', 'facility_id', 'resource_id'],
                'bookings_series_context_fk',
            )->references(['id', 'customer_id', 'centre_id', 'facility_id', 'resource_id'])
                ->on('booking_series')
                ->restrictOnDelete();
        });

        DB::statement("ALTER TABLE booking_series ADD CONSTRAINT booking_series_frequency_supported CHECK (recurrence_frequency = 'weekly')");
        DB::statement('ALTER TABLE booking_series ADD CONSTRAINT booking_series_pattern_valid CHECK (interval_weeks > 0 AND occurrence_count BETWEEN 2 AND 104 AND first_starts_at < first_ends_at)');
        DB::statement('ALTER TABLE bookings ADD CONSTRAINT bookings_series_occurrence_complete CHECK ((booking_series_id IS NULL AND occurrence_index IS NULL) OR (booking_series_id IS NOT NULL AND occurrence_index > 0))');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE bookings DROP CHECK bookings_series_occurrence_complete');
        DB::statement('ALTER TABLE booking_series DROP CHECK booking_series_pattern_valid');
        DB::statement('ALTER TABLE booking_series DROP CHECK booking_series_frequency_supported');

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropForeign('bookings_series_context_fk');
            $table->dropUnique('bookings_series_occurrence_unique');
            $table->dropIndex('bookings_series_period_idx');
            $table->dropColumn(['booking_series_id', 'occurrence_index']);
        });

        Schema::dropIfExists('booking_series');
    }
};
