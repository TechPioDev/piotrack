<?php

namespace App\Marketing;

use App\Models\Contact;

/**
 * Renders `{{ tag }}` merge fields in subjects/bodies from a contact. Unknown
 * tags render empty — a template never leaks a raw `{{ ... }}` to a recipient.
 *
 * EMAIL-013: conditional blocks render per contact before tag substitution —
 * `{{#if field}}…{{else}}…{{/if}}` keeps its branch when the field is
 * non-empty, `{{#if field=value}}…{{/if}}` when it equals the value
 * (case-insensitive). Blocks do not nest; an unknown field is empty and takes
 * the else branch.
 */
class MergeTags
{
    public static function render(string $content, Contact $contact): string
    {
        $map = self::fields($contact);

        $content = preg_replace_callback(
            '/\{\{#if\s+(\w+)\s*(?:=\s*([^}]*?)\s*)?\}\}(.*?)(?:\{\{else\}\}(.*?))?\{\{\/if\}\}/s',
            function (array $m) use ($map) {
                $value = $map[$m[1]] ?? '';
                $matches = $m[2] !== ''
                    ? strcasecmp(trim($value), trim($m[2])) === 0
                    : $value !== '';

                return $matches ? $m[3] : ($m[4] ?? '');
            },
            $content,
        ) ?? $content;

        return preg_replace_callback(
            '/\{\{\s*(\w+)\s*\}\}/',
            fn (array $m) => $map[$m[1]] ?? '',
            $content,
        ) ?? $content;
    }

    /**
     * @return array<string, string>
     */
    private static function fields(Contact $contact): array
    {
        $company = $contact->company;

        return [
            'first_name' => (string) $contact->first_name,
            'last_name' => (string) $contact->last_name,
            'full_name' => $contact->fullName(),
            'email' => (string) $contact->email,
            'company' => $company !== null ? (string) $company->name : '',
            'lifecycle_stage' => (string) $contact->lifecycle_stage,
            'lead_source' => (string) $contact->lead_source,
        ];
    }
}
