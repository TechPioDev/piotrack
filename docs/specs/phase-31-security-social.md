# Phase 31 — Security + Social Media Management (partial close-outs)

Register target (6 rows): SEC-003 (upload scanning), SEC-005 (encryption evidence),
SOC-006 (social strategy), SOC-018 (paid social), SOC-019 (sponsored posts),
SOC-027 (lead attribution).

Stays honest: SEC-007 (least-privilege service accounts — infrastructure
configuration), SOC-009 (graphic creation — designer work, the BRAND precedent),
SOC-020/021 (community/comment management — the comments live on the networks, so a
real inbox needs the channel APIs), SOC-022/023/024 (brand/social/reputation
listening — needs a social-listening API; review monitoring is already the
Reputation module's job).

## Security

- **SEC-003 — content scanning, first-party.** Upload validation (mime allow-list,
  size, tenant-scoped storage) was already tested; "scanning" was deferred for want
  of ClamAV. New `UploadScanner` runs real content checks before a file is stored:
  EICAR detection, magic-byte verification against the claimed extension for the
  allow-listed types (a PHP script renamed .png is refused), executable headers
  (MZ/ELF), and embedded `<?php`/script payloads in files claiming to be images or
  documents. AV-engine scanning (ClamAV-class) remains a deployment add-on behind
  the same seam, stated in the note — content-heuristic scanning is real scanning,
  not a placeholder.
- **SEC-005 — close on evidence.** The row claims transit encryption + at-rest
  encryption for sensitive fields (OAuth tokens, credentials): HTTPS is forced in
  production (Stage 0), integration credentials are `encrypted:array`, 2FA secrets
  and recovery codes `encrypted` — all with existing test coverage. The old note
  held it back for full-disk encryption, which the row never claimed; that stays a
  managed-platform concern, in the note.

## Social Media Management

- **SOC-006 — strategy from records.** `SocialStrategy::report()`: per-network
  cadence over the last 30 days (published, scheduled ahead, posts/week), content
  mix by type, and recommendations citing their numbers (a network silent 14+ days,
  everything on one network, long-form pieces with no clips). Rendered on the
  social page. Strategy the platform can compute is computed; taste stays human.
- **SOC-018/019 — the paid-social bridge.** Ad campaigns already run on
  meta/linkedin/youtube; sponsoring was the missing link. `sponsor(SocialPost)`
  creates a draft AdCampaign on the post's network's ad platform (linkedin →
  linkedin, facebook → meta, youtube → youtube), named for the post, linked via
  targeting, objective awareness — finished in the Ads module where budgets and
  metrics already live. X has no ads platform in the abstraction and is refused
  with a plain message.
- **SOC-027 — social lead attribution.** The attribution engine already classifies
  social first-touches (ChannelClassifier). `attribution()` rolls it up per network
  (utm_source): visitors, identified leads, customers won — real records only, on
  the social page next to the strategy.

## Tests (tests/Feature/Qa/SecuritySocialCloseoutTest.php)

1. Scanner: EICAR refused, PHP-in-PNG refused, MZ executable refused, magic-byte
   mismatch refused, clean PDF/PNG accepted end-to-end through the upload endpoint.
2. Encryption evidence: integration credentials and 2FA secret unreadable as plain
   text on the row, decrypt round-trip works.
3. Strategy report: cadence/mix computed from seeded posts, silent-network and
   no-clips recommendations cite numbers, quiet when healthy.
4. Sponsor bridge: linkedin post → draft linkedin campaign linked to the post;
   facebook → meta; x refused; idempotent per post.
5. Attribution: seeded social-source visitors/contacts/wins roll up per network.
