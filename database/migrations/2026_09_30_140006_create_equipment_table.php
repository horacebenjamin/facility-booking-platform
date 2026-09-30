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
        Schema::create('equipment', function (Blueprint $table) {
            $table->id();
            $table->foreignId('centre_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('facility_id')->nullable();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedInteger('quantity');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign(['facility_id', 'centre_id'])
                ->references(['id', 'centre_id'])
                ->on('facilities')
                ->restrictOnDelete();
        });

        DB::statement('ALTER TABLE equipment ADD CONSTRAINT equipment_quantity_not_negative CHECK (quantity >= 0)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('equipment');
    }
};
