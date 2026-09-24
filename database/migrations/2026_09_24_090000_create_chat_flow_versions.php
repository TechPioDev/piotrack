<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What the conversation looked like before: every save keeps the version it
 * replaced, so a change that turns out to be wrong can be put back.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('chat_flow_versions')) {
            return;
        }

        Schema::create('chat_flow_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('chat_widget_id')->constrained()->cascadeOnDelete();
            $table->foreignId('saved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('flow');
            $table->unsignedSmallInteger('steps')->default(0);
            $table->boolean('published')->default(false);
            $table->string('note', 120)->nullable();
            $table->timestamps();

            $table->index(['chat_widget_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_flow_versions');
    }
};
