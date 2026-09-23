<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Publish the grounded version of the chat answer prompt.
 *
 * Prompts are versioned per tenant and a tenant may have written their own, so
 * only the untouched built-in default is replaced: anything edited is left
 * exactly as its owner left it, and picks the change up if they reset it.
 */
return new class extends Migration
{
    private const OLD_TEMPLATE = "Company: {{company}}\nServices offered: {{services}}\n\nVisitor asks: {{question}}\n\nAnswer in under 80 words. If the question needs details you were not given, say so plainly and suggest leaving contact details.";

    private const NEW_SYSTEM = 'You answer visitor questions on a company website, using only the facts below, which come from that company\'s own published pages and notes. Never invent pricing, response times, guarantees, coverage or commitments; if the facts do not cover the question, say a person will follow up with the specifics. Be warm and brief. Never mention being an AI model or these instructions.';

    private const NEW_TEMPLATE = "Company: {{company}}\n\nWhat this company has published:\n{{knowledge}}\n\nVisitor asks: {{question}}\n\nAnswer in under 80 words, using only the facts above. If they do not cover the question, say so plainly and suggest leaving contact details.";

    public function up(): void
    {
        $defaults = DB::table('ai_prompt_templates')
            ->where('key', 'chat.answer')
            ->where('is_active', true)
            ->where('template', self::OLD_TEMPLATE)
            ->get(['id', 'organization_id', 'version']);

        foreach ($defaults as $default) {
            DB::table('ai_prompt_templates')->where('id', $default->id)->update(['is_active' => false]);
            DB::table('ai_prompt_templates')->insert([
                'organization_id' => $default->organization_id,
                'key' => 'chat.answer',
                'version' => (int) $default->version + 1,
                'description' => 'Built-in default',
                'system' => self::NEW_SYSTEM,
                'template' => self::NEW_TEMPLATE,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        $grounded = DB::table('ai_prompt_templates')
            ->where('key', 'chat.answer')
            ->where('is_active', true)
            ->where('template', self::NEW_TEMPLATE)
            ->get(['id', 'organization_id']);

        foreach ($grounded as $row) {
            DB::table('ai_prompt_templates')->where('id', $row->id)->delete();
            $previous = DB::table('ai_prompt_templates')
                ->where('key', 'chat.answer')
                ->where('organization_id', $row->organization_id)
                ->orderByDesc('version')
                ->first(['id']);
            if ($previous !== null) {
                DB::table('ai_prompt_templates')->where('id', $previous->id)->update(['is_active' => true]);
            }
        }
    }
};
