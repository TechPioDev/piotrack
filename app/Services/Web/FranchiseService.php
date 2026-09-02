<?php

namespace App\Services\Web;

use App\Models\BrandProfile;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\Organization;
use App\Models\SitePage;
use App\Support\AuditLogger;
use RuntimeException;

/**
 * Franchise support (MLOC-009): a franchisor organization with linked
 * franchisee children — hierarchy, roll-up reporting, and brand push-down.
 *
 * Cross-tenant reads here are deliberate and narrow: the roll-up queries drop
 * the tenant scope but pin organization_id to explicitly linked children (the
 * established pattern public controllers use), and the controller only lets a
 * user act on organizations they own. Nothing here widens what a normal
 * tenant-scoped query can see.
 */
class FranchiseService
{
    public function __construct(private AuditLogger $audit) {}

    /**
     * Link a franchisee under a franchisor. The caller must have verified the
     * acting user owns both — this method enforces the structural rules: no
     * self-link, no chains (a parent cannot itself be someone's child, and a
     * child with children cannot be linked).
     */
    public function linkChild(Organization $parent, Organization $child): void
    {
        if ($parent->id === $child->id) {
            throw new RuntimeException('An organization cannot franchise itself.');
        }
        if ($parent->parent_organization_id !== null) {
            throw new RuntimeException('A franchisee cannot take franchisees of its own.');
        }
        if ($child->parent_organization_id !== null) {
            throw new RuntimeException('That organization is already linked to a franchisor.');
        }
        if (Organization::where('parent_organization_id', $child->id)->exists()) {
            throw new RuntimeException('That organization is itself a franchisor.');
        }

        $child->forceFill(['parent_organization_id' => $parent->id])->save();

        $this->audit->log('franchise.linked', context: ['child' => $child->name], resourceType: 'organization', resourceId: (string) $child->id, organizationId: $parent->id);
    }

    public function unlinkChild(Organization $parent, Organization $child): void
    {
        if ($child->parent_organization_id !== $parent->id) {
            throw new RuntimeException('That organization is not one of your franchisees.');
        }

        $child->forceFill(['parent_organization_id' => null])->save();

        $this->audit->log('franchise.unlinked', context: ['child' => $child->name], resourceType: 'organization', resourceId: (string) $child->id, organizationId: $parent->id);
    }

    /**
     * Roll-up across the franchise (MLOC-009): each linked child's results
     * from its real records. Reads are pinned to linked child ids only.
     *
     * @return list<array<string, mixed>>
     */
    public function rollup(Organization $parent): array
    {
        return $parent->childOrganizations()->orderBy('name')->get()->map(function (Organization $child) {
            $contacts = Contact::withoutGlobalScope('tenant')->where('organization_id', $child->id);
            $wonValue = (int) Deal::withoutGlobalScope('tenant')->where('organization_id', $child->id)
                ->whereHas('stage', fn ($q) => $q->where('is_won', true))->sum('value');

            return [
                'id' => $child->id,
                'name' => $child->name,
                'contacts' => (clone $contacts)->count(),
                'sqls' => (clone $contacts)->where('lifecycle_stage', 'sql')->count(),
                'won_value' => $wonValue,
                'published_pages' => SitePage::withoutGlobalScope('tenant')->where('organization_id', $child->id)
                    ->where('status', SitePage::STATUS_PUBLISHED)->count(),
            ];
        })->all();
    }

    /**
     * Push the franchisor's brand profile down to a linked child (MLOC-009 /
     * MLOC-010): positioning, voice and the entity facts — so every
     * franchisee's pages draw on the same brand.
     */
    public function pushBrand(Organization $parent, Organization $child): void
    {
        if ($child->parent_organization_id !== $parent->id) {
            throw new RuntimeException('That organization is not one of your franchisees.');
        }

        $source = BrandProfile::withoutGlobalScope('tenant')->where('organization_id', $parent->id)->first();
        if ($source === null) {
            throw new RuntimeException('Define the franchisor brand profile before pushing it down.');
        }

        $fields = collect($source->getAttributes())
            ->except(['id', 'organization_id', 'created_at', 'updated_at'])
            ->all();

        BrandProfile::withoutGlobalScope('tenant')->updateOrCreate(
            ['organization_id' => $child->id],
            $fields,
        );

        $this->audit->log('franchise.brand_pushed', context: ['child' => $child->name], resourceType: 'organization', resourceId: (string) $child->id, organizationId: $parent->id);
    }
}
