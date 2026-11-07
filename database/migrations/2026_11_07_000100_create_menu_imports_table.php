<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source_name');              // file name or sheet URL
            $table->enum('status', ['completed', 'failed', 'undone'])->default('completed');
            $table->boolean('as_official')->default(true);
            $table->unsignedInteger('rows_read')->default(0);
            $table->unsignedInteger('prices_imported')->default(0);
            $table->unsignedInteger('beers_created')->default(0);
            $table->unsignedInteger('duplicates_skipped')->default(0);
            $table->unsignedInteger('error_count')->default(0);
            $table->json('errors')->nullable();         // first 200 row errors
            $table->timestamp('undone_at')->nullable();
            $table->timestamps();
        });

        Schema::table('beer_prices', function (Blueprint $table) {
            $table->foreignId('menu_import_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('beer_prices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('menu_import_id');
        });
        Schema::dropIfExists('menu_imports');
    }
};
