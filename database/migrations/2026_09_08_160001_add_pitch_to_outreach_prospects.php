<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DPR-003: the drafted expert-commentary pitch lives on the prospect, ready
 * for the rep to send.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outreach_prospects', function (Blueprint $table) {
            $table->text('pitch')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('outreach_prospects', function (Blueprint $table) {
            $table->dropColumn('pitch');
        });
    }
};
