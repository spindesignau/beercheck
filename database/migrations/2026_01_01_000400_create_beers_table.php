<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('beers', function (Blueprint $table) {
            $table->id();
            $table->string('brand', 100);
            $table->string('name', 150);
            $table->foreignId('type_id')->constrained('beer_types')->restrictOnDelete();
            $table->decimal('abv', 4, 1)->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('rejection_reason', 255)->nullable();
            $table->timestamps();

            $table->unique(['brand', 'name']);
            $table->index(['status', 'brand']);
            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('beers');
    }
};
