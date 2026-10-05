<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('availability_block_booking_impacts', function (Blueprint $table) {
            $table->string('operational_requirement', 40)->default('review_required');
            $table->string('financial_requirement', 40)->default('review_required');
            $table->boolean('communication_required')->default(true);
            $table->dateTime('communicated_at')->nullable();
            $table->foreignId('communicated_by')->nullable()->constrained('users', indexName: 'closure_impacts_communicated_by_fk')->restrictOnDelete();
            $table->text('communication_notes')->nullable();
            $table->text('review_notes')->nullable();
            $table->text('resolution_notes')->nullable();
        });
        DB::statement("ALTER TABLE availability_block_booking_impacts ADD CONSTRAINT closure_impacts_operational_supported CHECK (operational_requirement IN ('review_required', 'no_action_required', 'reschedule_required', 'alternative_resource_required', 'alternative_centre_required', 'cancellation_required'))");
        DB::statement("ALTER TABLE availability_block_booking_impacts ADD CONSTRAINT closure_impacts_financial_supported CHECK (financial_requirement IN ('review_required', 'not_required', 'refund_required', 'credit_required', 'invoice_adjustment_required'))");
        DB::statement('ALTER TABLE availability_block_booking_impacts ADD CONSTRAINT closure_impacts_communication_pair CHECK ((communicated_at IS NULL AND communicated_by IS NULL) OR (communicated_at IS NOT NULL AND communicated_by IS NOT NULL))');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE availability_block_booking_impacts DROP CHECK closure_impacts_operational_supported');
        DB::statement('ALTER TABLE availability_block_booking_impacts DROP CHECK closure_impacts_financial_supported');
        DB::statement('ALTER TABLE availability_block_booking_impacts DROP CHECK closure_impacts_communication_pair');
        Schema::table('availability_block_booking_impacts', function (Blueprint $table) {
            $table->dropForeign('closure_impacts_communicated_by_fk');
            $table->dropColumn(['operational_requirement', 'financial_requirement', 'communication_required', 'communicated_at', 'communicated_by', 'communication_notes', 'review_notes', 'resolution_notes']);
        });
    }
};
