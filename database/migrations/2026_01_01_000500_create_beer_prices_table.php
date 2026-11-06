<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('beer_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('beer_id')->constrained()->restrictOnDelete();
            $table->foreignId('venue_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->decimal('price', 8, 2);
            $table->enum('size', ['pot', 'schooner', 'pint', 'jug', 'stubby', 'longneck', 'can']);
            $table->boolean('is_venue_listed')->default(false);
            $table->string('photo_path')->nullable();
            $table->unsignedTinyInteger('rating')->nullable(); // 1-5, validated in app
            $table->text('review_text')->nullable();
            // Moderation: flagged = hidden pending admin review; soft-deleted = removed.
            $table->boolean('is_flagged')->default(false);
            $table->string('flag_reason', 255)->nullable();
            $table->foreignId('moderated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('moderated_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // "Current price" window function partitions on (venue, beer, size) ordered by recency.
            $table->index(['venue_id', 'beer_id', 'size', 'created_at'], 'beer_prices_current_lookup');
            $table->index(['beer_id', 'size', 'price'], 'beer_prices_beer_size_price');
            $table->index(['user_id', 'created_at']);
            $table->index(['is_flagged', 'deleted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('beer_prices');
    }
};
