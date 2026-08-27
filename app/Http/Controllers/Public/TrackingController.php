<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Services\Sales\VisitorTracker;
use App\Support\CurrentOrganization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * The public tracker (VINT): /t/{key}.js serves the ~1KB pixel, /t/{key}/e
 * ingests its events. The tracking key resolves the tenant (the established
 * public-endpoint pattern); everything else is validated, throttled and
 * tenant-scoped downstream.
 */
class TrackingController extends Controller
{
    public function __construct(
        private CurrentOrganization $currentOrganization,
        private VisitorTracker $tracker,
    ) {}

    public function script(string $key): Response
    {
        // 404 for an unknown key so the script cannot be used to probe tenants.
        Organization::where('tracking_key', $key)->firstOrFail();

        $endpoint = route('public.track.event', $key);

        $js = <<<JS
(function () {
    var d = document, w = window;
    function vid() {
        var m = d.cookie.match(/(?:^|; )_pt_vid=([a-z0-9]+)/);
        var v = m ? m[1] : null;
        if (!v) { try { v = localStorage.getItem('_pt_vid'); } catch (e) {}
        }
        if (!v) { v = Array.from(crypto.getRandomValues(new Uint8Array(16))).map(function (b) { return (b % 36).toString(36); }).join(''); }
        d.cookie = '_pt_vid=' + v + '; path=/; max-age=31536000; SameSite=Lax';
        try { localStorage.setItem('_pt_vid', v); } catch (e) {}
        return v;
    }
    var id = vid();
    function send(payload) {
        payload.vid = id;
        try {
            var body = JSON.stringify(payload);
            if (navigator.sendBeacon) { navigator.sendBeacon('$endpoint', new Blob([body], { type: 'application/json' })); }
            else { fetch('$endpoint', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: body, keepalive: true }); }
        } catch (e) {}
    }
    var q = new URLSearchParams(w.location.search);
    send({
        type: 'pageview',
        path: w.location.pathname,
        title: d.title.slice(0, 200),
        referrer: d.referrer.slice(0, 300),
        utm_source: q.get('utm_source') || undefined,
        utm_medium: q.get('utm_medium') || undefined,
        utm_campaign: q.get('utm_campaign') || undefined
    });
    w.piotrack = w.piotrack || {};
    w.piotrack.identify = function (email) { if (email) { send({ type: 'identify', email: String(email).slice(0, 255) }); } };
})();
JS;

        return response($js, 200, [
            'Content-Type' => 'application/javascript; charset=utf-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    public function event(Request $request, string $key): JsonResponse
    {
        $organization = Organization::where('tracking_key', $key)->firstOrFail();

        $data = $request->validate([
            'vid' => ['required', 'string', 'regex:/^[a-z0-9]{8,64}$/'],
            'type' => ['required', 'in:pageview,identify'],
            'path' => ['nullable', 'string', 'max:300'],
            'title' => ['nullable', 'string', 'max:200'],
            'referrer' => ['nullable', 'string', 'max:300'],
            'email' => ['required_if:type,identify', 'nullable', 'email', 'max:255'],
            'utm_source' => ['nullable', 'string', 'max:120'],
            'utm_medium' => ['nullable', 'string', 'max:120'],
            'utm_campaign' => ['nullable', 'string', 'max:120'],
        ]);

        $this->currentOrganization->set($organization);
        try {
            $this->tracker->ingest($data);
        } finally {
            $this->currentOrganization->forget();
        }

        return response()->json(['ok' => true]);
    }
}
