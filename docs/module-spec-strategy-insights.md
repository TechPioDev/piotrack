# Module Specification — Strategy Insights (Phase 5 / Module 16)

> Road-to-100 phase for Marketing Strategy & Research (2026-09-02). The prior honesty
> ruling stands: the strategy workspace (typed items, priorities, reporting) was already
> Tested, and rows stayed Partial because the *analysis* was human consulting work. This
> phase closes rows by making the product genuinely COMPUTE those analyses from its own
> records — never from invented market data. Anything still requiring external data (TAM)
> or qualitative judgment (personas, pain points, journey narratives, messaging) stays
> honestly Partial.

## StrategyInsights service — computed from real records only

- Keyword opportunities (STRAT-010/011): tracked keywords not ranking (null or >10),
  ordered volume-desc/difficulty-asc, plus search-intent mix.
- Competitor landscape (STRAT-003/004): share of voice, keyword head-to-head and AI
  recommendation share via the existing CompetitiveService.
- Geographic markets (STRAT-012): per-market keyword counts, average position and open
  opportunities from the Phase-2 geo pipeline.
- Vertical performance (STRAT-013): deals × company industry — totals, win rate, won MRR.
- Website conversion audit (STRAT-017): visitors → identified → form submissions →
  bookings → won, with guarded rates.
- CRM & automation hygiene (STRAT-021): contacts missing owner/company/email; open deals
  with no movement in 30 days.
- Funnel audit (STRAT-020): stages without attached assets per funnel.
- PPC audit (STRAT-019): per-campaign spend/clicks/conversions/revenue with
  no-conversion and negative-ROAS flags (provider provenance carried).
- Lead-gen gaps (STRAT-022): traffic sources with visitors but zero identifications;
  landing pages with views but no capture form.
- Revenue opportunity model (STRAT-023): open pipeline × the org's own historical win
  rate; explicitly reports insufficient data below 5 closed deals — nothing invented.
- Data-driven ICP (STRAT-006): won-deal clusters — top industries, sources, median MRR;
  insufficient-data guard.
- SEO audit summary (STRAT-018): roll-up of stored TechnicalSeoAuditor runs.
- Marketing assessment (STRAT-001/002): composite scorecard aggregating the audits
  above into issues/strengths counts.

Surfaced as an Insights section on /strategy; every block renders its empty/insufficient
state honestly. Pest `StrategyInsightsTest` seeds real records and asserts the numbers.
Still Partial after this phase: STRAT-005 (TAM — external market data), 007/008/009
(personas, pain points, journey — qualitative), 015/016 (positioning/messaging —
qualitative; the quantitative landscape feeds them).
