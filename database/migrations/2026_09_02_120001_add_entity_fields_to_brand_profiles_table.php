<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LLMO-005/010: the organization-entity facts LLMs disambiguate a brand with —
 * legal/alternate names, canonical URL, logo, founding year, sameAs profile
 * links and a one-line disambiguating description. They live on the brand
 * profile because disambiguation is a brand concern, one row per organization.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('brand_profiles', function (Blueprint $table) {
            $table->string('legal_name')->nullable();
            $table->json('alternate_names')->nullable();
            $table->string('website_url', 2048)->nullable();
            $table->string('logo_url', 2048)->nullable();
            $table->unsignedSmallInteger('founded_year')->nullable();
            $table->json('same_as')->nullable();
            $table->text('disambiguation')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('brand_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'legal_name', 'alternate_names', 'website_url', 'logo_url',
                'founded_year', 'same_as', 'disambiguation',
            ]);
        });
    }
};
