<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The meeting as it exists in the team's own calendar: the event it became,
 * and the link people actually join.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('bookings', 'calendar_event_id')) {
            return;
        }

        Schema::table('bookings', function (Blueprint $table) {
            $table->string('calendar_event_id', 512)->nullable()->after('ics_token');
            $table->string('meeting_url', 512)->nullable()->after('calendar_event_id');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('bookings', 'calendar_event_id')) {
            return;
        }

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['calendar_event_id', 'meeting_url']);
        });
    }
};
