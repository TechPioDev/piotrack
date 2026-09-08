<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // AUTO-029: conditional branching — {field, operator, value, on_fail}.
        Schema::table('workflow_steps', function (Blueprint $table) {
            $table->json('condition')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('workflow_steps', function (Blueprint $table) {
            $table->dropColumn('condition');
        });
    }
};
