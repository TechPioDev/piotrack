<?php

namespace App\Http\Controllers\Analytics;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Services\Analytics\AttributionService;
use Inertia\Inertia;
use Inertia\Response;

class AttributionController extends Controller
{
    public function index(AttributionService $attribution): Response
    {
        // A sample of recent contacts with their first/last touch, so the model is
        // demonstrable per-prospect alongside the aggregate rollups.
        $journeys = Contact::latest('id')->limit(25)->get()->map(fn (Contact $c) => [
            'id' => $c->id,
            'name' => $c->fullName(),
            'first_touch' => $attribution->firstTouch($c),
            'last_touch' => $attribution->lastTouch($c),
            'multi_touch' => $attribution->multiTouch($c),
        ]);

        return Inertia::render('analytics/attribution', [
            'channels' => $attribution->channelRevenue(),
            'campaigns' => $attribution->campaignRevenue(),
            'cac' => $attribution->cac(),
            'roi' => $attribution->marketingRoi(),
            'journeys' => $journeys,
            // ATTR-006..011: won revenue per keyword/ad/landing-page/content/form/call.
            'dimensions' => $attribution->dimensionAttribution(),
        ]);
    }
}
