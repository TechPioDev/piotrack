<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Flow-builder previews run through the real conversation engine so a tenant
 * tests exactly what a visitor will get. Those conversations must never reach
 * the CRM, the inbox or the analytics, so they are flagged here rather than
 * simulated with a parallel code path that could drift from the real one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_conversations', function (Blueprint $table) {
            $table->boolean('is_preview')->default(false)->after('visitor_id');
            $table->index(['organization_id', 'is_preview']);
        });
    }

    public function down(): void
    {
        Schema::table('chat_conversations', function (Blueprint $table) {
            $table->dropIndex(['organization_id', 'is_preview']);
            $table->dropColumn('is_preview');
        });
    }
};
