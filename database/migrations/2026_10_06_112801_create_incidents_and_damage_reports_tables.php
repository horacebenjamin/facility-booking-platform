<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incidents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('centre_id')->constrained()->restrictOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('resource_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('reported_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('closed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('issue_type', 64);
            $table->string('title');
            $table->text('description');
            $table->text('immediate_action')->nullable();
            $table->dateTime('occurred_at');
            $table->string('status', 32)->default('open');
            $table->text('follow_up_notes')->nullable();
            $table->dateTime('reviewed_at')->nullable();
            $table->dateTime('resolved_at')->nullable();
            $table->dateTime('closed_at')->nullable();
            $table->timestamps();

            $table->foreign(['booking_id', 'centre_id'], 'incidents_booking_centre_fk')
                ->references(['id', 'centre_id'])
                ->on('bookings')
                ->restrictOnDelete();
            $table->index(['centre_id', 'status', 'occurred_at']);
            $table->index(['resource_id', 'occurred_at']);
        });

        Schema::create('damage_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('centre_id')->constrained()->restrictOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('resource_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('equipment_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('reported_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('closed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('description');
            $table->dateTime('observed_at');
            $table->string('status', 32)->default('open');
            $table->string('responsibility', 32)->default('undetermined');
            $table->string('financial_follow_up', 32)->default('not_required');
            $table->text('follow_up_notes')->nullable();
            $table->dateTime('reviewed_at')->nullable();
            $table->dateTime('resolved_at')->nullable();
            $table->dateTime('closed_at')->nullable();
            $table->timestamps();

            $table->foreign(['booking_id', 'centre_id'], 'damage_reports_booking_centre_fk')
                ->references(['id', 'centre_id'])
                ->on('bookings')
                ->restrictOnDelete();
            $table->foreign(['equipment_id', 'centre_id'], 'damage_reports_equipment_centre_fk')
                ->references(['id', 'centre_id'])
                ->on('equipment')
                ->restrictOnDelete();
            $table->index(['centre_id', 'status', 'observed_at']);
            $table->index(['resource_id', 'observed_at']);
            $table->index(['equipment_id', 'observed_at']);
        });

        DB::statement("ALTER TABLE incidents ADD CONSTRAINT incidents_status_supported CHECK (status IN ('open', 'reviewed', 'resolved', 'closed'))");
        DB::statement("ALTER TABLE damage_reports ADD CONSTRAINT damage_reports_status_supported CHECK (status IN ('open', 'reviewed', 'resolved', 'closed'))");
        DB::statement("ALTER TABLE damage_reports ADD CONSTRAINT damage_reports_responsibility_supported CHECK (responsibility IN ('undetermined', 'customer', 'centre', 'third_party', 'no_fault'))");
        DB::statement("ALTER TABLE damage_reports ADD CONSTRAINT damage_reports_financial_follow_up_supported CHECK (financial_follow_up IN ('not_required', 'review_required', 'decision_recorded'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('damage_reports');
        Schema::dropIfExists('incidents');
    }
};
