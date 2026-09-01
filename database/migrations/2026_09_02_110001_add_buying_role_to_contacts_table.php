<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Explicit buying-committee role on a contact (ABM-005/008): decision
     * maker, champion, influencer, blocker or user. Null = unclassified (the
     * title-based heuristic still identifies likely decision makers).
     */
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->string('buying_role', 30)->nullable()->after('title');
        });
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropColumn('buying_role');
        });
    }
};
