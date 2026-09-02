<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TSEO-002: a bounded multi-page site crawl and its findings report
 * (indexation, crawlability, internal links, sitemap/robots, duplicates,
 * broken links, redirects, speed heuristics, architecture).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_crawls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('start_url', 2048);
            $table->unsignedInteger('pages_crawled')->default(0);
            $table->unsignedInteger('issues_count')->default(0);
            $table->json('report')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_crawls');
    }
};
