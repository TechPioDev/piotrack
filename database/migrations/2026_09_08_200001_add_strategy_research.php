<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // STRAT-007: structured buyer personas, authored against computed evidence.
        Schema::create('buyer_personas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('role_title', 150)->nullable();
            $table->string('seniority', 50)->nullable();
            $table->text('goals')->nullable();
            $table->text('pains')->nullable();
            $table->text('channels')->nullable();
            $table->text('objections')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // STRAT-014: bind deals to service lines so opportunity analysis joins
        // on records, not name matching (the P39 hard-binding pattern).
        Schema::table('deals', function (Blueprint $table) {
            $table->foreignId('service_line_id')->nullable()
                ->constrained('service_lines')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('deals', function (Blueprint $table) {
            $table->dropConstrainedForeignId('service_line_id');
        });
        Schema::dropIfExists('buyer_personas');
    }
};
