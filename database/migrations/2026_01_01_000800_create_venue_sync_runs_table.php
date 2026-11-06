<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('venue_sync_runs', function (Blueprint $table) {
            $table->id();
            $table->string('source', 20);
            $table->enum('status', ['running', 'completed', 'failed'])->default('running');
            $table->boolean('dry_run')->default(false);
            $table->unsignedInteger('fetched_count')->default(0);
            $table->unsignedInteger('filtered_count')->default(0);
            $table->unsignedInteger('inserted_count')->default(0);
            $table->unsignedInteger('updated_count')->default(0);
            $table->unsignedInteger('deactivated_count')->default(0);
            $table->unsignedInteger('geocoded_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->mediumText('error_log')->nullable();
            $table->foreignId('triggered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['source', 'created_at']);
        });

        Schema::create('geocode_cache', function (Blueprint $table) {
            $table->id();
            $table->char('query_hash', 40)->unique(); // sha1 of normalised address
            $table->string('query', 500);
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->boolean('found')->default(false);
            $table->string('provider', 30)->default('nominatim');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('geocode_cache');
        Schema::dropIfExists('venue_sync_runs');
    }
};
