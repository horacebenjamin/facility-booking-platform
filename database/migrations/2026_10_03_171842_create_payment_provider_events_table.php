<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_provider_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payment_id')->constrained()->restrictOnDelete();
            $table->string('provider', 32);
            $table->string('provider_event_id')->collation('utf8mb4_bin');
            $table->string('event_type');
            $table->dateTime('provider_created_at');
            $table->unique(['provider', 'provider_event_id']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_provider_events');
    }
};
