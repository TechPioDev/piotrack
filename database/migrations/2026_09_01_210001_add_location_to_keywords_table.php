<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Geo targeting on the keyword itself (LSEO-002..005/017): the market a
     * local keyword ranks in — city, state, service area or neighborhood —
     * carried into every manual and scheduled rank check.
     */
    public function up(): void
    {
        Schema::table('keywords', function (Blueprint $table) {
            $table->string('location', 120)->nullable()->after('cluster');
        });
    }

    public function down(): void
    {
        Schema::table('keywords', function (Blueprint $table) {
            $table->dropColumn('location');
        });
    }
};
