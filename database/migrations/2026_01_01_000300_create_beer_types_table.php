<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('beer_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique();
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->timestamps();
        });

        // Reference data is part of the schema, not optional seed data.
        $now = now();
        DB::table('beer_types')->insert(collect(
            ['Lager', 'Pale Ale', 'Ale', 'IPA', 'Stout', 'Porter', 'Wheat', 'Sour', 'Cider', 'Mid-strength', 'Other']
        )->map(fn ($name, $i) => [
            'name' => $name, 'display_order' => ($i + 1) * 10, 'created_at' => $now, 'updated_at' => $now,
        ])->all());
    }

    public function down(): void
    {
        Schema::dropIfExists('beer_types');
    }
};
