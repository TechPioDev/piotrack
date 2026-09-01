<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-organization sales-alert delivery config (ALERT-002/004): an SMS
     * number and/or an incoming-webhook URL (Slack/Teams). Null = in-app+email
     * only, today's behavior.
     */
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->json('alert_channels')->nullable()->after('slug');
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn('alert_channels');
        });
    }
};
