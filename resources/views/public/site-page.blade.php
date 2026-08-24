{{--
    A published website page (WEB).

    This is a prospect's first impression of the tenant, so it is designed rather
    than merely rendered: a real type scale, a considered palette, and a distinct
    treatment per section type. Still server-rendered with zero JavaScript — it
    must load instantly, index cleanly and work with scripting off.

    Self-contained by necessity as well as choice: the app sends a strict CSP
    (font-src 'self' data:), so no external font or stylesheet would load. The
    type is a well-set system stack, and the character comes from scale, weight
    and spacing instead.

    Tenants with a brand palette get their own accent; everyone else gets a
    considered default rather than a browser blue.
--}}
@php
    $palette = $brand?->palette ?? [];
    $accent = preg_match('/^#[0-9a-f]{6}$/i', (string) ($palette['primary'] ?? '')) ? $palette['primary'] : '#0d7a6f';

    // The page's own headline is the H1 — it is the authored page-level field and
    // must never be dropped. A hero section is only skipped when it repeats that
    // headline verbatim, which is what printed the same sentence twice, once as
    // the H1 and again as an H2 directly beneath it. A hero saying something
    // different still has content worth showing, so it renders as a section.
    $headline = $page->headline ?: $page->title;

    $echoedHero = $sections->first(fn ($s) => $s->type === 'hero'
        && trim((string) $s->heading) !== ''
        && trim((string) $s->heading) === trim((string) $headline));

    $body = $echoedHero ? $sections->reject(fn ($s) => $s->is($echoedHero)) : $sections;
    $standfirst = $page->subheadline ?: $echoedHero?->body;
    $formUrl = $page->form_id && optional($page->form)->slug ? url('/f/'.$page->form->slug) : null;
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $page->meta_title ?: $page->title }}</title>
    @if ($page->meta_description)
        <meta name="description" content="{{ $page->meta_description }}">
    @endif
    <link rel="canonical" href="{{ url('/s/'.$page->slug) }}">

    {{-- Shared links and previews are part of looking professional. --}}
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ $organization->name }}">
    <meta property="og:title" content="{{ $page->meta_title ?: $page->title }}">
    <meta property="og:url" content="{{ url('/s/'.$page->slug) }}">
    @if ($page->meta_description)
        <meta property="og:description" content="{{ $page->meta_description }}">
    @endif
    <meta name="twitter:card" content="summary_large_image">

    <style>
        :root {
            --accent: {{ $accent }};
            --ink: #111c2b;
            --muted: #5a6b80;
            --line: #e2eaea;
            --bg: #ffffff;
            --soft: #f5f8f8;
            --on-accent: #ffffff;
            --shadow: 0 1px 2px rgb(17 28 43 / .04), 0 8px 24px -12px rgb(17 28 43 / .12);
            --radius: 14px;
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --ink: #e8eef2;
                --muted: #93a6b8;
                --line: #22303d;
                --bg: #0c1219;
                --soft: #121b24;
                --shadow: 0 1px 2px rgb(0 0 0 / .3), 0 8px 24px -12px rgb(0 0 0 / .6);
            }
        }

        *, *::before, *::after { box-sizing: border-box; }
        html { -webkit-text-size-adjust: 100%; }
        body {
            margin: 0;
            background: var(--bg);
            color: var(--ink);
            font: 400 17px/1.65 ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto,
                  "Helvetica Neue", Arial, "Noto Sans", sans-serif;
            -webkit-font-smoothing: antialiased;
        }
        .wrap { max-width: 68rem; margin: 0 auto; padding: 0 1.5rem; }

        /* ---------- masthead ---------- */
        .masthead { border-bottom: 1px solid var(--line); background: var(--bg); }
        .masthead .wrap { display: flex; align-items: center; gap: .75rem; padding-block: 1.15rem; }
        .mark {
            width: 30px; height: 30px; border-radius: 8px; flex: none;
            background: var(--accent); color: var(--on-accent);
            display: grid; place-items: center; font-weight: 700; font-size: .9rem;
        }
        .org { font-weight: 650; letter-spacing: -.01em; }

        /* ---------- hero ---------- */
        .hero {
            padding-block: clamp(3.5rem, 9vw, 6.5rem);
            background:
                radial-gradient(60rem 30rem at 15% -20%, color-mix(in srgb, var(--accent) 12%, transparent), transparent 60%),
                var(--bg);
            border-bottom: 1px solid var(--line);
        }
        .eyebrow {
            display: inline-block; margin-bottom: 1.1rem;
            font-size: .78rem; font-weight: 650; letter-spacing: .09em; text-transform: uppercase;
            color: var(--accent);
        }
        h1 {
            margin: 0;
            font-size: clamp(2.2rem, 6.2vw, 4rem);
            line-height: 1.05;
            letter-spacing: -.032em;
            font-weight: 700;
            text-wrap: balance;
            max-width: 20ch;
        }
        .standfirst {
            margin: 1.25rem 0 0;
            font-size: clamp(1.08rem, 2.1vw, 1.3rem);
            line-height: 1.55;
            color: var(--muted);
            max-width: 46ch;
            text-wrap: pretty;
        }

        /* ---------- buttons ---------- */
        .btn {
            display: inline-block; margin-top: 2rem;
            background: var(--accent); color: var(--on-accent);
            text-decoration: none; font-weight: 640; font-size: 1rem;
            padding: .9rem 1.6rem; border-radius: 10px;
            box-shadow: var(--shadow);
            transition: transform .15s ease, filter .15s ease;
        }
        .btn:hover { filter: brightness(1.07); transform: translateY(-1px); }
        .btn:focus-visible { outline: 3px solid color-mix(in srgb, var(--accent) 45%, transparent); outline-offset: 3px; }
        .btn.inverse { background: var(--bg); color: var(--accent); }

        /* ---------- sections ---------- */
        section { padding-block: clamp(3rem, 7vw, 5rem); }
        section + section { border-top: 1px solid var(--line); }
        h2 {
            margin: 0 0 .6rem;
            font-size: clamp(1.5rem, 3.4vw, 2.15rem);
            line-height: 1.15; letter-spacing: -.022em; font-weight: 680;
            text-wrap: balance;
        }
        .section-lead { margin: 0 0 2rem; color: var(--muted); max-width: 52ch; }

        .grid { display: grid; gap: 1rem; grid-template-columns: 1fr; margin: 0; padding: 0; list-style: none; }
        @media (min-width: 40rem) { .grid.two { grid-template-columns: repeat(2, 1fr); } }
        @media (min-width: 52rem) { .grid.three { grid-template-columns: repeat(3, 1fr); } }

        .card {
            border: 1px solid var(--line); border-radius: var(--radius);
            padding: 1.4rem 1.4rem 1.5rem; background: var(--soft);
            font-weight: 560; letter-spacing: -.005em;
            transition: transform .15s ease, box-shadow .15s ease;
        }
        .card:hover { transform: translateY(-2px); box-shadow: var(--shadow); }
        .card .tick {
            display: block; width: 26px; height: 26px; margin-bottom: .8rem; border-radius: 7px;
            background: color-mix(in srgb, var(--accent) 14%, transparent);
            color: var(--accent); font-size: .95rem; line-height: 26px; text-align: center; font-weight: 700;
        }

        /* ---------- testimonials ---------- */
        .quote {
            border: 1px solid var(--line); border-radius: var(--radius);
            padding: 1.6rem; background: var(--bg); position: relative;
        }
        .quote::before {
            content: "\201C"; position: absolute; top: .35rem; left: 1rem;
            font-size: 3.2rem; line-height: 1; color: var(--accent); opacity: .28;
        }
        .quote p { margin: .6rem 0 0; font-size: 1.06rem; line-height: 1.55; }
        .quote cite { display: block; margin-top: .9rem; font-style: normal; font-weight: 620; font-size: .92rem; }
        .quote cite span { color: var(--muted); font-weight: 400; }

        /* ---------- closing call to action ---------- */
        .cta-band {
            border: 0; border-radius: var(--radius);
            background: var(--accent); color: var(--on-accent);
            padding: clamp(2.2rem, 5vw, 3.4rem); text-align: center;
        }
        .cta-band h2 { color: var(--on-accent); margin-bottom: .5rem; }
        .cta-band p { margin: 0 auto; max-width: 48ch; opacity: .92; }

        /* ---------- footer ---------- */
        .site-footer { border-top: 1px solid var(--line); color: var(--muted); font-size: .9rem; }
        .site-footer .wrap { padding-block: 2.25rem; }

        @media (prefers-reduced-motion: reduce) {
            * { transition: none !important; }
        }
    </style>
</head>
<body>
<header class="masthead">
    <div class="wrap">
        <span class="mark" aria-hidden="true">{{ mb_strtoupper(mb_substr($organization->name, 0, 1)) }}</span>
        <span class="org">{{ $organization->name }}</span>
    </div>
</header>

<main>
    <div class="hero">
        <div class="wrap">
            @if ($page->type && $page->type !== 'landing')
                <span class="eyebrow">{{ str_replace('_', ' ', $page->type) }}</span>
            @endif
            <h1>{{ $headline }}</h1>
            @if ($standfirst)
                <p class="standfirst">{{ $standfirst }}</p>
            @endif
            @if ($formUrl)
                <a class="btn" href="{{ $formUrl }}">Get in touch</a>
            @endif
        </div>
    </div>

    @foreach ($body as $section)
        @php $items = array_values(array_filter((array) ($section->settings['items'] ?? []))); @endphp

        @if ($section->type === 'cta' || $section->type === 'offer')
            <section>
                <div class="wrap">
                    <div class="cta-band">
                        @if ($section->heading)<h2>{{ $section->heading }}</h2>@endif
                        @if ($section->body)<p>{{ $section->body }}</p>@endif
                        @if ($formUrl)
                            <a class="btn inverse" href="{{ $formUrl }}">Get in touch</a>
                        @endif
                    </div>
                </div>
            </section>

        @elseif (in_array($section->type, ['testimonials', 'reviews', 'case_studies'], true) && $items !== [])
            <section>
                <div class="wrap">
                    @if ($section->heading)<h2>{{ $section->heading }}</h2>@endif
                    @if ($section->body)<p class="section-lead">{{ $section->body }}</p>@endif
                    <ul class="grid two">
                        @foreach ($items as $item)
                            @php
                                $text = is_array($item) ? ($item['label'] ?? '') : (string) $item;
                                // Stored as: "Quote." — Attribution
                                $parts = preg_split('/\s+[—–-]\s+/u', $text, 2);
                                $quote = trim($parts[0] ?? '', " \"“”");
                                $who = trim($parts[1] ?? '');
                            @endphp
                            <li class="quote">
                                <p>{{ $quote }}</p>
                                @if ($who)<cite>{{ $who }}</cite>@endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            </section>

        @elseif (in_array($section->type, ['services', 'logos', 'trust', 'awards'], true) && $items !== [])
            <section>
                <div class="wrap">
                    @if ($section->heading)<h2>{{ $section->heading }}</h2>@endif
                    @if ($section->body)<p class="section-lead">{{ $section->body }}</p>@endif
                    <ul class="grid {{ count($items) > 2 ? 'three' : 'two' }}">
                        @foreach ($items as $item)
                            <li class="card">
                                <span class="tick" aria-hidden="true">&checkmark;</span>
                                {{ is_array($item) ? ($item['label'] ?? '') : $item }}
                            </li>
                        @endforeach
                    </ul>
                </div>
            </section>

        @else
            <section>
                <div class="wrap">
                    @if ($section->heading)<h2>{{ $section->heading }}</h2>@endif
                    @if ($section->body)<p class="section-lead">{{ $section->body }}</p>@endif
                </div>
            </section>
        @endif
    @endforeach
</main>

<footer class="site-footer">
    <div class="wrap">&copy; {{ date('Y') }} {{ $organization->name }}</div>
</footer>
</body>
</html>
