<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BENCH-003: a campaign can target one service line — the taxonomy axis P12
 * started with seo_location_id, and the binding segmented CPC benchmarks
 * aggregate on (service_lines.key is canonical across tenants).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ad_campaigns', function (Blueprint $table) {
            $table->foreignId('service_line_id')->nullable()->constrained('service_lines')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ad_campaigns', function (Blueprint $table) {
            $table->dropConstrainedForeignId('service_line_id');
        });
    }
};
