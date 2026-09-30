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
        Schema::create('allocation_occupancies', function (Blueprint $table) {
            $table->id();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->dateTime('expires_at')->nullable();
            $table->timestamps();

            $table->index(['starts_at', 'ends_at']);
        });

        Schema::create('allocation_occupancy_allocation_unit', function (Blueprint $table) {
            $table->foreignId('allocation_occupancy_id')->constrained(indexName: 'alloc_occ_unit_occ_fk')->restrictOnDelete();
            $table->foreignId('allocation_unit_id')->constrained(indexName: 'alloc_occ_unit_unit_fk')->restrictOnDelete();

            $table->unique(['allocation_occupancy_id', 'allocation_unit_id'], 'alloc_occ_unit_unique');
            $table->index(['allocation_unit_id', 'allocation_occupancy_id'], 'alloc_occ_unit_lookup_idx');
        });

        DB::statement('ALTER TABLE allocation_occupancies ADD CONSTRAINT allocation_occupancies_valid_range CHECK (starts_at < ends_at)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('allocation_occupancy_allocation_unit');

        Schema::dropIfExists('allocation_occupancies');
    }
};
