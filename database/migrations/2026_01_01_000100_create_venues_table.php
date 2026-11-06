<?php

use App\Support\Geo;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('venues', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('address')->nullable();
            $table->string('suburb', 100)->nullable();
            $table->char('state', 3)->nullable();
            $table->string('postcode', 4)->nullable();
            // Plain decimals are kept alongside the POINT column: they are what
            // the app reads/writes and are portable to SQLite for tests.
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->string('phone', 30)->nullable();
            $table->string('website')->nullable();
            $table->text('description')->nullable();
            $table->json('opening_hours')->nullable();
            $table->enum('source', ['nsw_liquor', 'vic_liquor', 'manual'])->default('manual');
            $table->string('external_id', 64)->nullable();
            $table->string('licence_type', 120)->nullable();
            $table->boolean('is_active')->default(true);
            // 'sync_missing' (auto, reversible by the next sync) or 'admin' (sticky).
            $table->string('deactivation_reason', 20)->nullable();
            $table->timestamp('deactivated_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            // Manual venues have NULL external_id; NULLs never collide in a unique index.
            $table->unique(['source', 'external_id'], 'venues_source_external_unique');
            $table->index(['name', 'suburb', 'postcode'], 'venues_name_suburb_postcode_index');
            $table->index('postcode');
            $table->index(['is_active', 'latitude', 'longitude'], 'venues_active_lat_lng_index');
        });

        if (Geo::supportsSpatial()) {
            // MariaDB: POINT(x = longitude, y = latitude). SPATIAL indexes require NOT NULL.
            DB::statement('ALTER TABLE venues ADD COLUMN location POINT NOT NULL AFTER longitude');
            DB::statement('ALTER TABLE venues ADD SPATIAL INDEX venues_location_spatial (location)');
            DB::statement('ALTER TABLE venues ADD FULLTEXT INDEX venues_name_suburb_fulltext (name, suburb)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('venues');
    }
};
