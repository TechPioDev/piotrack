<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * VID-017 + PORTAL-012/014: the campaign video block, and the client-visible
 * flags (default FALSE — nothing reaches the portal unless flagged).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('files', function (Blueprint $table) {
            $table->boolean('client_visible')->default(false);
        });

        Schema::table('activities', function (Blueprint $table) {
            $table->boolean('client_visible')->default(false);
        });

        Schema::table('campaigns', function (Blueprint $table) {
            $table->string('video_url', 500)->nullable();
            $table->string('video_title', 200)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('files', fn (Blueprint $t) => $t->dropColumn('client_visible'));
        Schema::table('activities', fn (Blueprint $t) => $t->dropColumn('client_visible'));
        Schema::table('campaigns', fn (Blueprint $t) => $t->dropColumn(['video_url', 'video_title']));
    }
};
