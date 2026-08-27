<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Visitor Intelligence (VINT): first-party website visitors and their
     * trail. The visitor row is the rollup (sessions, pages, intent score,
     * first-touch attribution, identity once known); visitor_events is the raw
     * pageview/identify record behind it. tracking_key on organizations scopes
     * the public pixel to a tenant.
     */
    public function up(): void
    {
        Schema::create('visitors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('visitor_key', 64);
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->string('email')->nullable();
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->unsignedInteger('visits')->default(0);
            $table->unsignedInteger('page_views')->default(0);
            $table->unsignedInteger('intent_score')->default(0);
            $table->string('last_path', 300)->nullable();
            $table->string('referrer', 300)->nullable();
            $table->string('utm_source', 120)->nullable();
            $table->string('utm_medium', 120)->nullable();
            $table->string('utm_campaign', 120)->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'visitor_key']);
            $table->index(['organization_id', 'last_seen_at']);
        });

        Schema::create('visitor_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('visitor_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);
            $table->string('path', 300)->nullable();
            $table->string('title', 200)->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'created_at']);
        });

        Schema::table('organizations', function (Blueprint $table) {
            $table->string('tracking_key', 40)->nullable()->unique()->after('slug');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visitor_events');
        Schema::dropIfExists('visitors');
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn('tracking_key');
        });
    }
};
