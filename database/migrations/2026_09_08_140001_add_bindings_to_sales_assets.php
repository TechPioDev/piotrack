<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ENAB-015/016: vertical- and service-specific collateral — the same hard
 * bindings the coverage reports join on everywhere else.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_assets', function (Blueprint $table) {
            $table->foreignId('vertical_id')->nullable()->constrained('verticals')->nullOnDelete();
            $table->foreignId('service_line_id')->nullable()->constrained('service_lines')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sales_assets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('vertical_id');
            $table->dropConstrainedForeignId('service_line_id');
        });
    }
};
