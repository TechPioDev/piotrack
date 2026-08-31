<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\ImportJob;
use App\Models\Lead;
use App\Models\Organization;
use App\Models\Pipeline;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * CSV import for companies, leads and deals (IMEX-001), on the same pipeline
 * contract as the contact importer: header-alias mapping, per-row validation
 * and dedupe, preview, committed import with an ImportJob history record and
 * a per-row error report. Contacts keep their original importer untouched.
 */
class EntityCsvImporter
{
    /** @var array<string, array<string, list<string>>> header aliases per entity */
    private const ALIASES = [
        'companies' => [
            'name' => ['name', 'company', 'company name', 'organization', 'account'],
            'domain' => ['domain', 'website domain'],
            'industry' => ['industry', 'sector', 'vertical'],
            'phone' => ['phone', 'phone number', 'telephone'],
            'website' => ['website', 'url', 'site'],
        ],
        'leads' => [
            'first_name' => ['first name', 'firstname', 'first', 'name'],
            'last_name' => ['last name', 'lastname', 'surname'],
            'email' => ['email', 'email address', 'e-mail'],
            'phone' => ['phone', 'phone number', 'mobile'],
            'company_name' => ['company', 'company name', 'organization', 'account'],
            'source' => ['source', 'lead source', 'channel'],
        ],
        'deals' => [
            'name' => ['name', 'deal', 'deal name', 'opportunity', 'opportunity name'],
            'value' => ['value', 'amount', 'deal value'],
            'mrr' => ['mrr', 'monthly recurring revenue'],
            'contact_email' => ['contact email', 'contact', 'email'],
            'stage' => ['stage', 'pipeline stage'],
        ],
    ];

    public const ENTITIES = ['companies', 'leads', 'deals'];

    /**
     * @return array{headers: list<string>, mapping: array<string,string>, rows: list<array<string,string>>}
     */
    public function parse(string $entity, string $path): array
    {
        $handle = fopen($path, 'r');
        $headers = fgetcsv($handle) ?: [];
        $mapping = $this->mapHeaders($entity, $headers);

        $rows = [];
        while (($line = fgetcsv($handle)) !== false) {
            $row = [];
            foreach ($headers as $i => $header) {
                $field = $mapping[$header] ?? null;
                if ($field !== null) {
                    $row[$field] = trim((string) ($line[$i] ?? ''));
                }
            }
            if ($row !== []) {
                $rows[] = $row;
            }
        }
        fclose($handle);

        return ['headers' => $headers, 'mapping' => $mapping, 'rows' => $rows];
    }

    /**
     * @param  list<array<string,string>>  $rows
     * @return array{total: int, valid: int, invalid: int, duplicates: int, sample: list<array{label: string, status: string, error: ?string}>}
     */
    public function analyze(string $entity, Organization $organization, array $rows): array
    {
        $existing = $this->existingKeys($entity, $organization);
        $seen = [];
        $valid = $invalid = $duplicates = 0;
        $sample = [];

        foreach ($rows as $i => $row) {
            [$status, $error] = $this->classify($entity, $row, $existing, $seen);
            match ($status) {
                'valid' => $valid++,
                'duplicate' => $duplicates++,
                default => $invalid++,
            };
            if ($i < 10) {
                $sample[] = ['label' => $this->label($entity, $row), 'status' => $status, 'error' => $error];
            }
        }

        return ['total' => count($rows), 'valid' => $valid, 'invalid' => $invalid, 'duplicates' => $duplicates, 'sample' => $sample];
    }

    /**
     * @param  list<array<string,string>>  $rows
     */
    public function import(string $entity, Organization $organization, ?User $user, string $filename, array $rows): ImportJob
    {
        return DB::transaction(function () use ($entity, $organization, $user, $filename, $rows) {
            $existing = $this->existingKeys($entity, $organization);
            $seen = [];
            $imported = $skipped = $failed = 0;
            $errors = [];

            foreach ($rows as $index => $row) {
                [$status, $error] = $this->classify($entity, $row, $existing, $seen);

                if ($status === 'duplicate') {
                    $skipped++;

                    continue;
                }
                if ($status === 'invalid') {
                    $failed++;
                    $errors[] = ['row' => $index + 2, 'error' => $error];

                    continue;
                }

                $this->createRecord($entity, $organization, $user, $row);
                $imported++;
            }

            return ImportJob::create([
                'organization_id' => $organization->id,
                'user_id' => $user?->id,
                'resource' => $entity,
                'filename' => $filename,
                'status' => 'completed',
                'total' => count($rows),
                'imported' => $imported,
                'skipped' => $skipped,
                'failed' => $failed,
                'errors' => $errors === [] ? null : $errors,
            ]);
        });
    }

    /**
     * @param  array<string,string>  $row
     */
    private function createRecord(string $entity, Organization $organization, ?User $user, array $row): void
    {
        if ($entity === 'companies') {
            Company::create([
                'organization_id' => $organization->id,
                'name' => $row['name'],
                'domain' => $row['domain'] ?? null,
                'industry' => $row['industry'] ?? null,
                'phone' => $row['phone'] ?? null,
                'website' => $row['website'] ?? null,
                'owner_id' => $user?->id,
            ]);

            return;
        }

        if ($entity === 'leads') {
            Lead::create([
                'organization_id' => $organization->id,
                'first_name' => $row['first_name'],
                'last_name' => $row['last_name'] ?? null,
                'email' => $row['email'] ?? null,
                'phone' => $row['phone'] ?? null,
                'company_name' => $row['company_name'] ?? null,
                'source' => $row['source'] ?? 'import',
                'status' => 'new',
                'owner_id' => $user?->id,
            ]);

            return;
        }

        // Deals: money arrives in dollars and is stored in minor units, the
        // same convention as the deal forms. Stage resolves by name inside the
        // default pipeline, falling back to its first stage.
        $pipeline = Pipeline::where('organization_id', $organization->id)->where('is_default', true)->first();
        $stage = null;
        if ($pipeline !== null) {
            $stage = ! empty($row['stage'])
                ? $pipeline->stages()->whereRaw('lower(name) = ?', [Str::lower($row['stage'])])->first()
                : null;
            $stage ??= $pipeline->stages()->first();
        }

        $contact = ! empty($row['contact_email'])
            ? Contact::where('organization_id', $organization->id)->where('email', Str::lower($row['contact_email']))->first()
            : null;

        Deal::create([
            'organization_id' => $organization->id,
            'pipeline_id' => $pipeline?->id,
            'stage_id' => $stage?->id,
            'name' => $row['name'],
            'value' => (int) round(((float) ($row['value'] ?? 0)) * 100),
            'mrr' => (int) round(((float) ($row['mrr'] ?? 0)) * 100),
            'contact_id' => $contact?->id,
            'status' => 'open',
            'owner_id' => $user?->id,
        ]);
    }

    /**
     * @return array<string, bool>
     */
    private function existingKeys(string $entity, Organization $organization): array
    {
        return match ($entity) {
            'companies' => Company::where('organization_id', $organization->id)
                ->pluck('name')->mapWithKeys(fn ($n) => [Str::lower((string) $n) => true])->all(),
            'leads' => Lead::where('organization_id', $organization->id)
                ->whereNotNull('email')->pluck('email')->mapWithKeys(fn ($e) => [Str::lower((string) $e) => true])->all(),
            default => [],
        };
    }

    /**
     * @param  array<string,string>  $row
     * @param  array<string,bool>  $existing
     * @param  array<string,bool>  $seen
     * @return array{0: string, 1: ?string}
     */
    private function classify(string $entity, array $row, array $existing, array &$seen): array
    {
        if ($entity === 'companies') {
            if (empty($row['name'])) {
                return ['invalid', 'Missing company name'];
            }
            $key = Str::lower($row['name']);
            if (isset($existing[$key]) || isset($seen[$key])) {
                return ['duplicate', 'Company already exists'];
            }
            $seen[$key] = true;

            return ['valid', null];
        }

        if ($entity === 'leads') {
            if (empty($row['first_name'])) {
                return ['invalid', 'Missing first name'];
            }
            $email = $row['email'] ?? '';
            if ($email !== '' && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return ['invalid', 'Invalid email'];
            }
            if ($email !== '') {
                $key = Str::lower($email);
                if (isset($existing[$key]) || isset($seen[$key])) {
                    return ['duplicate', 'Duplicate email'];
                }
                $seen[$key] = true;
            }

            return ['valid', null];
        }

        if (empty($row['name'])) {
            return ['invalid', 'Missing deal name'];
        }
        if (isset($row['value']) && $row['value'] !== '' && ! is_numeric($row['value'])) {
            return ['invalid', 'Deal value is not a number'];
        }
        if (isset($row['mrr']) && $row['mrr'] !== '' && ! is_numeric($row['mrr'])) {
            return ['invalid', 'MRR is not a number'];
        }

        return ['valid', null];
    }

    /**
     * @param  array<string,string>  $row
     */
    private function label(string $entity, array $row): string
    {
        return match ($entity) {
            'companies' => $row['name'] ?? '',
            'leads' => trim(($row['first_name'] ?? '').' '.($row['last_name'] ?? '')),
            default => $row['name'] ?? '',
        };
    }

    /**
     * @param  list<string>  $headers
     * @return array<string,string>
     */
    private function mapHeaders(string $entity, array $headers): array
    {
        $mapping = [];
        foreach ($headers as $header) {
            $normalized = Str::lower(trim($header));
            foreach (self::ALIASES[$entity] as $field => $aliases) {
                if (in_array($normalized, $aliases, true)) {
                    $mapping[$header] = $field;
                    break;
                }
            }
        }

        return $mapping;
    }
}
