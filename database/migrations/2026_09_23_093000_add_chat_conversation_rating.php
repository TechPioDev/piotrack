<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What the visitor thought: one to five, asked once the chat has finished.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('chat_conversations', 'rating')) {
            return;
        }

        Schema::table('chat_conversations', function (Blueprint $table) {
            $table->unsignedTinyInteger('rating')->nullable()->after('lead_score');
            $table->timestamp('rated_at')->nullable()->after('rating');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('chat_conversations', 'rating')) {
            return;
        }

        Schema::table('chat_conversations', function (Blueprint $table) {
            $table->dropColumn(['rating', 'rated_at']);
        });
    }
};
