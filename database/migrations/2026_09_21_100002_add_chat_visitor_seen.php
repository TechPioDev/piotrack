<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Whether the visitor is still in the chat, and how far through the
        // conversation their widget has got: an agent's reply they never saw
        // is emailed to them once they have left. One column per statement so
        // a failed run on MySQL (no transactional DDL) can simply be re-run.
        if (! Schema::hasColumn('chat_conversations', 'visitor_seen_at')) {
            Schema::table('chat_conversations', function (Blueprint $table) {
                $table->timestamp('visitor_seen_at')->nullable();
            });
        }

        if (! Schema::hasColumn('chat_conversations', 'visitor_seen_message_id')) {
            Schema::table('chat_conversations', function (Blueprint $table) {
                $table->unsignedBigInteger('visitor_seen_message_id')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['visitor_seen_message_id', 'visitor_seen_at'] as $column) {
            if (Schema::hasColumn('chat_conversations', $column)) {
                Schema::table('chat_conversations', function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }
    }
};
