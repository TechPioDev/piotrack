<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LSEO-015: a prospect/placement can be worked for one branch — local
 * backlinks are the placements bound to a location.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outreach_prospects', function (Blueprint $table) {
            $table->foreignId('seo_location_id')->nullable()->constrained('seo_locations')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('outreach_prospects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('seo_location_id');
        });
    }
};
