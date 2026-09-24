<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A ticket opened from the website chat remembers who asked and where.
 *
 * The person asking is a website visitor, not a user of the workspace, so the
 * ticket had no requester at all: replies on the desk reached nobody, and the
 * ticket had no way back to the conversation or to the client's record. It now
 * keeps the visitor's name and email, the conversation, and the matching
 * contact when there is one.
 *
 * Each column and constraint is added on its own and only when missing:
 * MySQL does not roll back DDL, so a run that stopped halfway must be able to
 * finish on the next attempt.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach ([
            'requester_email' => fn (Blueprint $table) => $table->string('requester_email')->nullable()->after('requester_id'),
            'requester_name' => fn (Blueprint $table) => $table->string('requester_name', 160)->nullable()->after('requester_email'),
            'contact_id' => fn (Blueprint $table) => $table->unsignedBigInteger('contact_id')->nullable()->after('requester_name'),
            'chat_conversation_id' => fn (Blueprint $table) => $table->unsignedBigInteger('chat_conversation_id')->nullable()->after('contact_id'),
        ] as $column => $add) {
            if (! Schema::hasColumn('tickets', $column)) {
                Schema::table('tickets', $add);
            }
        }

        $existing = $this->constrainedColumns();

        foreach (['contact_id' => 'contacts', 'chat_conversation_id' => 'chat_conversations'] as $column => $parent) {
            if (! in_array($column, $existing, true)) {
                Schema::table('tickets', fn (Blueprint $table) => $table->foreign($column)->references('id')->on($parent)->nullOnDelete());
            }
        }
    }

    public function down(): void
    {
        $existing = $this->constrainedColumns();

        foreach (['contact_id', 'chat_conversation_id'] as $column) {
            if (in_array($column, $existing, true)) {
                Schema::table('tickets', fn (Blueprint $table) => $table->dropForeign([$column]));
            }
        }

        foreach (['chat_conversation_id', 'contact_id', 'requester_name', 'requester_email'] as $column) {
            if (Schema::hasColumn('tickets', $column)) {
                Schema::table('tickets', fn (Blueprint $table) => $table->dropColumn($column));
            }
        }
    }

    /** @return list<string> the tickets columns that already carry a foreign key */
    private function constrainedColumns(): array
    {
        $columns = [];
        foreach (Schema::getForeignKeys('tickets') as $key) {
            array_push($columns, ...$key['columns']);
        }

        return $columns;
    }
};
