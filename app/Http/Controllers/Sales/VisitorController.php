<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\Visitor;
use App\Support\CurrentOrganization;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Visitor Intelligence (VINT): who is on the website, identified or not,
 * and how hot they run. Opening the page the first time mints the
 * organization's tracking key so the install snippet is ready to copy.
 */
class VisitorController extends Controller
{
    public function __construct(private CurrentOrganization $currentOrganization) {}

    public function index(): Response
    {
        $organization = $this->currentOrganization->get();

        if ($organization->tracking_key === null) {
            $organization->forceFill(['tracking_key' => 'tk_'.Str::lower(Str::random(24))])->save();
        }

        $visitors = Visitor::with('contact:id,first_name,last_name,company_id', 'contact.company:id,name')
            ->orderByRaw('contact_id is null')
            ->orderByDesc('last_seen_at')
            ->paginate(25)
            ->through(fn (Visitor $visitor) => [
                'id' => $visitor->id,
                'label' => $visitor->contact !== null
                    ? $visitor->contact->fullName()
                    : 'Anonymous · '.substr($visitor->visitor_key, 0, 6),
                'contact_id' => $visitor->contact_id,
                'company' => $visitor->contact?->company?->name,
                'email' => $visitor->email,
                'visits' => $visitor->visits,
                'page_views' => $visitor->page_views,
                'intent_score' => $visitor->intent_score,
                'last_path' => $visitor->last_path,
                'source' => $visitor->utm_source ?? ($visitor->referrer !== null ? parse_url($visitor->referrer, PHP_URL_HOST) : null),
                'last_seen_at' => $visitor->last_seen_at?->diffForHumans(),
            ]);

        return Inertia::render('sales/visitors/index', [
            'visitors' => $visitors,
            'trackingKey' => $organization->tracking_key,
            'snippet' => '<script src="'.route('public.track.script', $organization->tracking_key).'" defer></script>',
            'identified' => Visitor::whereNotNull('contact_id')->count(),
            'total' => Visitor::count(),
        ]);
    }
}
