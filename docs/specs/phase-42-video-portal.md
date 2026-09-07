# Phase 42 — Video Marketing + Client Portal closeout

**Register targets:** VID-001, VID-014, VID-015, VID-016, VID-017 (Video Marketing
72.2% → 100%); PORTAL-003, PORTAL-012, PORTAL-013, PORTAL-014, PORTAL-015 (Client
Portal 72.2% → 100%).

## Position

**Video** — the deferral notes predate machinery now shipped: P38's video-aware Meta
promotion, the ads platform matrix with `youtube`, the P16 customer-match exports, the
campaign send pipeline with link click-tracking, and MergeTags personalization.
- **VID-001 Video strategy** — the P31 SocialStrategy precedent: a report computed from
  the tenant's own video records (cadence, type mix, funnel coverage, published share),
  recommendations citing their numbers behind a data floor. Strategy AUTHORING stays
  human; the report informs it.
- **VID-014 Video ads** — Meta video-ad drafts shipped in P38 (tested); Phase 42 adds
  the YouTube half: video-typed pieces draft `video_ad` campaigns on the youtube
  platform, the creative stating the video file lives in YouTube/Google Ads. Live
  delivery stays behind the Ads API as everywhere.
- **VID-015 Video retargeting** — audience attach extended to youtube campaigns:
  YouTube retargeting runs on Google Ads Customer Match, and the audience's google
  export CSV (P16, tested) is the upload. One audience → meta + youtube video campaigns.
- **VID-016 Personalized sales video** — a rep records on any host (Loom/Vidyard/
  unlisted YouTube), pastes the link with a personal message on the contact; it sends
  through the real dispatcher (merge-tag personalized, suppression-honoring,
  click-tracked video CTA). No video host is required or pretended.
- **VID-017 Video email** — campaigns get `video_url`/`video_title`; the send appends a
  video CTA block whose link rides the existing click tracker — thumbnail-and-link is
  how video email actually works in every client (no client plays inline video), stated
  honestly.

**Portal** — all four rows are "exists as data, not surfaced" or "flag pending":
- **PORTAL-003/015** — campaigns (status + high-level stats) and the strategy roadmap
  (strategy_items type=roadmap) become portal dashboard sections.
- **PORTAL-012** — `files.client_visible` (default false, nothing leaks by default);
  the portal lists only flagged files; toggle on the files page.
- **PORTAL-013** — the P21 PDF report streams from the portal (`portal/report`), and a
  monthly `reports:portal-monthly` command notifies client users it's ready (scheduled).
- **PORTAL-014** — `activities.client_visible` on meeting activities; flagged meeting
  notes render in the portal; toggle rides the activity timeline.

## Build

1. Migration `extend_video_portal`: `files.client_visible`, `activities.client_visible`
   (both default false), `campaigns.video_url` + `video_title`.
2. `VideoStrategy` service + panel on the content pieces page.
3. `VideoAdsService` (youtubeCampaign + youtube audience attach via the shared route);
   piece-page button; campaigns/show audience panel gains youtube.
4. Contact "Send video message" dialog → dispatcher email with tracked CTA.
5. Campaign video fields + send-block injection in `buildEmail`.
6. PortalService: campaigns(), roadmap(), meetingNotes(), files() filtered; portal
   dashboard sections + report download; `reports:portal-monthly` command (scheduled).
7. Visibility toggles: PATCH files/{file}/visibility, PATCH activities/{activity}/visibility.
8. Tests `tests/Feature/Qa/VideoPortalCloseoutTest.php` (~6).

## Out of scope (unchanged)

Live video-ad delivery (Ads APIs), video hosting itself, inline video playback in email
clients (does not exist anywhere).
