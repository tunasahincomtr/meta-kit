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
        Schema::create('metakit_pages', function (Blueprint $table) {
            $table->id();
            $table->string('domain', 191)->index();
            $table->text('path');
            $table->string('query_hash', 40)->nullable()->index();
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->text('keywords')->nullable();
            $table->string('robots')->nullable()->default('index, follow');
            $table->string('canonical_url')->nullable();
            $table->string('og_title')->nullable();
            $table->text('og_description')->nullable();
            $table->string('og_image')->nullable();
            $table->string('twitter_card')->nullable()->default('summary_large_image');
            $table->string('twitter_title')->nullable();
            $table->text('twitter_description')->nullable();
            $table->string('twitter_image')->nullable();
            $table->json('jsonld')->nullable();
            $table->enum('status', ['draft', 'active'])->default('active');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Add path index with prefix length (191 chars max for utf8mb4 to fit in 1000 bytes)
        DB::statement('ALTER TABLE `metakit_pages` ADD INDEX `metakit_pages_path_index` (`path`(191))');
        
        // Add unique constraint with prefix length for MySQL compatibility
        // Using shorter prefixes to fit within 1000 byte limit: domain(100) + path(100) + query_hash(40) = ~960 bytes
        DB::statement('ALTER TABLE `metakit_pages` ADD UNIQUE `metakit_pages_unique` (`domain`(100), `path`(100), `query_hash`)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('metakit_pages');
    }
};
