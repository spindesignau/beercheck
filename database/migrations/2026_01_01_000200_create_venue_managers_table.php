<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('venue_managers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('venue_id')->constrained()->cascadeOnDelete();
            $table->string('business_name');
            $table->string('business_email');
            $table->string('abn', 11)->nullable();
            $table->enum('status', ['pending', 'email_verified', 'approved', 'rejected'])->default('pending');
            $table->timestamp('business_email_verified_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('admin_notes', 500)->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'venue_id']);
            $table->index(['venue_id', 'status']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('venue_managers');
    }
};
