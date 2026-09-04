<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CRO-010/011/014: the first-party pixel captures clicks (viewport-x% ×
 * document-y%) and scroll depth, so heatmaps and behavior analysis run on our
 * own data instead of a third-party recorder.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visitor_events', function (Blueprint $table) {
            $table->unsignedSmallInteger('x_pct')->nullable();
            $table->unsignedSmallInteger('y_pct')->nullable();
            $table->index(['organization_id', 'type', 'path']);
        });
    }

    public function down(): void
    {
        Schema::table('visitor_events', function (Blueprint $table) {
            $table->dropIndex(['organization_id', 'type', 'path']);
            $table->dropColumn(['x_pct', 'y_pct']);
        });
    }
};
