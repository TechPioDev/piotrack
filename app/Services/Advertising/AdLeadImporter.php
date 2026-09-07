<?php

namespace App\Services\Advertising;

use App\Models\Contact;
use Illuminate\Validation\ValidationException;

/**
 * Shared ad-platform lead CSV importer (LIAD-015, META-010): each platform
 * hands in its export's header aliases and a lead source. Contacts are
 * matched by email; lead_source and lifecycle are set once and never
 * overwritten — a lead whose first touch was elsewhere keeps its origin.
 */
class AdLeadImporter
{
    /**
     * @param  array<string, list<string>>  $aliases  contact-field => lowercase header aliases
     * @return array{created: int, updated: int, skipped: int}
     */
    public function import(string $path, array $aliases, string $source, ?string $fallbackCampaign = null): array
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw ValidationException::withMessages(['file' => __('The leads file could not be read.')]);
        }

        $headers = array_map(fn ($h) => mb_strtolower(trim((string) $h)), fgetcsv($handle, escape: '\\') ?: []);
        $mapping = [];
        foreach ($headers as $i => $header) {
            foreach ($aliases as $field => $names) {
                if (in_array($header, $names, true)) {
                    $mapping[$i] = $field;

                    break;
                }
            }
        }

        if (! in_array('email', $mapping, true)) {
            fclose($handle);

            throw ValidationException::withMessages(['file' => __('No email column found — upload the platform\'s lead export CSV unchanged.')]);
        }

        $created = $updated = $skipped = 0;

        while (($line = fgetcsv($handle, escape: '\\')) !== false) {
            $row = [];
            foreach ($mapping as $i => $field) {
                $row[$field] = trim((string) ($line[$i] ?? ''));
            }

            // Meta exports often carry full_name instead of first/last.
            if (($row['full_name'] ?? '') !== '' && ($row['first_name'] ?? '') === '') {
                $parts = preg_split('/\s+/', $row['full_name'], 2) ?: [];
                $row['first_name'] = $parts[0] ?? '';
                if (($row['last_name'] ?? '') === '') {
                    $row['last_name'] = $parts[1] ?? '';
                }
            }

            $email = mb_strtolower($row['email'] ?? '');
            if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $skipped++;

                continue;
            }

            $campaignName = ($row['campaign'] ?? '') !== '' ? $row['campaign'] : $fallbackCampaign;
            $contact = Contact::firstWhere('email', $email);

            if ($contact === null) {
                Contact::create(array_filter([
                    'email' => $email,
                    'first_name' => $row['first_name'] ?? null,
                    'last_name' => $row['last_name'] ?? null,
                    'title' => $row['title'] ?? null,
                    'phone' => $row['phone'] ?? null,
                    'lead_source' => $source,
                    'campaign' => $campaignName,
                    'lifecycle_stage' => 'lead',
                ], fn ($v) => $v !== null && $v !== ''));
                $created++;
            } else {
                // Fill blanks only; first-touch fields are never rewritten.
                $contact->fill(array_filter([
                    'first_name' => $contact->first_name ?: ($row['first_name'] ?? null),
                    'last_name' => $contact->last_name ?: ($row['last_name'] ?? null),
                    'title' => $contact->title ?: ($row['title'] ?? null),
                    'phone' => $contact->phone ?: ($row['phone'] ?? null),
                    'lead_source' => $contact->lead_source ?: $source,
                    'campaign' => $contact->campaign ?: $campaignName,
                ], fn ($v) => $v !== null && $v !== ''))->save();
                $updated++;
            }
        }
        fclose($handle);

        return ['created' => $created, 'updated' => $updated, 'skipped' => $skipped];
    }
}
