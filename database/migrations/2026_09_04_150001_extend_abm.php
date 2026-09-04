<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ABM close-out: reporting lines captured in-CRM (ABM-007) and content targeted
 * at one account's company (ABM-011).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->foreignId('reports_to_contact_id')->nullable()->constrained('contacts')->nullOnDelete();
        });
        Schema::table('content_pieces', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reports_to_contact_id');
        });
        Schema::table('content_pieces', function (Blueprint $table) {
            $table->dropConstrainedForeignId('company_id');
        });
    }
};
