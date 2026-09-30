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
        Schema::table('resources', function (Blueprint $table) {
            $table->unsignedSmallInteger('setup_minutes')->default(0);
            $table->unsignedSmallInteger('cleanup_minutes')->default(0);
        });

        Schema::create('centre_operating_hours', function (Blueprint $table) {
            $table->id();
            $table->foreignId('centre_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('day_of_week');
            $table->time('opens_at');
            $table->time('closes_at');
            $table->timestamps();

            $table->unique(['centre_id', 'day_of_week']);
        });

        Schema::create('facility_bookable_hours', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facility_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('day_of_week');
            $table->time('opens_at');
            $table->time('closes_at');
            $table->timestamps();

            $table->unique(['facility_id', 'day_of_week']);
        });

        Schema::create('resource_bookable_hours', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resource_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('day_of_week');
            $table->time('opens_at');
            $table->time('closes_at');
            $table->timestamps();

            $table->unique(['resource_id', 'day_of_week']);
        });

        DB::statement('ALTER TABLE resources ADD CONSTRAINT resources_setup_minutes_not_negative CHECK (setup_minutes >= 0)');
        DB::statement('ALTER TABLE resources ADD CONSTRAINT resources_cleanup_minutes_not_negative CHECK (cleanup_minutes >= 0)');
        DB::statement('ALTER TABLE centre_operating_hours ADD CONSTRAINT centre_operating_hours_valid_range CHECK (day_of_week BETWEEN 1 AND 7 AND opens_at < closes_at)');
        DB::statement('ALTER TABLE facility_bookable_hours ADD CONSTRAINT facility_bookable_hours_valid_range CHECK (day_of_week BETWEEN 1 AND 7 AND opens_at < closes_at)');
        DB::statement('ALTER TABLE resource_bookable_hours ADD CONSTRAINT resource_bookable_hours_valid_range CHECK (day_of_week BETWEEN 1 AND 7 AND opens_at < closes_at)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('resource_bookable_hours');
        Schema::dropIfExists('facility_bookable_hours');
        Schema::dropIfExists('centre_operating_hours');

        DB::statement('ALTER TABLE resources DROP CHECK resources_setup_minutes_not_negative');
        DB::statement('ALTER TABLE resources DROP CHECK resources_cleanup_minutes_not_negative');

        Schema::table('resources', function (Blueprint $table) {
            $table->dropColumn(['setup_minutes', 'cleanup_minutes']);
        });
    }
};
