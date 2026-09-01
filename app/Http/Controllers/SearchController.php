<?php

namespace App\Http\Controllers;

use App\Services\GlobalSearch;
use App\Support\CurrentOrganization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function __construct(
        private GlobalSearch $search,
        private CurrentOrganization $currentOrganization,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $term = (string) $request->query('q', '');
        $user = $request->user();
        $organization = $this->currentOrganization->get();

        $groups = $this->search->search($user, $organization, $term);

        // SRCH-002: only searches that found something are worth suggesting
        // again; an empty query returns the recents for the palette to show.
        if ($groups !== []) {
            $this->search->rememberTerm($user, $organization, $term);
        }

        return response()->json([
            'groups' => $groups,
            'recent' => $this->search->recentTerms($user, $organization),
        ]);
    }
}
