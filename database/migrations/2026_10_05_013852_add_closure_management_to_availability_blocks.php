<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('availability_blocks', function (Blueprint $table) {
            $table->string('type', 32)->default('other');
            $table->foreignId('created_by')->nullable()->constrained('users', indexName: 'avail_blocks_created_by_fk')->restrictOnDelete();
            $table->dateTime('ended_at')->nullable();
            $table->foreignId('ended_by')->nullable()->constrained('users', indexName: 'avail_blocks_ended_by_fk')->restrictOnDelete();
        });
        DB::statement("ALTER TABLE availability_blocks ADD CONSTRAINT avail_blocks_type_supported CHECK (type IN ('school_use', 'exam', 'maintenance', 'health_and_safety', 'weather', 'internal_use', 'manager_blockout', 'other'))");
        DB::statement('ALTER TABLE availability_blocks ADD CONSTRAINT avail_blocks_end_actor_pair CHECK ((ended_at IS NULL AND ended_by IS NULL) OR (ended_at IS NOT NULL AND ended_by IS NOT NULL))');

        Schema::create('availability_block_booking_impacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('availability_block_id')->constrained('availability_blocks', indexName: 'closure_impacts_block_fk')->restrictOnDelete();
            $table->foreignId('booking_id')->constrained('bookings', indexName: 'closure_impacts_booking_fk')->restrictOnDelete();
            $table->dateTime('detected_at');
            $table->string('status', 32)->default('unresolved');
            $table->dateTime('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users', indexName: 'closure_impacts_resolved_by_fk')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['availability_block_id', 'booking_id'], 'closure_impacts_block_booking_unique');
            $table->index(['availability_block_id', 'status'], 'closure_impacts_block_status_idx');
        });
        DB::statement("ALTER TABLE availability_block_booking_impacts ADD CONSTRAINT closure_impacts_status_shape CHECK ((status = 'unresolved' AND resolved_at IS NULL AND resolved_by IS NULL) OR (status = 'resolved' AND resolved_at IS NOT NULL AND resolved_by IS NOT NULL))");
    }

    public function down(): void
    {
        Schema::dropIfExists('availability_block_booking_impacts');
        DB::statement('ALTER TABLE availability_blocks DROP CHECK avail_blocks_type_supported');
        DB::statement('ALTER TABLE availability_blocks DROP CHECK avail_blocks_end_actor_pair');
        Schema::table('availability_blocks', function (Blueprint $table) {
            $table->dropForeign('avail_blocks_created_by_fk');
            $table->dropForeign('avail_blocks_ended_by_fk');
            $table->dropColumn(['type', 'created_by', 'ended_at', 'ended_by']);
        });
    }
};
