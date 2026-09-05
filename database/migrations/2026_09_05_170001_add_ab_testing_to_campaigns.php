<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * EMAIL-015: subject-line A/B testing — a B subject on the campaign and the
 * variant each recipient was dealt.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->string('subject_b')->nullable();
        });
        Schema::table('campaign_recipients', function (Blueprint $table) {
            $table->string('variant', 1)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropColumn('subject_b');
        });
        Schema::table('campaign_recipients', function (Blueprint $table) {
            $table->dropColumn('variant');
        });
    }
};
