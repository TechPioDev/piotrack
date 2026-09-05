{{-- PRIV-002: the first-party pixel loads only with analytics consent. The
     gate runs client-side (granted → load pixel, denied → nothing, undecided →
     banner), so the page's cacheable output never varies by cookie. --}}
<script @if (! empty($cspNonce)) nonce="{{ $cspNonce }}" @endif>
(function () {
    var d = document;
    var match = d.cookie.match(/(?:^|; )pt_consent=(\w+)/);
    var decision = match ? match[1] : null;

    function loadPixel() {
        var s = d.createElement('script');
        s.src = @json(route('public.track.script', $trackingKey));
        s.defer = true;
        d.body.appendChild(s);
    }

    function decide(value) {
        d.cookie = 'pt_consent=' + value + '; path=/; max-age=31536000; SameSite=Lax';
        try {
            navigator.sendBeacon(
                @json(route('public.track.consent', $trackingKey)),
                new Blob([JSON.stringify({ analytics: value === 'granted' })], { type: 'application/json' })
            );
        } catch (e) {}
        var banner = d.getElementById('pt-consent');
        if (banner) { banner.remove(); }
        if (value === 'granted') { loadPixel(); }
    }

    if (decision === 'granted') { loadPixel(); return; }
    if (decision === 'denied') { return; }

    var banner = d.createElement('div');
    banner.id = 'pt-consent';
    banner.setAttribute('role', 'dialog');
    banner.setAttribute('aria-label', 'Cookie preferences');
    banner.style.cssText = 'position:fixed;bottom:16px;left:16px;right:16px;max-width:520px;margin:0 auto;background:#1c2420;color:#fff;padding:14px 16px;border-radius:10px;font:14px/1.5 system-ui,sans-serif;z-index:9999;display:flex;gap:12px;align-items:center;flex-wrap:wrap;box-shadow:0 8px 30px rgba(0,0,0,.25)';
    var text = d.createElement('span');
    text.style.cssText = 'flex:1;min-width:220px';
    text.textContent = 'This site uses one first-party cookie to understand visits. No third-party trackers.';
    banner.appendChild(text);
    [['Essential only', 'denied', 'background:transparent;color:#fff;border:1px solid #5d6b64'],
     ['Allow', 'granted', 'background:#37b790;color:#0d1f18;border:1px solid #37b790']].forEach(function (spec) {
        var button = d.createElement('button');
        button.type = 'button';
        button.textContent = spec[0];
        button.style.cssText = 'padding:7px 14px;border-radius:8px;font:inherit;cursor:pointer;' + spec[2];
        button.addEventListener('click', function () { decide(spec[1]); });
        banner.appendChild(button);
    });
    d.body.appendChild(banner);
})();
</script>
