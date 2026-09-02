<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\Pipeline;

/**
 * Provisions per-organization CRM defaults. Every organization gets a default
 * sales pipeline with standard stages so deals can be created immediately.
 */
class CrmProvisioner
{
    /**
     * @var list<array{name: string, is_won?: bool, is_lost?: bool, is_proposal?: bool}>
     */
    private const DEFAULT_STAGES = [
        ['name' => 'New'],
        ['name' => 'Qualified'],
        ['name' => 'Proposal', 'is_proposal' => true],
        ['name' => 'Negotiation'],
        ['name' => 'Won', 'is_won' => true],
        ['name' => 'Lost', 'is_lost' => true],
    ];

    public function createDefaultPipeline(Organization $organization): Pipeline
    {
        $pipeline = Pipeline::create([
            'organization_id' => $organization->id,
            'name' => 'Sales Pipeline',
            'is_default' => true,
        ]);

        foreach (self::DEFAULT_STAGES as $i => $stage) {
            $pipeline->stages()->create([
                'name' => $stage['name'],
                'sort_order' => $i,
                'is_won' => $stage['is_won'] ?? false,
                'is_lost' => $stage['is_lost'] ?? false,
                'is_proposal' => $stage['is_proposal'] ?? false,
            ]);
        }

        return $pipeline;
    }
}
