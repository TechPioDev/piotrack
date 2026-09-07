<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * VERT-014..019: the explicit vertical bindings the coverage report joins on
 * (previously counted by name matching), plus the per-vertical messaging
 * framework (VERT-016) beside the existing compliance notes.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['content_pieces', 'ad_campaigns', 'campaigns', 'workflows', 'target_accounts'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->foreignId('vertical_id')->nullable()->constrained('verticals')->nullOnDelete();
            });
        }

        Schema::table('verticals', function (Blueprint $t) {
            $t->json('messaging')->nullable();
        });
    }

    public function down(): void
    {
        foreach (['content_pieces', 'ad_campaigns', 'campaigns', 'workflows', 'target_accounts'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropConstrainedForeignId('vertical_id');
            });
        }

        Schema::table('verticals', function (Blueprint $t) {
            $t->dropColumn('messaging');
        });
    }
};
