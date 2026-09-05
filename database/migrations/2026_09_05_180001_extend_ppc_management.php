<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PPC-017/020: ad extensions (sitelinks, callouts, structured snippets, call
 * extensions) stored per campaign, and the tracking number → ad campaign link
 * that attributes calls to the campaign that drove them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ad_extensions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('ad_campaign_id')->constrained('ad_campaigns')->cascadeOnDelete();
            $table->string('kind', 30); // sitelink | callout | structured_snippet | call
            $table->string('text', 90);
            $table->string('url', 500)->nullable();
            $table->string('phone', 30)->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'ad_campaign_id', 'kind']);
        });

        Schema::table('call_tracking_numbers', function (Blueprint $table) {
            $table->foreignId('ad_campaign_id')->nullable()->constrained('ad_campaigns')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('call_tracking_numbers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ad_campaign_id');
        });
        Schema::dropIfExists('ad_extensions');
    }
};
