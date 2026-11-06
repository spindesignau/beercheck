<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('points_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('event_type', 40);
            $table->integer('points_awarded'); // signed: reversals are negative
            $table->string('reference_type', 40)->nullable(); // beer_price | beer
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note', 255)->nullable();
            $table->timestamps();

            // Idempotency: the same contribution can never be rewarded twice.
            $table->unique(['user_id', 'event_type', 'reference_type', 'reference_id'], 'points_events_idempotency');
            $table->index(['user_id', 'created_at']);
            $table->index('event_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('points_events');
    }
};
