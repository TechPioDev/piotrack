<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CINT-005: point-in-time captures of a competitor's public site content
 * (url + title + content hash per page) with the diff against the previous
 * capture stored alongside — new, changed and removed pages.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('competitor_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('competitor_id')->constrained()->cascadeOnDelete();
            $table->json('pages')->nullable();
            $table->unsignedInteger('pages_count')->default(0);
            $table->json('new_pages')->nullable();
            $table->json('changed_pages')->nullable();
            $table->json('removed_pages')->nullable();
            $table->timestamps();

            $table->index(['competitor_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('competitor_snapshots');
    }
};
