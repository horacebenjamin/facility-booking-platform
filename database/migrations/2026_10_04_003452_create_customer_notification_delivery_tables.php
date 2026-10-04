<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_delivery_checkpoints', function (Blueprint $table): void {
            $table->unsignedTinyInteger('id')->primary();
            $table->unsignedBigInteger('first_activity_id');
        });
        DB::table('notification_delivery_checkpoints')->insert(['id' => 1, 'first_activity_id' => ((int) DB::table('activity_log')->max('id')) + 1]);
        Schema::create('customer_communications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('activity_id')->unique()->constrained('activity_log')->cascadeOnDelete();
            $table->string('semantic_key', 100)->unique();
            $table->foreignId('customer_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 64);
            $table->json('payload');
            $table->dateTime('database_delivered_at')->nullable();
            $table->dateTime('mail_delivered_at')->nullable();
            $table->timestamps();
        });
        Schema::create('notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('communication_id')->nullable()->unique()->constrained('customer_communications')->cascadeOnDelete();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('customer_communications');
        Schema::dropIfExists('notification_delivery_checkpoints');
    }
};
