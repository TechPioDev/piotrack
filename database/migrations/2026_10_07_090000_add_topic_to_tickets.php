<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What a ticket from the website chat was about - the answer the visitor
 * picked ("Billing question", "Technical support").
 *
 * It was only ever inside the subject line. Kept on its own it lets a second
 * request from the same client about the same thing join the ticket that is
 * already open, instead of starting another one for somebody else to pick up.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('tickets', 'topic')) {
            return;
        }

        Schema::table('tickets', function (Blueprint $table) {
            $table->string('topic', 120)->nullable()->after('category');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('tickets', 'topic')) {
            return;
        }

        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn('topic');
        });
    }
};
