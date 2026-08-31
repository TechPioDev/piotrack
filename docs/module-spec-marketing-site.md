# Module Specification — Product Marketing Site (MSITE, "Module 11")

> Approved 2026-09-01. Separate, SEO-ready pages for piotrack.com: Features, How it works,
> Results, About, Contact, FAQ — US-market copy.

## Honesty constraints (agreed stance)

No invented client testimonials, case studies or performance numbers. "Results" presents what the
platform measurably proves (honest funnels, MRR attribution, AI-visibility evidence) plus a
founding-customer invitation; real client feedback gets added with Review schema when it exists.
No fabricated US office address in schema or copy.

## Pages & URLs

`/features` · `/how-it-works` · `/results` · `/about` · `/contact` · `/faq` — all sharing the
landing page's `.lp` design system through a new shared marketing layout (nav + footer + newsletter
reused). Homepage nav/footer link to the new pages.

## SEO

Per-page `<title>` (≤60 chars, keyword-led: "MSP marketing software", "MSP growth platform",
"AI visibility tracking for MSPs"…), meta description, JSON-LD (FAQPage on /faq, Organization on
/about, ContactPage on /contact), `sitemap.xml` route listing the public product pages, robots.txt
pointing at it. US-English copy targeting US MSP search intent; internal links between pages.

## Backend

- `contact_messages` table (platform-level: name, email, company?, message, source) +
  `POST /contact` — throttled, honeypot-guarded, validated, platform-audited. Stored for review
  (surfacing in the platform console is a later step; SMTP forwarding when mail exists).
- `GET /sitemap.xml` — generated from the public product routes.

## Implementation

Extract from `welcome.tsx`: the `.lp` styles string → `resources/js/marketing/styles.ts`;
`NewsletterForm` + footer → shared components; new `MarketingLayout` (subpage nav, footer, Head).
Welcome keeps its behavior, loses ~1,000 duplicated lines. Small additional `.lp .sub*` styles for
page heroes, prose, FAQ accordions and the contact form.

## Testing

Pest `MarketingSiteTest`: each page 200 + title/meta + key content; FAQ carries FAQPage JSON-LD;
contact stores + validates + honeypot + platform audit; sitemap lists all pages; newsletter still
works from the shared footer. Vitest suites stay green.

## Out of scope

Blog/insights engine, real testimonials (awaiting real clients), pricing page (needs a pricing
decision), SSR prerendering, multi-language.
