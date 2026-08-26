<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The AI handoff summary (CHAT-044): what an agent reads before taking
     * over, so a takeover starts with "Dana, 40-seat clinic, wants M365,
     * asked twice about HIPAA" rather than a scroll through the transcript.
     */
    public function up(): void
    {
        Schema::table('chat_conversations', function (Blueprint $table) {
            $table->text('summary')->nullable()->after('attribution');
            $table->timestamp('summary_generated_at')->nullable()->after('summary');
        });
    }

    public function down(): void
    {
        Schema::table('chat_conversations', function (Blueprint $table) {
            $table->dropColumn(['summary', 'summary_generated_at']);
        });
    }
};
