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
        Schema::table('equipment', function (Blueprint $table) {
            $table->unique(['id', 'centre_id']);
        });

        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 32)->unique();
            $table->foreignId('customer_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('centre_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('facility_id');
            $table->unsignedBigInteger('resource_id');
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('status', 32)->default('requested');
            $table->string('financial_status', 32)->default('not_due');
            $table->timestamps();

            $table->foreign(['facility_id', 'centre_id'], 'bookings_facility_centre_fk')
                ->references(['id', 'centre_id'])
                ->on('facilities')
                ->restrictOnDelete();
            $table->foreign(['resource_id', 'facility_id'], 'bookings_resource_facility_fk')
                ->references(['id', 'facility_id'])
                ->on('resources')
                ->restrictOnDelete();
            $table->index(['customer_id', 'status', 'starts_at'], 'bookings_customer_status_period_idx');
            $table->index(['resource_id', 'starts_at', 'ends_at'], 'bookings_resource_period_idx');
            $table->unique(['id', 'centre_id']);
        });

        Schema::create('booking_equipment', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('centre_id');
            $table->foreignId('equipment_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('requested_quantity');
            $table->timestamps();

            $table->unique(['booking_id', 'equipment_id']);
            $table->unique(['id', 'booking_id']);
            $table->foreign(['booking_id', 'centre_id'], 'booking_equipment_booking_centre_fk')
                ->references(['id', 'centre_id'])
                ->on('bookings')
                ->restrictOnDelete();
            $table->foreign(['equipment_id', 'centre_id'], 'booking_equipment_equipment_centre_fk')
                ->references(['id', 'centre_id'])
                ->on('equipment')
                ->restrictOnDelete();
        });

        Schema::create('booking_price_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->restrictOnDelete();
            $table->char('currency', 3)->charset('ascii')->collation('ascii_bin');
            $table->unsignedBigInteger('resource_amount_minor');
            $table->unsignedBigInteger('equipment_amount_minor');
            $table->unsignedBigInteger('subtotal_minor');
            $table->unsignedBigInteger('discount_requested_amount_minor')->nullable();
            $table->unsignedBigInteger('discount_amount_minor')->default(0);
            $table->string('discount_description')->nullable();
            $table->unsignedBigInteger('calculated_total_minor');
            $table->unsignedBigInteger('final_total_minor');
            $table->unsignedBigInteger('override_original_total_minor')->nullable();
            $table->unsignedBigInteger('override_adjusted_total_minor')->nullable();
            $table->text('override_reason')->nullable();
            $table->foreignId('override_responsible_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('override_responsible_user_name')->nullable();
            $table->dateTime('override_adjusted_at')->nullable();
            $table->timestamps();

            $table->unique('booking_id');
            $table->unique(['id', 'booking_id']);
        });

        Schema::create('booking_price_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_price_snapshot_id')->constrained()->restrictOnDelete();
            $table->foreignId('booking_id')->constrained()->restrictOnDelete();
            $table->foreignId('booking_equipment_id')->nullable()->constrained('booking_equipment')->restrictOnDelete();
            $table->string('line_type', 32);
            $table->string('description');
            $table->unsignedInteger('quantity');
            $table->string('charge_type', 32)->nullable();
            $table->unsignedBigInteger('hourly_rate_minor');
            $table->string('rate_unit', 32);
            $table->date('effective_from');
            $table->date('effective_until')->nullable();
            $table->unsignedBigInteger('duration_seconds');
            $table->unsignedBigInteger('amount_minor');
            $table->timestamps();

            $table->unique('booking_equipment_id');
            $table->foreign(['booking_price_snapshot_id', 'booking_id'], 'booking_price_lines_snapshot_booking_fk')
                ->references(['id', 'booking_id'])
                ->on('booking_price_snapshots')
                ->restrictOnDelete();
            $table->foreign(['booking_equipment_id', 'booking_id'], 'booking_price_lines_equipment_booking_fk')
                ->references(['id', 'booking_id'])
                ->on('booking_equipment')
                ->restrictOnDelete();
        });

        DB::statement('ALTER TABLE bookings ADD CONSTRAINT bookings_valid_range CHECK (starts_at < ends_at)');
        DB::statement("ALTER TABLE bookings ADD CONSTRAINT bookings_status_supported CHECK (status = 'requested')");
        DB::statement("ALTER TABLE bookings ADD CONSTRAINT bookings_financial_status_supported CHECK (financial_status = 'not_due')");
        DB::statement('ALTER TABLE booking_equipment ADD CONSTRAINT booking_equipment_quantity_positive CHECK (requested_quantity > 0)');
        DB::statement("ALTER TABLE booking_price_snapshots ADD CONSTRAINT booking_price_snapshots_currency_format CHECK (currency REGEXP '^[A-Z]{3}$')");
        DB::statement('ALTER TABLE booking_price_snapshots ADD CONSTRAINT booking_price_snapshots_totals_reconcile CHECK (subtotal_minor = resource_amount_minor + equipment_amount_minor AND calculated_total_minor = subtotal_minor - discount_amount_minor AND final_total_minor = COALESCE(override_adjusted_total_minor, calculated_total_minor))');
        DB::statement('ALTER TABLE booking_price_snapshots ADD CONSTRAINT booking_price_snapshots_discount_valid CHECK ((discount_requested_amount_minor IS NULL AND discount_amount_minor = 0 AND discount_description IS NULL) OR (discount_requested_amount_minor IS NOT NULL AND discount_amount_minor <= discount_requested_amount_minor AND discount_amount_minor <= subtotal_minor AND discount_description IS NOT NULL))');
        DB::statement('ALTER TABLE booking_price_snapshots ADD CONSTRAINT booking_price_snapshots_override_complete CHECK ((override_original_total_minor IS NULL AND override_adjusted_total_minor IS NULL AND override_reason IS NULL AND override_responsible_user_id IS NULL AND override_responsible_user_name IS NULL AND override_adjusted_at IS NULL) OR (override_original_total_minor = calculated_total_minor AND override_adjusted_total_minor = final_total_minor AND override_reason IS NOT NULL AND override_responsible_user_id IS NOT NULL AND override_responsible_user_name IS NOT NULL AND override_adjusted_at IS NOT NULL))');
        DB::statement("ALTER TABLE booking_price_lines ADD CONSTRAINT booking_price_lines_type_supported CHECK (line_type IN ('resource', 'equipment'))");
        DB::statement("ALTER TABLE booking_price_lines ADD CONSTRAINT booking_price_lines_charge_type_supported CHECK (charge_type IS NULL OR charge_type IN ('included', 'separately_chargeable'))");
        DB::statement("ALTER TABLE booking_price_lines ADD CONSTRAINT booking_price_lines_rate_unit_supported CHECK (rate_unit = 'hour')");
        DB::statement('ALTER TABLE booking_price_lines ADD CONSTRAINT booking_price_lines_values_valid CHECK (quantity > 0 AND duration_seconds > 0 AND amount_minor >= 0 AND hourly_rate_minor >= 0 AND (effective_until IS NULL OR effective_until >= effective_from))');
        DB::statement("ALTER TABLE booking_price_lines ADD CONSTRAINT booking_price_lines_resource_shape CHECK ((line_type = 'resource' AND booking_equipment_id IS NULL AND quantity = 1 AND charge_type IS NULL) OR (line_type = 'equipment' AND booking_equipment_id IS NOT NULL AND charge_type IS NOT NULL))");
        DB::statement("ALTER TABLE booking_price_lines ADD CONSTRAINT booking_price_lines_amounts_match_charge_type CHECK ((line_type = 'resource' AND hourly_rate_minor > 0 AND amount_minor > 0) OR (charge_type = 'included' AND hourly_rate_minor = 0 AND amount_minor = 0) OR (charge_type = 'separately_chargeable' AND hourly_rate_minor > 0 AND amount_minor > 0))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('booking_price_lines');
        Schema::dropIfExists('booking_price_snapshots');
        Schema::dropIfExists('booking_equipment');
        Schema::dropIfExists('bookings');

        Schema::table('equipment', function (Blueprint $table) {
            $table->dropUnique(['id', 'centre_id']);
        });
    }
};
