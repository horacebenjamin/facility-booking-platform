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
        Schema::create('allocation_unit_resource', function (Blueprint $table) {
            $table->unsignedBigInteger('resource_id');
            $table->unsignedBigInteger('allocation_unit_id');
            $table->unsignedBigInteger('facility_id');

            $table->unique(['resource_id', 'allocation_unit_id']);
            $table->foreign(['resource_id', 'facility_id'])
                ->references(['id', 'facility_id'])
                ->on('resources')
                ->restrictOnDelete();
            $table->foreign(['allocation_unit_id', 'facility_id'])
                ->references(['id', 'facility_id'])
                ->on('allocation_units')
                ->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('allocation_unit_resource');
    }
};
