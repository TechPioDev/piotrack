<?php

namespace App\Services\Web;

use App\Models\AdCampaign;
use App\Models\BrandProfile;
use App\Models\Citation;
use App\Models\Contact;
use App\Models\ContentPiece;
use App\Models\Deal;
use App\Models\Keyword;
use App\Models\PageSection;
use App\Models\SeoLocation;
use App\Models\SitePage;
use App\Support\AuditLogger;
use Illuminate\Support\Collection;

/**
 * Multi-location support (MLOC). Extends the Stage 7 NAP location record with a
 * sales territory, its own Google Business Profile mapping and a location page,
 * then rolls results up per branch.
 *
 * Location-level attribution (MLOC-012) reuses the real CRM data: contacts and
 * deals are attributed to a branch by matching the contact's city to the
 * territory or city of the location, so a branch's numbers come from records
 * rather than a manual tally.
 */
class LocationService
{
    public function __construct(private AuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): SeoLocation
    {
        $location = SeoLocation::create($data + ['is_active' => true]);

        $this->audit->log('web.location.created', context: ['name' => $location->name, 'city' => $location->city],
            resourceType: 'seo_location', resourceId: (string) $location->id);

        return $location;
    }

    /**
     * Per-branch rollup: its page, NAP consistency inputs and attributed results.
     *
     * @return list<array<string, mixed>>
     */
    public function report(): array
    {
        return SeoLocation::orderBy('name')->get()->map(function (SeoLocation $location) {
            $contacts = $this->attributedContacts($location);
            $contactIds = $contacts->pluck('id')->all();

            $wonValue = $contactIds === [] ? 0 : (int) Deal::whereIn('contact_id', $contactIds)
                ->whereHas('stage', fn ($q) => $q->where('is_won', true))->sum('value');

            $citations = Citation::where('seo_location_id', $location->id)->get();

            return [
                'id' => $location->id,
                'name' => $location->name,
                'city' => $location->city,
                'region' => $location->region,
                'territory' => $location->territory,
                'is_active' => (bool) $location->is_active,
                'has_page' => SitePage::where('seo_location_id', $location->id)->exists(),
                'published_page' => SitePage::where('seo_location_id', $location->id)
                    ->where('status', SitePage::STATUS_PUBLISHED)->exists(),
                'leads' => $contacts->count(),
                'sqls' => $contacts->where('lifecycle_stage', 'sql')->count(),
                'won_value' => $wonValue,
                // MLOC-005: the branch's local SEO footprint from real records.
                'citations' => $citations->count(),
                'consistent_citations' => $citations->where('status', 'consistent')->count(),
                'geo_keywords' => $location->city !== null
                    ? Keyword::where('location', $location->city)->count()
                    : 0,
                // MLOC-006/008: branch-scoped campaigns and content.
                'campaigns' => AdCampaign::where('seo_location_id', $location->id)->count(),
                'active_campaigns' => AdCampaign::where('seo_location_id', $location->id)->where('status', 'active')->count(),
                'content_pieces' => ContentPiece::where('seo_location_id', $location->id)->count(),
            ];
        })->all();
    }

    /**
     * Regional content calendar (MLOC-008/011): campaigns and content pieces
     * grouped by the branch they target; unscoped items are the centralized
     * programme, listed under "Central" — one calendar shows both.
     *
     * @return list<array{location: string, entries: list<array{month: string, kind: string, title: string, status: string}>}>
     */
    public function regionalCalendar(): array
    {
        $names = SeoLocation::orderBy('name')->pluck('name', 'id');
        $groups = [];

        foreach (ContentPiece::orderBy('id')->get() as $piece) {
            $when = $piece->published_at ?? $piece->created_at;
            $groups[$piece->seo_location_id][] = [
                'month' => $when?->format('M Y') ?? '—',
                'kind' => 'content',
                'title' => $piece->title,
                'status' => $piece->status,
            ];
        }

        foreach (AdCampaign::orderBy('id')->get() as $campaign) {
            $groups[$campaign->seo_location_id][] = [
                'month' => $campaign->start_date?->format('M Y') ?? '—',
                'kind' => 'campaign',
                'title' => $campaign->name,
                'status' => $campaign->status,
            ];
        }

        $out = [];
        foreach ($groups as $locationId => $entries) {
            // A null binding becomes the '' array key — that is the central bucket.
            $out[] = [
                'location' => is_int($locationId) ? ($names[$locationId] ?? 'Central') : 'Central',
                'entries' => $entries,
            ];
        }

        usort($out, fn (array $a, array $b) => ($a['location'] === 'Central' ? 0 : 1) <=> ($b['location'] === 'Central' ? 0 : 1)
            ?: strcmp($a['location'], $b['location']));

        return $out;
    }

    /**
     * Brand consistency per branch (MLOC-010): deterministic checks on
     * first-party data only. Each failing check names its fix; no check
     * guesses at tone or design.
     *
     * @return list<array{location: string, ok: bool, checks: list<array{key: string, label: string, ok: bool, detail: string}>}>
     */
    public function brandCompliance(): array
    {
        $brand = BrandProfile::first();

        return SeoLocation::where('is_active', true)->orderBy('name')->get()->map(function (SeoLocation $location) use ($brand) {
            $page = SitePage::where('seo_location_id', $location->id)
                ->where('status', SitePage::STATUS_PUBLISHED)->first();

            $checks = [];

            $checks[] = $this->check('page', 'Published location page',
                $page !== null,
                'The branch has a live page.',
                'Publish a location page for this branch — without one there is nothing to keep consistent.');

            $checks[] = $this->check('nap', 'Complete NAP record',
                $location->street !== null && $location->city !== null && $location->phone !== null,
                'Street, city and phone are on record.',
                'Complete the branch street, city and phone — local search matches on them.');

            if ($page !== null) {
                $title = mb_strtolower($page->title.' '.($page->meta_title ?? ''));
                $checks[] = $this->check('title', 'Page names the market',
                    ($location->city !== null && str_contains($title, mb_strtolower($location->city)))
                        || str_contains($title, mb_strtolower($location->name)),
                    'The page title carries the branch city or name.',
                    "Put \"{$location->city}\" in the page title — a location page that never names its market ranks for nothing.");

                $checks[] = $this->check('description', 'Meta description',
                    ($page->meta_description ?? '') !== '',
                    'Meta description present.',
                    'Write the meta description — it is the search snippet for this branch.');

                if ($brand !== null && ($brand->tagline ?? '') !== '') {
                    $inSections = PageSection::where('site_page_id', $page->id)
                        ->get()
                        ->contains(fn (PageSection $s) => str_contains(
                            mb_strtolower(($s->heading ?? '').' '.($s->body ?? '')),
                            mb_strtolower($brand->tagline),
                        ));
                    $checks[] = $this->check('tagline', 'Brand tagline present',
                        $inSections,
                        'The brand tagline appears on the page.',
                        "Add the brand tagline (\"{$brand->tagline}\") so every branch speaks with one voice.");
                }
            }

            return [
                'location' => $location->name,
                'ok' => ! in_array(false, array_column($checks, 'ok'), true),
                'checks' => $checks,
            ];
        })->all();
    }

    /**
     * @return array{key: string, label: string, ok: bool, detail: string}
     */
    private function check(string $key, string $label, bool $ok, string $okDetail, string $fix): array
    {
        return ['key' => $key, 'label' => $label, 'ok' => $ok, 'detail' => $ok ? $okDetail : $fix];
    }

    /**
     * Contacts attributed to a branch, through their company's city or region —
     * contacts carry no address of their own, the company does.
     *
     * A contact with no company, or whose company has no matching city/region,
     * is deliberately left unattributed rather than assigned to the nearest
     * branch: a confident wrong attribution is worse than a visible gap.
     *
     * @return Collection<int, Contact>
     */
    public function attributedContacts(SeoLocation $location): Collection
    {
        $needles = array_values(array_filter([$location->territory, $location->city, $location->region]));

        if ($needles === []) {
            return collect();
        }

        return Contact::query()
            ->whereHas('company', function ($query) use ($needles) {
                $query->where(function ($inner) use ($needles) {
                    foreach ($needles as $needle) {
                        $inner->orWhere('city', $needle)->orWhere('region', $needle);
                    }
                });
            })
            ->get();
    }
}
