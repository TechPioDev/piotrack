<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Competitor;
use App\Models\Deal;
use App\Models\Keyword;
use App\Models\Lead;
use App\Support\AuditLogger;
use App\Support\Csv;
use App\Support\CurrentOrganization;
use App\Support\Xlsx;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streamed CSV exports for companies, leads and deals (IMEX-003), matching
 * the contact export: chunked so large books don't buffer, every cell through
 * the Csv formula-injection guard.
 */
class EntityExportController extends Controller
{
    public function __construct(
        private CurrentOrganization $currentOrganization,
        private AuditLogger $audit,
    ) {}

    public function __invoke(Request $request, string $entity): StreamedResponse
    {
        // IMEX-003: the same guarded rows serve CSV and native .xlsx.
        $format = $request->query('format') === 'xlsx' ? 'xlsx' : 'csv';

        $this->audit->log('data.exported', context: ['resource' => $entity, 'format' => $format], organizationId: $this->currentOrganization->id());

        $filename = $entity.'-'.now()->format('Y-m-d').'.'.$format;

        if ($format === 'xlsx') {
            $binary = Xlsx::write($this->rows($entity), ucfirst($entity));

            return response()->streamDownload(function () use ($binary) {
                echo $binary;
            }, $filename, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
        }

        return response()->streamDownload(function () use ($entity) {
            $out = fopen('php://output', 'w');

            foreach ($this->rows($entity) as $i => $row) {
                fputcsv($out, $i === 0 ? $row : Csv::row($row));
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * Header + data rows for one entity — the single source both formats use.
     *
     * @return list<array<int, mixed>>
     */
    private function rows(string $entity): array
    {
        $rows = [];
        $push = function (array $row) use (&$rows): void {
            $rows[] = $row;
        };

        match ($entity) {
            'companies' => $this->companies($push),
            'leads' => $this->leads($push),
            'keywords' => $this->keywords($push),
            'competitors' => $this->competitors($push),
            default => $this->deals($push),
        };

        return $rows;
    }

    /** @param  callable(array<int, mixed>): void  $push */
    private function companies(callable $push): void
    {
        $push(['Name', 'Domain', 'Industry', 'Phone', 'Website', 'Contacts', 'Deals']);
        Company::withCount('contacts', 'deals')->chunk(500, function ($companies) use ($push) {
            foreach ($companies as $company) {
                $push([
                    $company->name, $company->domain, $company->industry, $company->phone,
                    $company->website, $company->contacts_count, $company->deals_count,
                ]);
            }
        });
    }

    /** @param  callable(array<int, mixed>): void  $push */
    private function leads(callable $push): void
    {
        $push(['First name', 'Last name', 'Email', 'Phone', 'Company', 'Source', 'Status']);
        Lead::query()->chunk(500, function ($leads) use ($push) {
            foreach ($leads as $lead) {
                $push([
                    $lead->first_name, $lead->last_name, $lead->email, $lead->phone,
                    $lead->company_name, $lead->source, $lead->status,
                ]);
            }
        });
    }

    /** @param  callable(array<int, mixed>): void  $push */
    private function keywords(callable $push): void
    {
        $push(['Phrase', 'Intent', 'Search volume', 'Cluster', 'Mapped URL', 'Tracked', 'Current position']);
        Keyword::query()->chunk(500, function ($keywords) use ($push) {
            foreach ($keywords as $keyword) {
                $push([
                    $keyword->phrase, $keyword->intent, $keyword->search_volume, $keyword->cluster,
                    $keyword->mapped_url, $keyword->is_tracked ? 'yes' : 'no', $keyword->current_position,
                ]);
            }
        });
    }

    /** @param  callable(array<int, mixed>): void  $push */
    private function competitors(callable $push): void
    {
        $push(['Name', 'Domain', 'Notes', 'Tracked']);
        Competitor::query()->chunk(500, function ($competitors) use ($push) {
            foreach ($competitors as $competitor) {
                $push([
                    $competitor->name, $competitor->domain, $competitor->notes,
                    $competitor->is_tracked ? 'yes' : 'no',
                ]);
            }
        });
    }

    /** @param  callable(array<int, mixed>): void  $push */
    private function deals(callable $push): void
    {
        $push(['Name', 'Stage', 'Status', 'Value', 'MRR', 'ARR', 'Contact', 'Closed at']);
        Deal::with('stage:id,name', 'contact:id,first_name,last_name')->chunk(500, function ($deals) use ($push) {
            foreach ($deals as $deal) {
                $push([
                    $deal->name, $deal->stage?->name, $deal->status,
                    // Money in dollars on the way out, mirroring the forms.
                    number_format(((int) $deal->value) / 100, 2, '.', ''),
                    number_format(((int) $deal->mrr) / 100, 2, '.', ''),
                    number_format(((int) $deal->arr) / 100, 2, '.', ''),
                    $deal->contact?->fullName(),
                    $deal->closed_at?->toDateString(),
                ]);
            }
        });
    }
}
