<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AISA-012/013: advisory AI scores get history, so predictive quality can be
 * MEASURED against outcomes instead of claimed. Advisory only — the
 * deterministic lead_score is never written from here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->morphs('scoreable');
            $table->unsignedSmallInteger('score');
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'scoreable_type', 'scoreable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_scores');
    }
};
