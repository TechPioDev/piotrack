<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Services\Web\FranchiseService;
use App\Support\CurrentOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Franchise management (MLOC-009). Linking is the sensitive act: it grants the
 * franchisor roll-up visibility into the child, so it demands one person who
 * owns BOTH organizations — an admin of the parent alone cannot pull a foreign
 * organization under it.
 */
class FranchiseController extends Controller
{
    public function __construct(
        private FranchiseService $franchise,
        private CurrentOrganization $current,
    ) {}

    public function index(Request $request): Response
    {
        $organization = $this->current->get();

        // Organizations the acting user owns and could link: independent, not
        // this one, not already parented, not franchisors themselves.
        $linkable = Organization::where('owner_id', $request->user()->id)
            ->whereKeyNot($organization->id)
            ->whereNull('parent_organization_id')
            ->whereDoesntHave('childOrganizations')
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('settings/franchise', [
            'parent' => $organization->parentOrganization()->first(['organizations.id', 'organizations.name'])?->only(['name']),
            'children' => $this->franchise->rollup($organization),
            'linkable' => ($organization->parent_organization_id === null && $organization->owner_id === $request->user()->id)
                ? $linkable
                : collect(),
            'isOwner' => $organization->owner_id === $request->user()->id,
        ]);
    }

    public function link(Request $request): RedirectResponse
    {
        $data = $request->validate(['organization_id' => ['required', 'integer']]);

        $parent = $this->current->get();
        $child = Organization::find($data['organization_id']);

        // One person must own both sides of the link.
        if ($child === null || $parent->owner_id !== $request->user()->id || $child->owner_id !== $request->user()->id) {
            throw ValidationException::withMessages(['organization_id' => __('You must own both organizations to link them.')]);
        }

        try {
            $this->franchise->linkChild($parent, $child);
        } catch (\RuntimeException $e) {
            throw ValidationException::withMessages(['organization_id' => $e->getMessage()]);
        }

        return back()->with('status', __(':name linked as a franchisee.', ['name' => $child->name]));
    }

    public function unlink(Request $request, Organization $child): RedirectResponse
    {
        $parent = $this->current->get();

        try {
            $this->franchise->unlinkChild($parent, $child);
        } catch (\RuntimeException $e) {
            abort(404);
        }

        return back()->with('status', __(':name unlinked.', ['name' => $child->name]));
    }

    public function pushBrand(Request $request, Organization $child): RedirectResponse
    {
        $parent = $this->current->get();

        try {
            $this->franchise->pushBrand($parent, $child);
        } catch (\RuntimeException $e) {
            if ($child->parent_organization_id !== $parent->id) {
                abort(404);
            }

            throw ValidationException::withMessages(['organization_id' => $e->getMessage()]);
        }

        return back()->with('status', __('Brand profile pushed to :name.', ['name' => $child->name]));
    }
}
