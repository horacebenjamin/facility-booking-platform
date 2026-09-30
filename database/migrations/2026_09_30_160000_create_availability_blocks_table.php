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
        Schema::create('availability_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('centre_id')->nullable()->constrained(indexName: 'avail_blocks_centre_fk')->restrictOnDelete();
            $table->foreignId('facility_id')->nullable()->constrained(indexName: 'avail_blocks_facility_fk')->restrictOnDelete();
            $table->foreignId('resource_id')->nullable()->constrained(indexName: 'avail_blocks_resource_fk')->restrictOnDelete();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('reason')->nullable();
            $table->timestamps();

            $table->index(['centre_id', 'starts_at', 'ends_at'], 'avail_blocks_centre_period_idx');
            $table->index(['facility_id', 'starts_at', 'ends_at'], 'avail_blocks_facility_period_idx');
            $table->index(['resource_id', 'starts_at', 'ends_at'], 'avail_blocks_resource_period_idx');
        });

        DB::statement('ALTER TABLE availability_blocks ADD CONSTRAINT availability_blocks_exactly_one_scope CHECK ((centre_id IS NOT NULL) + (facility_id IS NOT NULL) + (resource_id IS NOT NULL) = 1)');
        DB::statement('ALTER TABLE availability_blocks ADD CONSTRAINT availability_blocks_valid_range CHECK (starts_at < ends_at)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('availability_blocks');
    }
};
