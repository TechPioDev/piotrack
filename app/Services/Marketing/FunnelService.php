<?php

namespace App\Services\Marketing;

use App\Models\AdCampaign;
use App\Models\BookingPage;
use App\Models\Campaign;
use App\Models\Contact;
use App\Models\ContentPiece;
use App\Models\Form;
use App\Models\Funnel;
use App\Models\FunnelAsset;
use App\Models\FunnelStage;
use App\Models\LandingPage;
use App\Models\RetargetingAudience;
use App\Models\SitePage;
use App\Models\SocialPost;
use App\Models\Workflow;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * Funnel reporting and composition (FUNL). A funnel is a named, ordered set of
 * stages mapped to contact lifecycle stages; counts come from the contacts
 * currently in each mapped stage, and each stage carries the real platform
 * assets that work it (FUNL-001…018 as attachable types).
 *
 * Conversion is CUMULATIVE — contacts at-or-beyond the next stage over
 * contacts at-or-beyond this one — so a rate can never exceed 100% even though
 * lifecycle stages are states people move out of (the Phase-1 funnel lesson).
 */
class FunnelService
{
    /**
     * The attachable asset types: model, display column, and where the asset
     * lives in the product. The single source of truth for validation, options
     * and links.
     *
     * @var array<string, array{model: class-string<Model>, label: string, column: string, url: string}>
     */
    public const TYPES = [
        'content' => ['model' => ContentPiece::class, 'label' => 'Content piece', 'column' => 'title', 'url' => '/content/pieces/:id'],
        'social' => ['model' => SocialPost::class, 'label' => 'Social post', 'column' => 'body', 'url' => '/content/social'],
        'ad_campaign' => ['model' => AdCampaign::class, 'label' => 'Ad campaign', 'column' => 'name', 'url' => '/ads/campaigns/:id'],
        'retargeting' => ['model' => RetargetingAudience::class, 'label' => 'Retargeting audience', 'column' => 'name', 'url' => '/ads/retargeting'],
        'email_campaign' => ['model' => Campaign::class, 'label' => 'Email campaign', 'column' => 'name', 'url' => '/marketing/campaigns/:id'],
        'workflow' => ['model' => Workflow::class, 'label' => 'Workflow', 'column' => 'name', 'url' => '/marketing/automation/:id'],
        'landing_page' => ['model' => LandingPage::class, 'label' => 'Landing page', 'column' => 'name', 'url' => '/marketing/landing-pages'],
        'form' => ['model' => Form::class, 'label' => 'Form', 'column' => 'name', 'url' => '/marketing/forms'],
        'booking_page' => ['model' => BookingPage::class, 'label' => 'Booking page', 'column' => 'name', 'url' => '/sales/booking'],
        'site_page' => ['model' => SitePage::class, 'label' => 'Site page', 'column' => 'title', 'url' => '/website'],
    ];

    /**
     * @return list<array{id: int, name: string, category: string, count: int}>
     */
    public function stageCounts(Funnel $funnel): array
    {
        return $funnel->stages()->get()->map(fn ($stage) => [
            'id' => $stage->id,
            'name' => $stage->name,
            'category' => $stage->category,
            'count' => $stage->lifecycle_stage !== null
                ? Contact::where('lifecycle_stage', $stage->lifecycle_stage)->count()
                : 0,
        ])->all();
    }

    /**
     * Stages with cumulative counts, conversion, assets and gap flags.
     *
     * @return list<array<string, mixed>>
     */
    public function detail(Funnel $funnel): array
    {
        $order = array_flip(Contact::LIFECYCLE_STAGES);

        // One pass over the tenant's lifecycle distribution.
        $byStage = Contact::query()
            ->selectRaw('lifecycle_stage, count(*) as total')
            ->groupBy('lifecycle_stage')
            ->pluck('total', 'lifecycle_stage');

        $atOrBeyond = function (?string $lifecycle) use ($order, $byStage): ?int {
            if ($lifecycle === null || ! isset($order[$lifecycle])) {
                return null;
            }
            $index = $order[$lifecycle];

            return (int) collect($byStage)
                ->filter(fn ($n, $stage) => isset($order[$stage]) && $order[$stage] >= $index)
                ->sum();
        };

        $stages = $funnel->stages()->with('assets')->orderBy('position')->get();

        $rows = [];
        $previousBeyond = null;
        foreach ($stages as $stage) {
            $beyond = $atOrBeyond($stage->lifecycle_stage);
            $assets = $stage->assets->map(fn (FunnelAsset $asset) => $this->presentAsset($asset))->filter()->values()->all();

            $rows[] = [
                'id' => $stage->id,
                'name' => $stage->name,
                'category' => $stage->category,
                'lifecycle_stage' => $stage->lifecycle_stage,
                'count' => $stage->lifecycle_stage !== null ? (int) ($byStage[$stage->lifecycle_stage] ?? 0) : 0,
                'at_or_beyond' => $beyond,
                // Conversion from the PREVIOUS stage into this one, cumulative.
                'conversion_pct' => ($previousBeyond !== null && $previousBeyond > 0 && $beyond !== null)
                    ? round(min(100, $beyond / $previousBeyond * 100), 1)
                    : null,
                'assets' => $assets,
                'gap' => $assets === [],
            ];

            $previousBeyond = $beyond ?? $previousBeyond;
        }

        return $rows;
    }

    public function attach(FunnelStage $stage, string $type, int $assetId): FunnelAsset
    {
        $config = self::TYPES[$type] ?? null;
        if ($config === null) {
            throw ValidationException::withMessages(['asset_type' => __('Unknown asset type.')]);
        }

        // Tenant scope on the asset's own model: a foreign id simply does not exist here.
        if (! $config['model']::query()->whereKey($assetId)->exists()) {
            throw ValidationException::withMessages(['asset_id' => __('That record does not exist.')]);
        }

        return FunnelAsset::firstOrCreate([
            'funnel_stage_id' => $stage->id,
            'asset_type' => $type,
            'asset_id' => $assetId,
        ]);
    }

    /**
     * Attachable records per type for the pickers, capped and tenant-scoped.
     *
     * @return array<string, array{label: string, options: list<array{id: int, name: string}>}>
     */
    public function attachableOptions(): array
    {
        $out = [];

        foreach (self::TYPES as $type => $config) {
            $out[$type] = [
                'label' => $config['label'],
                'options' => $config['model']::query()
                    ->orderByDesc('id')->limit(100)
                    ->get(['id', $config['column']])
                    ->map(fn ($m) => ['id' => (int) $m->id, 'name' => str($m->{$config['column']} ?? '')->limit(60)->toString()])
                    ->all(),
            ];
        }

        return $out;
    }

    /**
     * @return array{id: int, type: string, type_label: string, name: string, url: string}|null
     */
    private function presentAsset(FunnelAsset $asset): ?array
    {
        $config = self::TYPES[$asset->asset_type] ?? null;
        if ($config === null) {
            return null;
        }

        $record = $config['model']::query()->find($asset->asset_id);
        if ($record === null) {
            return null; // the asset was deleted since attachment
        }

        return [
            'id' => $asset->id,
            'type' => $asset->asset_type,
            'type_label' => $config['label'],
            'name' => str($record->{$config['column']} ?? '')->limit(60)->toString(),
            'url' => str_replace(':id', (string) $record->id, $config['url']),
        ];
    }
}
