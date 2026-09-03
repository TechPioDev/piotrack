<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * WEB close-out: experiments bind to a site page and variants carry the
 * content they test (WEB-033..037); lead-magnet downloads are counted
 * (WEB-022).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('experiments', function (Blueprint $table) {
            $table->foreignId('site_page_id')->nullable()->constrained('site_pages')->nullOnDelete();
        });
        Schema::table('experiment_variants', function (Blueprint $table) {
            $table->json('content')->nullable();
        });
        Schema::table('files', function (Blueprint $table) {
            $table->unsignedInteger('download_count')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('experiments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('site_page_id');
        });
        Schema::table('experiment_variants', function (Blueprint $table) {
            $table->dropColumn('content');
        });
        Schema::table('files', function (Blueprint $table) {
            $table->dropColumn('download_count');
        });
    }
};
