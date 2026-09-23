<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tags a conversation collected, kept on the conversation itself.
 *
 * The "Add Tag" step used to write into the answers blob, where nothing read
 * it: not the inbox, not the CRM, not a webhook subscriber. They live here now,
 * so the team can see what a chat was about and an integration can act on it.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('chat_conversations', 'tags')) {
            return;
        }

        Schema::table('chat_conversations', function (Blueprint $table) {
            $table->json('tags')->nullable()->after('answers');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('chat_conversations', 'tags')) {
            return;
        }

        Schema::table('chat_conversations', function (Blueprint $table) {
            $table->dropColumn('tags');
        });
    }
};
