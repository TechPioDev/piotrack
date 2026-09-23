<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The answers a team types over and over, kept once.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('chat_saved_replies')) {
            return;
        }

        Schema::create('chat_saved_replies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title', 80);
            $table->text('body');
            $table->timestamps();

            $table->index(['organization_id', 'title']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_saved_replies');
    }
};
