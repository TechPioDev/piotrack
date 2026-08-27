<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>@yield('title', 'Piotrack')</title>
    <style>
        :root { color-scheme: light; }
        * { box-sizing: border-box; }
        body {
            margin: 0; font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
            background: #f5f6f8; color: #1a1a1a; line-height: 1.5;
            display: flex; min-height: 100vh; align-items: center; justify-content: center; padding: 24px;
        }
        .card {
            background: #fff; border: 1px solid #e6e8eb; border-radius: 12px;
            padding: 32px; max-width: 560px; width: 100%;
            box-shadow: 0 1px 3px rgba(0,0,0,.06);
        }
        h1 { font-size: 22px; margin: 0 0 8px; }
        p.lead { color: #667085; margin: 0 0 24px; }
        label { display: block; font-size: 14px; font-weight: 600; margin: 16px 0 6px; }
        input, textarea {
            width: 100%; padding: 10px 12px; border: 1px solid #d0d5dd; border-radius: 8px;
            font-size: 15px; font-family: inherit;
        }
        textarea { min-height: 96px; resize: vertical; }
        .hp { position: absolute; left: -9999px; }
        button {
            margin-top: 24px; width: 100%; padding: 12px; border: 0; border-radius: 8px;
            background: #111; color: #fff; font-size: 15px; font-weight: 600; cursor: pointer;
        }
        button:hover { background: #333; }
        .error { color: #b42318; font-size: 13px; margin-top: 6px; }
        .muted { color: #98a2b3; font-size: 12px; margin-top: 24px; text-align: center; }
    </style>
</head>
<body>
    <div class="card">
        @yield('content')
        <p class="muted">Powered by Piotrack</p>
    </div>
    @php($trackingKey = app(\App\Support\CurrentOrganization::class)->get()?->tracking_key)
    @if ($trackingKey !== null)
        {{-- Visitor Intelligence pixel (VINT): first-party, same-origin here. --}}
        <script src="{{ route('public.track.script', $trackingKey) }}" defer></script>
    @endif
</body>
</html>
