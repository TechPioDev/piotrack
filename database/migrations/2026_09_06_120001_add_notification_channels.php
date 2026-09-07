<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * NOTIF-003/004/005: the user's SMS number, and the organization-level
 * outbound notification channels (Slack/Teams incoming webhooks + signed
 * generic webhooks).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 30)->nullable();
        });

        Schema::create('notification_channels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('kind', 20); // slack | teams | webhook
            $table->string('url', 500);
            $table->string('secret', 100)->nullable(); // HMAC key for kind=webhook
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['organization_id', 'kind', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_channels');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('phone');
        });
    }
};
