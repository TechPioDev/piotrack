<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Website Chat Phase 3 — live human chat.
 *
 * Adds agent presence (who can take a chat right now) and the conversation
 * state a handoff needs: whether a human has taken over, and when the visitor
 * asked for one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_agent_presence', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('status')->default('offline'); // online|away|busy|offline
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
            // One presence row per agent per tenant.
            $table->unique(['organization_id', 'user_id']);
            $table->index(['organization_id', 'status']);
        });

        Schema::table('chat_conversations', function (Blueprint $table) {
            // A human has taken over: the bot stops driving the conversation.
            $table->boolean('is_live')->default(false)->after('is_preview');
            $table->timestamp('handoff_requested_at')->nullable()->after('is_live');
            $table->index(['organization_id', 'is_live']);
        });
    }

    public function down(): void
    {
        Schema::table('chat_conversations', function (Blueprint $table) {
            $table->dropIndex(['organization_id', 'is_live']);
            $table->dropColumn(['is_live', 'handoff_requested_at']);
        });

        Schema::dropIfExists('chat_agent_presence');
    }
};
