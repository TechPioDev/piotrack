<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * REP-005/006/007: a testimonial can carry its video link, and an authority
 * asset (directory profile, placement) can carry structured details.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->string('video_url', 2048)->nullable();
        });
        Schema::table('authority_assets', function (Blueprint $table) {
            $table->json('details')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropColumn('video_url');
        });
        Schema::table('authority_assets', function (Blueprint $table) {
            $table->dropColumn('details');
        });
    }
};
