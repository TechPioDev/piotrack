<?php

namespace App\Services\Analytics;

use App\Models\Experiment;
use App\Models\ExperimentVariant;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Conversion-rate optimization (CRO). A generic A/B experiment engine covering
 * landing-page/CTA/form/copy/headline/offer/layout test types: variants collect
 * impressions + conversions, and results expose per-variant conversion rate,
 * lift vs the control, and the current leader.
 */
class ExperimentService
{
    public function __construct(private AuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  list<array{name: string, is_control?: bool, content?: array<string, string>|null}>  $variants
     */
    public function create(array $data, array $variants): Experiment
    {
        $experiment = Experiment::create([
            'name' => $data['name'],
            'type' => $data['type'] ?? 'landing_page',
            'hypothesis' => $data['hypothesis'] ?? null,
            'primary_metric' => $data['primary_metric'] ?? 'conversion_rate',
            // WEB-033: bound to a page, the experiment serves itself on live traffic.
            'site_page_id' => $data['site_page_id'] ?? null,
            'status' => 'draft',
        ]);

        foreach ($variants as $i => $variant) {
            $experiment->variants()->create([
                'organization_id' => $experiment->organization_id,
                'name' => $variant['name'],
                'is_control' => $variant['is_control'] ?? ($i === 0),
                'content' => $variant['content'] ?? null,
            ]);
        }

        $this->audit->log('analytics.experiment.created', context: ['type' => $experiment->type], resourceType: 'experiment', resourceId: (string) $experiment->id);

        return $experiment->load('variants');
    }

    public function start(Experiment $experiment): Experiment
    {
        $experiment->update(['status' => 'running', 'started_at' => now()]);

        return $experiment;
    }

    /**
     * Record impressions and conversions against a variant (idempotent-additive).
     */
    public function record(ExperimentVariant $variant, int $impressions, int $conversions): ExperimentVariant
    {
        // Guard the resulting totals, not the increment: live serving records
        // an impression (1, 0) at exposure and the conversion (0, 1) later.
        if ($variant->conversions + $conversions > $variant->impressions + $impressions) {
            throw new RuntimeException('Conversions cannot exceed impressions.');
        }

        $variant->forceFill([
            'impressions' => $variant->impressions + $impressions,
            'conversions' => $variant->conversions + $conversions,
        ])->save();

        return $variant->refresh();
    }

    /**
     * Per-variant results: conversion rate, and lift vs the control variant.
     *
     * @return array<int, array{id: int, name: string, is_control: bool, impressions: int, conversions: int, conversion_rate: float, lift: float}>
     */
    public function results(Experiment $experiment): array
    {
        $variants = $experiment->variants()->get();
        $control = $variants->firstWhere('is_control', true) ?? $variants->first();
        $controlRate = $control ? $control->conversionRate() : 0.0;

        return $variants->map(function (ExperimentVariant $v) use ($controlRate) {
            $rate = $v->conversionRate();
            $lift = $controlRate > 0 ? round((($rate - $controlRate) / $controlRate) * 100, 2) : 0.0;

            return [
                'id' => $v->id,
                'name' => $v->name,
                'is_control' => $v->is_control,
                'impressions' => $v->impressions,
                'conversions' => $v->conversions,
                'conversion_rate' => round($rate * 100, 2),
                'lift' => $lift,
            ];
        })->all();
    }

    /**
     * The variant with the highest conversion rate (the leader). Null when no
     * variant has any impressions yet.
     */
    public function leader(Experiment $experiment): ?ExperimentVariant
    {
        return $experiment->variants()
            ->where('impressions', '>', 0)
            ->get()
            ->sortByDesc(fn (ExperimentVariant $v) => $v->conversionRate())
            ->first();
    }

    /**
     * Conclude the experiment, stamping the winning variant.
     */
    public function conclude(Experiment $experiment): Experiment
    {
        $leader = $this->leader($experiment);

        DB::transaction(function () use ($experiment, $leader) {
            $experiment->update([
                'status' => 'completed',
                'ended_at' => now(),
                'winning_variant_id' => $leader?->id,
            ]);
        });

        $this->audit->log('analytics.experiment.concluded', context: ['winner' => $leader?->id], resourceType: 'experiment', resourceId: (string) $experiment->id);

        return $experiment->refresh();
    }
}
