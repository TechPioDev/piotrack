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

    public function __invoke(string $entity): StreamedResponse
    {
        $this->audit->log('data.exported', context: ['resource' => $entity], organizationId: $this->currentOrganization->id());

        $filename = $entity.'-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($entity) {
            $out = fopen('php://output', 'w');

            match ($entity) {
                'companies' => $this->companies($out),
                'leads' => $this->leads($out),
                'keywords' => $this->keywords($out),
                'competitors' => $this->competitors($out),
                default => $this->deals($out),
            };

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /** @param  resource  $out */
    private function companies($out): void
    {
        fputcsv($out, ['Name', 'Domain', 'Industry', 'Phone', 'Website', 'Contacts', 'Deals']);
        Company::withCount('contacts', 'deals')->chunk(500, function ($companies) use ($out) {
            foreach ($companies as $company) {
                fputcsv($out, Csv::row([
                    $company->name, $company->domain, $company->industry, $company->phone,
                    $company->website, $company->contacts_count, $company->deals_count,
                ]));
            }
        });
    }

    /** @param  resource  $out */
    private function leads($out): void
    {
        fputcsv($out, ['First name', 'Last name', 'Email', 'Phone', 'Company', 'Source', 'Status']);
        Lead::query()->chunk(500, function ($leads) use ($out) {
            foreach ($leads as $lead) {
                fputcsv($out, Csv::row([
                    $lead->first_name, $lead->last_name, $lead->email, $lead->phone,
                    $lead->company_name, $lead->source, $lead->status,
                ]));
            }
        });
    }

    /** @param  resource  $out */
    private function keywords($out): void
    {
        fputcsv($out, ['Phrase', 'Intent', 'Search volume', 'Cluster', 'Mapped URL', 'Tracked', 'Current position']);
        Keyword::query()->chunk(500, function ($keywords) use ($out) {
            foreach ($keywords as $keyword) {
                fputcsv($out, Csv::row([
                    $keyword->phrase, $keyword->intent, $keyword->search_volume, $keyword->cluster,
                    $keyword->mapped_url, $keyword->is_tracked ? 'yes' : 'no', $keyword->current_position,
                ]));
            }
        });
    }

    /** @param  resource  $out */
    private function competitors($out): void
    {
        fputcsv($out, ['Name', 'Domain', 'Notes', 'Tracked']);
        Competitor::query()->chunk(500, function ($competitors) use ($out) {
            foreach ($competitors as $competitor) {
                fputcsv($out, Csv::row([
                    $competitor->name, $competitor->domain, $competitor->notes,
                    $competitor->is_tracked ? 'yes' : 'no',
                ]));
            }
        });
    }

    /** @param  resource  $out */
    private function deals($out): void
    {
        fputcsv($out, ['Name', 'Stage', 'Status', 'Value', 'MRR', 'ARR', 'Contact', 'Closed at']);
        Deal::with('stage:id,name', 'contact:id,first_name,last_name')->chunk(500, function ($deals) use ($out) {
            foreach ($deals as $deal) {
                fputcsv($out, Csv::row([
                    $deal->name, $deal->stage?->name, $deal->status,
                    // Money in dollars on the way out, mirroring the forms.
                    number_format(((int) $deal->value) / 100, 2, '.', ''),
                    number_format(((int) $deal->mrr) / 100, 2, '.', ''),
                    number_format(((int) $deal->arr) / 100, 2, '.', ''),
                    $deal->contact?->fullName(),
                    $deal->closed_at?->toDateString(),
                ]));
            }
        });
    }
}
