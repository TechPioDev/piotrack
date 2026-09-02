<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MLOC-006/008/011: ad campaigns and content pieces can target one branch;
 * unscoped rows stay central. Nullable on purpose — central marketing is the
 * default, not an error.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ad_campaigns', function (Blueprint $table) {
            $table->foreignId('seo_location_id')->nullable()->constrained('seo_locations')->nullOnDelete();
        });
        Schema::table('content_pieces', function (Blueprint $table) {
            $table->foreignId('seo_location_id')->nullable()->constrained('seo_locations')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ad_campaigns', function (Blueprint $table) {
            $table->dropConstrainedForeignId('seo_location_id');
        });
        Schema::table('content_pieces', function (Blueprint $table) {
            $table->dropConstrainedForeignId('seo_location_id');
        });
    }
};
