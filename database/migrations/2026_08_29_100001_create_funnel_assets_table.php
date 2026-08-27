<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Funnel Builder (FUNL): a stage carries the real platform assets that work
     * it — content, forms, booking pages, ads, workflows — so a funnel is an
     * executable map, not a diagram. asset_type is an allow-listed string; the
     * referenced row lives in that type's own table (validated per type at the
     * boundary, tenant-scoped like everything else).
     */
    public function up(): void
    {
        Schema::create('funnel_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('funnel_stage_id')->constrained()->cascadeOnDelete();
            $table->string('asset_type', 40);
            $table->unsignedBigInteger('asset_id');
            $table->timestamps();

            $table->unique(['funnel_stage_id', 'asset_type', 'asset_id']);
            $table->index(['organization_id', 'asset_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('funnel_assets');
    }
};
