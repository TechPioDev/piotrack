<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // INTENT-002: the visitor's last IP, stored under the same consent
        // gate as every other pixel field, so reverse-IP company
        // identification has something real to look up.
        Schema::table('visitors', function (Blueprint $table) {
            $table->string('last_ip', 45)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('visitors', function (Blueprint $table) {
            $table->dropColumn('last_ip');
        });
    }
};
