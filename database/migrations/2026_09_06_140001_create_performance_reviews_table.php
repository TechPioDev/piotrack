<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PERF-011: the formal ROI review artefact — a stored, timestamped snapshot
 * per agreement period, not a transient screen.
 *
 * The index carries an explicit short name: the auto-generated
 * `performance_reviews_organization_id_performance_agreement_id_index` is 66
 * characters, over MySQL's 64-character identifier limit (production runs
 * MySQL). The guard below also heals a half-applied MySQL run where the
 * CREATE TABLE succeeded but the 1059 error killed the index before the
 * migration was recorded.
 */
return new class extends Migration
{
    public function up(): void
    {
        // The failed 2026-09-08 production deploys left an EMPTY, unrecorded
        // copy of this table behind (MySQL DDL is non-transactional and the
        // 1059 index error struck after CREATE TABLE, before the migration was
        // recorded; app code never wrote to it). Dropping it first makes this
        // migration deterministic from every starting state, with nothing to
        // lose.
        Schema::dropIfExists('performance_reviews');

        Schema::create('performance_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('performance_agreement_id')->constrained('performance_agreements')->cascadeOnDelete();
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->json('data');
            $table->timestamps();

            $table->index(['organization_id', 'performance_agreement_id'], 'perf_reviews_org_agreement_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('performance_reviews');
    }
};
