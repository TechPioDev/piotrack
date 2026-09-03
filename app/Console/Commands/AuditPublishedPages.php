<?php

namespace App\Console\Commands;

use App\Models\SitePage;
use App\Services\Web\SiteBuilderService;
use App\Support\CurrentOrganization;
use Illuminate\Console\Command;

/**
 * WEB-052/053: scheduled SEO monitoring of the pages themselves — every
 * published site page is re-audited daily through the Stage 7 auditor
 * (rank tracking already monitors their mapped keywords at 04:00).
 */
class AuditPublishedPages extends Command
{
    protected $signature = 'web:audit-published';

    protected $description = 'Run the technical SEO auditor against every published site page';

    public function handle(SiteBuilderService $builder, CurrentOrganization $current): int
    {
        $audited = 0;
        $skipped = 0;

        SitePage::withoutGlobalScope('tenant')
            ->where('status', SitePage::STATUS_PUBLISHED)
            ->with('organization')
            ->chunkById(100, function ($pages) use ($builder, $current, &$audited, &$skipped) {
                foreach ($pages as $page) {
                    if ($page->organization === null) {
                        $skipped++;

                        continue;
                    }

                    // Tenant context per page so the audit lands in its org.
                    $current->set($page->organization);
                    $builder->auditPublished($page) ? $audited++ : $skipped++;
                    $current->forget();
                }
            });

        $this->info("Audited {$audited} published pages, skipped {$skipped}.");

        return self::SUCCESS;
    }
}
