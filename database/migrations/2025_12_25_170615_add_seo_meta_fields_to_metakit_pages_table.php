<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('metakit_pages', function (Blueprint $table) {
            // Language support (http-equiv="content-language")
            $table->string('language', 10)->nullable()->after('robots');
            
            // OG Site Name
            $table->string('og_site_name')->nullable()->after('og_image');
            
            // Twitter handles
            $table->string('twitter_site', 100)->nullable()->after('twitter_image');
            $table->string('twitter_creator', 100)->nullable()->after('twitter_site');
            
            // Author
            $table->string('author')->nullable()->after('twitter_creator');
            
            // Theme color for PWA
            $table->string('theme_color', 7)->nullable()->after('author');
            
            // Breadcrumb JSON-LD (stored separately for easier management)
            $table->json('breadcrumb_jsonld')->nullable()->after('jsonld');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('metakit_pages', function (Blueprint $table) {
            $table->dropColumn([
                'language',
                'og_site_name',
                'twitter_site',
                'twitter_creator',
                'author',
                'theme_color',
                'breadcrumb_jsonld',
            ]);
        });
    }
};
