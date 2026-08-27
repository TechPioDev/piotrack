<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The receipt (AIVM): every visibility number stores the answer text it
     * was computed from, so a "mentioned at #2" can be checked, not believed.
     */
    public function up(): void
    {
        Schema::table('ai_visibility_checks', function (Blueprint $table) {
            $table->text('answer_excerpt')->nullable()->after('share_of_answer');
        });
    }

    public function down(): void
    {
        Schema::table('ai_visibility_checks', function (Blueprint $table) {
            $table->dropColumn('answer_excerpt');
        });
    }
};
