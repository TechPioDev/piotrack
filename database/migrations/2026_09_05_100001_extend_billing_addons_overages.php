<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BILL-004/005: metered overage pricing on plans, and per-subscription
 * add-ons whose grants the central Entitlements resolver applies.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->json('overage_prices')->nullable(); // {limit_key: cents_per_unit}
        });

        Schema::create('subscription_addons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->string('code', 60);
            $table->string('name', 150);
            $table->unsignedInteger('price');       // cents per period
            $table->json('grants');                 // {limit_key: boost}
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->timestamps();

            $table->unique(['subscription_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn('overage_prices');
        });
        Schema::dropIfExists('subscription_addons');
    }
};
