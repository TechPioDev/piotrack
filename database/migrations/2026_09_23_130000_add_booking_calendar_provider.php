<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which calendar holds this meeting - Microsoft 365 or Google - so moving or
 * cancelling it later talks to the right one.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('bookings', 'calendar_provider')) {
            return;
        }

        Schema::table('bookings', function (Blueprint $table) {
            $table->string('calendar_provider', 40)->nullable()->after('ics_token');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('bookings', 'calendar_provider')) {
            return;
        }

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('calendar_provider');
        });
    }
};
