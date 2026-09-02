<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * BENCH-008/009: the explicit proposal-stage convention the benchmark rows
 * were waiting on — a flag on the stage, a first-entry timestamp on the deal.
 * Existing "Proposal" stages adopt the flag so current tenants join the
 * convention without manual work.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pipeline_stages', function (Blueprint $table) {
            $table->boolean('is_proposal')->default(false);
        });
        Schema::table('deals', function (Blueprint $table) {
            $table->timestamp('proposal_sent_at')->nullable();
        });

        DB::table('pipeline_stages')->where('name', 'Proposal')->update(['is_proposal' => true]);
    }

    public function down(): void
    {
        Schema::table('pipeline_stages', function (Blueprint $table) {
            $table->dropColumn('is_proposal');
        });
        Schema::table('deals', function (Blueprint $table) {
            $table->dropColumn('proposal_sent_at');
        });
    }
};
