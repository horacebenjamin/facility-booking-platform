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
        Schema::create('equipment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipment_id')->constrained(indexName: 'equip_alloc_equip_fk')->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->dateTime('expires_at')->nullable();
            $table->timestamps();

            $table->index(['equipment_id', 'starts_at', 'ends_at'], 'equip_alloc_period_idx');
        });

        DB::statement('ALTER TABLE equipment_allocations ADD CONSTRAINT equip_alloc_qty_positive CHECK (quantity > 0)');
        DB::statement('ALTER TABLE equipment_allocations ADD CONSTRAINT equip_alloc_valid_range CHECK (starts_at < ends_at)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('equipment_allocations');
    }
};
