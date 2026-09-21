<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The widget's own logo, shown in the launcher and chat header. Its own
        // column rather than a key inside `theme`: saving the settings form
        // replaces `theme` wholesale, which would silently drop the logo.
        if (! Schema::hasColumn('chat_widgets', 'logo_path')) {
            Schema::table('chat_widgets', function (Blueprint $table) {
                $table->string('logo_path')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('chat_widgets', 'logo_path')) {
            Schema::table('chat_widgets', function (Blueprint $table) {
                $table->dropColumn('logo_path');
            });
        }
    }
};
