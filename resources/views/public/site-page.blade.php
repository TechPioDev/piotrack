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

    // Every page needs a description: search engines write their own from the
    // body when one is missing, and it is rarely the sentence you would choose.
    $metaDescription = $page->meta_description
        ?: \Illuminate\Support\Str::limit(trim((string) ($standfirst ?: $page->title)), 155);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $page->meta_title ?: $page->title }}</title>
    <meta name="description" content="{{ $metaDescription }}">
    <link rel="canonical" href="{{ url('/s/'.$page->slug) }}">
    <meta name="robots" content="index,follow,max-image-preview:large">

    {{-- Shared links and previews are part of looking professional. --}}
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ $organization->name }}">
    <meta property="og:title" content="{{ $page->meta_title ?: $page->title }}">
    <meta property="og:url" content="{{ url('/s/'.$page->slug) }}">
    <meta property="og:description" content="{{ $metaDescription }}">
    <meta name="twitter:card" content="summary_large_image">

    {{-- Structured data. Carries the CSP nonce: the app allows no un-nonced
         inline script, and ld+json is still a script element to the browser. --}}
    @foreach ($schema as $block)
        <script type="application/ld+json" @if (! empty($cspNonce)) nonce="{{ $cspNonce }}" @endif>{!! json_encode($block, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
    @endforeach

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

        /* ---------- navigation ---------- */
        .nav { margin-left: auto; display: flex; flex-wrap: wrap; gap: .25rem 1.25rem; }
        .nav a { color: var(--muted); text-decoration: none; font-size: .95rem; font-weight: 520; }
        .nav a:hover, .nav a[aria-current="page"] { color: var(--ink); }
        .nav a[aria-current="page"] { font-weight: 640; }
        .call { color: var(--accent); font-weight: 640; text-decoration: none; white-space: nowrap; }

        /* ---------- breadcrumbs ---------- */
        .crumbs { font-size: .87rem; color: var(--muted); padding-block: 1rem 0; }
        .crumbs a { color: var(--muted); }
        .crumbs span { margin-inline: .45rem; opacity: .55; }

        /* ---------- contact / NAP ---------- */
        .nap { display: grid; gap: 1rem; grid-template-columns: 1fr; }
        @media (min-width: 46rem) { .nap { grid-template-columns: 1.2fr 1fr; align-items: start; } }
        .nap address { font-style: normal; line-height: 1.7; }
        .nap .label { font-size: .78rem; letter-spacing: .09em; text-transform: uppercase;
                      color: var(--muted); font-weight: 650; display: block; margin-bottom: .4rem; }

        /* ---------- related links ---------- */
        .related a {
            display: block; text-decoration: none; color: inherit;
            border: 1px solid var(--line); border-radius: var(--radius);
            padding: 1.15rem 1.25rem; background: var(--soft);
            transition: transform .15s ease, box-shadow .15s ease;
        }
        .related a:hover { transform: translateY(-2px); box-shadow: var(--shadow); }
        .related .context { display: block; font-size: .78rem; letter-spacing: .07em;
                            text-transform: uppercase; color: var(--accent); font-weight: 650; margin-bottom: .35rem; }
        .related .title { font-weight: 620; letter-spacing: -.01em; }
        .related .go { color: var(--muted); font-size: .9rem; }

        /* ---------- footer ---------- */
        .site-footer { border-top: 1px solid var(--line); color: var(--muted); font-size: .9rem; }
        .site-footer .wrap { padding-block: 2.25rem; display: grid; gap: 1rem; }
        .site-footer a { color: var(--muted); }
        .footer-links { display: flex; flex-wrap: wrap; gap: .35rem 1.25rem; }

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
        @if ($headerNav !== [] || $location?->phone)
            <nav class="nav" aria-label="Site">
                @foreach ($headerNav as $item)
                    <a href="{{ $item['href'] }}" @if ($item['current']) aria-current="page" @endif>{{ $item['label'] }}</a>
                @endforeach
                @if ($location?->phone)
                    <a class="call" href="tel:{{ preg_replace('/[^0-9+]/', '', $location->phone) }}">{{ $location->phone }}</a>
                @endif
            </nav>
        @endif
    </div>
</header>

<main>
    <nav class="crumbs" aria-label="Breadcrumb">
        <div class="wrap">
            <a href="{{ url('/') }}">{{ $organization->name }}</a>
            <span aria-hidden="true">/</span>
            <span aria-current="page">{{ $page->title }}</span>
        </div>
    </nav>

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

    @if ($related !== [])
        <section class="related">
            <div class="wrap">
                <h2>Explore more</h2>
                <p class="section-lead">Other services and locations we cover.</p>
                <ul class="grid three">
                    @foreach ($related as $item)
                        <li>
                            <a href="{{ $item['href'] }}">
                                @if ($item['context'])<span class="context">{{ $item['context'] }}</span>@endif
                                <span class="title">{{ $item['title'] }}</span>
                                <span class="go">&rarr;</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        </section>
    @endif

    @if ($location)
        <section>
            <div class="wrap nap">
                <div>
                    <h2>Talk to us</h2>
                    <p class="section-lead">
                        Local {{ $location->city ? $location->city.' ' : '' }}support, from people you can actually reach.
                    </p>
                    @if ($formUrl)
                        <a class="btn" href="{{ $formUrl }}">Get in touch</a>
                    @endif
                </div>
                <div>
                    <span class="label">{{ $location->name }}</span>
                    {{-- Name, address and phone in markup search engines can read:
                         this is what local results are matched and ranked on. --}}
                    @php
                        // Assembled here rather than with adjacent @if/@endif pairs:
                        // Blade will not compile an @if that directly follows @endif
                        // with no whitespace, and the orphaned @endif breaks the view.
                        $locality = implode(', ', array_filter([$location->city, $location->region]));
                        $postal = implode(' · ', array_filter([$location->postal_code, $location->country]));
                    @endphp
                    <address>
                        @if ($location->street){{ $location->street }}<br>@endif
                        @if ($locality){{ $locality }}<br>@endif
                        @if ($postal){{ $postal }}@endif
                        @if ($location->phone)
                            <br><a class="call" href="tel:{{ preg_replace('/[^0-9+]/', '', $location->phone) }}">{{ $location->phone }}</a>
                        @endif
                        @if ($location->website)
                            {{-- An outbound link to the tenant's own site: rel=me states
                                 that both belong to the same organisation. --}}
                            <br><a href="{{ $location->website }}" rel="me noopener" target="_blank">{{ preg_replace('#^https?://#', '', $location->website) }}</a>
                        @endif
                    </address>
                </div>
            </div>
        </section>
    @endif
</main>

<footer class="site-footer">
    <div class="wrap">
        @if ($footerNav !== [])
            <nav class="footer-links" aria-label="Footer">
                @foreach ($footerNav as $item)
                    <a href="{{ $item['href'] }}">{{ $item['label'] }}</a>
                @endforeach
            </nav>
        @endif
        <div>&copy; {{ date('Y') }} {{ $organization->name }}</div>
    </div>
</footer>
</body>
</html>
