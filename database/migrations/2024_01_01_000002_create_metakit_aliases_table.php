<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('metakit_aliases', function (Blueprint $table) {
            $table->id();
            $table->string('domain', 191);
            $table->string('old_path', 255);
            $table->string('new_path', 255);
            $table->timestamps();
        });

        // Add indexes with prefix length for MySQL compatibility
        DB::statement('ALTER TABLE `metakit_aliases` ADD INDEX `metakit_aliases_domain_index` (`domain`(191))');
        DB::statement('ALTER TABLE `metakit_aliases` ADD INDEX `metakit_aliases_old_path_index` (`old_path`(191))');
        
        // Add unique constraint with prefix length for MySQL compatibility
        // Using shorter prefixes: domain(100) + old_path(100) = ~800 bytes (UTF8MB4 max 4 bytes per char)
        DB::statement('ALTER TABLE `metakit_aliases` ADD UNIQUE `metakit_aliases_domain_old_path_unique` (`domain`(100), `old_path`(100))');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('metakit_aliases');
    }
};

