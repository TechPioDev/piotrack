<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Website Chat / Conversations module (CHAT). Tenant-scoped tables for embeddable
 * chat widgets, the conversations they produce, their messages, and the analytics
 * events that feed the funnel and per-question drop-off reports.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_widgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            // Non-secret public id embedded in the customer's page source; the widget
            // resolves its tenant + config from this, never from a session.
            $table->string('public_key')->unique();
            $table->string('name');
            $table->string('description')->nullable();
            $table->string('status')->default('draft'); // draft|active|paused
            $table->json('flow')->nullable();           // conversation graph: {start, nodes{}}
            $table->json('theme')->nullable();          // logo, avatar, accent, position, title
            $table->json('targeting')->nullable();      // page include/exclude, devices, visitors
            $table->json('business_hours')->nullable(); // per-day open/close + offline message
            $table->json('consent')->nullable();        // required?, message, privacy_url
            $table->json('routing')->nullable();        // strategy + assignments
            $table->json('settings')->nullable();       // teaser, language, live-chat mode, fallback
            $table->json('allowed_domains')->nullable(); // origin allow-list (empty = any)
            $table->timestamps();
            $table->softDeletes();
            $table->index(['organization_id', 'status']);
        });

        Schema::create('chat_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('chat_widget_id')->constrained('chat_widgets')->cascadeOnDelete();
            // Opaque per-conversation id handed to the widget so it can post messages
            // without exposing the numeric primary key.
            $table->string('token')->unique();
            $table->string('status')->default('new'); // new|open|waiting|assigned|qualified|converted|closed|spam
            $table->string('visitor_id')->nullable(); // anonymous cookie id from the widget
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained('leads')->nullOnDelete();
            $table->unsignedInteger('lead_score')->default(0);
            $table->json('answers')->nullable();      // collected field values keyed by field
            $table->json('attribution')->nullable();  // source/page/utm/campaign/keyword/referrer
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'status']);
            $table->index(['organization_id', 'chat_widget_id']);
            $table->index(['organization_id', 'assignee_id']);
        });

        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('chat_conversation_id')->constrained('chat_conversations')->cascadeOnDelete();
            $table->string('role'); // visitor|bot|agent|note|system
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('body')->nullable();
            $table->json('meta')->nullable(); // choice options, node id, field key
            $table->timestamps();
            $table->index(['organization_id', 'chat_conversation_id']);
        });

        Schema::create('chat_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('chat_widget_id')->constrained('chat_widgets')->cascadeOnDelete();
            $table->foreignId('chat_conversation_id')->nullable()->constrained('chat_conversations')->nullOnDelete();
            $table->string('type'); // impression|open|start|complete|lead|qualified|meeting|dropoff
            $table->string('node_id')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'chat_widget_id', 'type']);
            $table->index(['organization_id', 'type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_events');
        Schema::dropIfExists('chat_messages');
        Schema::dropIfExists('chat_conversations');
        Schema::dropIfExists('chat_widgets');
    }
};
