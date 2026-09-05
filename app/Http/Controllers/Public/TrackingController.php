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
        utm_campaign: q.get('utm_campaign') || undefined,
        utm_term: q.get('utm_term') || undefined,
        utm_content: q.get('utm_content') || undefined
    });
    w.piotrack = w.piotrack || {};
    w.piotrack.identify = function (email) { if (email) { send({ type: 'identify', email: String(email).slice(0, 255) }); } };
    // CRO-010/011: click positions (viewport-x% x document-y%) + element label.
    function docH() { var b = d.body, e = d.documentElement; return Math.max(b.scrollHeight, e.scrollHeight, e.clientHeight, 1); }
    d.addEventListener('click', function (ev) {
        var t = ev.target && ev.target.closest ? (ev.target.closest('a,button,input,select,textarea,label') || ev.target) : ev.target;
        var label = t && t.tagName ? (t.tagName.toLowerCase() + ' ' + (t.innerText || t.value || '').trim().slice(0, 60)).trim() : '';
        send({
            type: 'click',
            path: w.location.pathname,
            title: label.slice(0, 200),
            x_pct: Math.max(0, Math.min(100, Math.round(ev.clientX / Math.max(w.innerWidth, 1) * 100))),
            y_pct: Math.max(0, Math.min(100, Math.round((ev.clientY + w.scrollY) / docH() * 100)))
        });
    }, { capture: true, passive: true });
    // CRO-014: max scroll depth, sent once when the page is left.
    var depth = 0, sentDepth = false;
    w.addEventListener('scroll', function () {
        var p = Math.round((w.scrollY + w.innerHeight) / docH() * 100);
        if (p > depth) { depth = Math.min(p, 100); }
    }, { passive: true });
    w.addEventListener('pagehide', function () {
        if (!sentDepth) { sentDepth = true; send({ type: 'scroll', path: w.location.pathname, y_pct: depth }); }
    });
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
            'type' => ['required', 'in:pageview,identify,click,scroll'],
            'path' => ['nullable', 'string', 'max:300'],
            'title' => ['nullable', 'string', 'max:200'],
            'referrer' => ['nullable', 'string', 'max:300'],
            'email' => ['required_if:type,identify', 'nullable', 'email', 'max:255'],
            'utm_source' => ['nullable', 'string', 'max:120'],
            'utm_medium' => ['nullable', 'string', 'max:120'],
            'utm_campaign' => ['nullable', 'string', 'max:120'],
            // ATTR-006/011: keyword and ad-creative first-touch dimensions.
            'utm_term' => ['nullable', 'string', 'max:120'],
            'utm_content' => ['nullable', 'string', 'max:120'],
            // CRO-010/014: click position and scroll depth, both 0–100.
            'x_pct' => ['nullable', 'integer', 'min:0', 'max:100'],
            'y_pct' => ['nullable', 'integer', 'min:0', 'max:100'],
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
