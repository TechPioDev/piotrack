<?php

namespace App\Services\Web;

use App\Models\Experiment;
use App\Models\ExperimentVariant;
use App\Models\SitePage;
use App\Services\Analytics\ExperimentService;

/**
 * Serving experiments on public site pages (WEB-033/034/035/037): the Stage 11
 * engine already measures conversion rate, lift and winners — this binds a
 * running experiment to a site_page so traffic is split automatically. A
 * visitor gets a sticky variant (cookie), the variant's content overrides are
 * applied in-memory (nothing is written to the page), the impression counts
 * once per visitor, and a form submission carrying the cookie converts.
 */
class PageExperiments
{
    public const COOKIE_PREFIX = 'pt_exp_';

    /** Sticky assignment lives this long (days). */
    public const COOKIE_DAYS = 30;

    public function __construct(private ExperimentService $experiments) {}

    /**
     * The variant this visitor should see on this page, or null when no
     * experiment is running here. A returning visitor keeps their variant and
     * is not re-counted; a new one is assigned randomly and counted once.
     *
     * @return array{variant: ExperimentVariant, experiment: Experiment, fresh: bool}|null
     */
    public function assign(SitePage $page, ?string $cookieValue): ?array
    {
        $experiment = Experiment::where('site_page_id', $page->id)->where('status', 'running')->first();
        if ($experiment === null) {
            return null;
        }

        $variants = $experiment->variants()->get();
        if ($variants->isEmpty()) {
            return null;
        }

        if ($cookieValue !== null) {
            $sticky = $variants->firstWhere('id', (int) $cookieValue);
            if ($sticky !== null) {
                return ['variant' => $sticky, 'experiment' => $experiment, 'fresh' => false];
            }
        }

        /** @var ExperimentVariant $variant */
        $variant = $variants->random();
        $this->experiments->record($variant, 1, 0);

        return ['variant' => $variant, 'experiment' => $experiment, 'fresh' => true];
    }

    /**
     * Apply a variant's content overrides in-memory — the stored page is the
     * control and never mutates.
     */
    public function apply(SitePage $page, ExperimentVariant $variant): void
    {
        $content = $variant->content ?? [];

        foreach (['headline', 'subheadline'] as $field) {
            if (! empty($content[$field])) {
                $page->setAttribute($field, (string) $content[$field]);
            }
        }
    }

    /**
     * Credit conversions for any experiment cookies riding a form submission
     * (WEB-037): the cookie was only ever set on the experiment's page, so its
     * presence ties the conversion to the exposure. Org-checked — a cookie
     * from another tenant's experiment converts nothing.
     *
     * @param  array<string, string>  $cookies  name => value
     * @return int variants credited
     */
    public function convertFromCookies(array $cookies): int
    {
        $credited = 0;

        foreach ($cookies as $name => $value) {
            if (! str_starts_with($name, self::COOKIE_PREFIX)) {
                continue;
            }

            $variant = ExperimentVariant::whereKey((int) $value)->first();
            if ($variant === null) {
                continue; // tenant scope already filtered foreign variants out
            }

            $experiment = $variant->experiment()->first();
            if ($experiment === null || $experiment->status !== 'running') {
                continue;
            }

            $this->experiments->record($variant, 0, 1);
            $credited++;
        }

        return $credited;
    }
}
