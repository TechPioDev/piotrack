<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SOC-020/021/023: the engagement queue (logged comments/DMs/mentions) and
 * the listening terms the monitoring seam tracks.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_interactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('network', 30);
            $table->string('kind', 20); // comment | dm | mention
            $table->string('author', 150)->nullable();
            $table->string('url', 500)->nullable();
            $table->text('body');
            $table->string('sentiment', 15)->nullable(); // positive | neutral | negative
            $table->string('status', 20)->default('open'); // open | replied | dismissed
            $table->string('source', 20)->default('manual'); // manual | listening
            $table->timestamp('replied_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status'], 'social_interactions_org_status_idx');
        });

        Schema::create('listening_terms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('term', 120);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['organization_id', 'is_active'], 'listening_terms_org_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('listening_terms');
        Schema::dropIfExists('social_interactions');
    }
};
