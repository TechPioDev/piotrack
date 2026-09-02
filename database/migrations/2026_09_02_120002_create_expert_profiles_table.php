<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LLMO-007/008: the people behind the MSP as machine-readable entities. An
 * expert profile carries the E-E-A-T signals answer engines read — job title,
 * bio, credentials (CISSP, CCNA, vCIO…), topics known about, and sameAs links
 * to authoritative profiles.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expert_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('title')->nullable();
            $table->text('bio')->nullable();
            $table->json('credentials')->nullable();
            $table->json('knows_about')->nullable();
            $table->json('same_as')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['organization_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expert_profiles');
    }
};
