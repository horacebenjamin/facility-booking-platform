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
        Schema::create('resource_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resource_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3)->charset('ascii')->collation('ascii_bin');
            $table->string('rate_unit', 32);
            $table->date('effective_from');
            $table->date('effective_until')->nullable();
            $table->timestamps();

            $table->index(['resource_id', 'effective_from', 'effective_until'], 'resource_rates_effective_lookup');
        });

        Schema::create('equipment_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipment_id')->constrained()->restrictOnDelete();
            $table->string('charge_type', 32);
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3)->charset('ascii')->collation('ascii_bin');
            $table->string('rate_unit', 32);
            $table->date('effective_from');
            $table->date('effective_until')->nullable();
            $table->timestamps();

            $table->index(['equipment_id', 'effective_from', 'effective_until'], 'equipment_rates_effective_lookup');
        });

        DB::statement('ALTER TABLE resource_rates ADD CONSTRAINT resource_rates_amount_positive CHECK (amount_minor > 0)');
        DB::statement("ALTER TABLE resource_rates ADD CONSTRAINT resource_rates_currency_format CHECK (currency REGEXP '^[A-Z]{3}$')");
        DB::statement("ALTER TABLE resource_rates ADD CONSTRAINT resource_rates_rate_unit_supported CHECK (rate_unit = 'hour')");
        DB::statement('ALTER TABLE resource_rates ADD CONSTRAINT resource_rates_effective_period_valid CHECK (effective_until IS NULL OR effective_until >= effective_from)');
        DB::statement("ALTER TABLE equipment_rates ADD CONSTRAINT equipment_rates_charge_amount_valid CHECK ((charge_type = 'included' AND amount_minor = 0) OR (charge_type = 'separately_chargeable' AND amount_minor > 0))");
        DB::statement("ALTER TABLE equipment_rates ADD CONSTRAINT equipment_rates_currency_format CHECK (currency REGEXP '^[A-Z]{3}$')");
        DB::statement("ALTER TABLE equipment_rates ADD CONSTRAINT equipment_rates_rate_unit_supported CHECK (rate_unit = 'hour')");
        DB::statement('ALTER TABLE equipment_rates ADD CONSTRAINT equipment_rates_effective_period_valid CHECK (effective_until IS NULL OR effective_until >= effective_from)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('equipment_rates');
        Schema::dropIfExists('resource_rates');
    }
};
