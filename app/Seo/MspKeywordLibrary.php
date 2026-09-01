<?php

namespace App\Seo;

/**
 * Curated MSP keyword research library (KSEO-001..012). This is domain
 * expertise shipped as product: every entry is typed (service, industry,
 * vertical, problem, solution, long-tail, bottom-funnel) and intent-classified
 * (informational/commercial/transactional). Volume and difficulty are NEVER
 * invented — they stay null until a keyword-data provider fills them
 * (ADR-0005). Geo variants multiply the buying-stage phrases across the
 * tenant's own locations.
 */
class MspKeywordLibrary
{
    /**
     * @return list<array{phrase: string, type: string, intent: string}>
     */
    public static function entries(): array
    {
        $e = fn (string $phrase, string $type, string $intent) => ['phrase' => $phrase, 'type' => $type, 'intent' => $intent];

        return [
            // Solution searches (KSEO-010) — how buyers name the fix.
            $e('managed it services', 'solution', 'commercial'),
            $e('managed service provider', 'solution', 'commercial'),
            $e('outsourced it support', 'solution', 'commercial'),
            $e('co-managed it services', 'solution', 'commercial'),
            $e('it help desk services', 'solution', 'commercial'),
            $e('network monitoring services', 'solution', 'commercial'),
            $e('managed cybersecurity services', 'solution', 'commercial'),
            $e('cloud migration services', 'solution', 'commercial'),
            $e('backup and disaster recovery services', 'solution', 'commercial'),
            $e('vcio services', 'solution', 'commercial'),

            // Service keywords (KSEO-005) — the MSP service catalog.
            $e('it support', 'service', 'commercial'),
            $e('cybersecurity services', 'service', 'commercial'),
            $e('microsoft 365 management', 'service', 'commercial'),
            $e('server management', 'service', 'commercial'),
            $e('endpoint security', 'service', 'commercial'),
            $e('email security', 'service', 'commercial'),
            $e('penetration testing services', 'service', 'commercial'),
            $e('it compliance services', 'service', 'commercial'),
            $e('voip phone systems for business', 'service', 'commercial'),
            $e('hardware procurement services', 'service', 'commercial'),

            // Industry keywords (KSEO-006) — who is buying.
            $e('it support for small business', 'industry', 'commercial'),
            $e('it services for manufacturing', 'industry', 'commercial'),
            $e('it support for accounting firms', 'industry', 'commercial'),
            $e('it services for construction companies', 'industry', 'commercial'),
            $e('it support for nonprofits', 'industry', 'commercial'),

            // Vertical keywords (KSEO-007) — regulated niches with named stakes.
            $e('healthcare it services', 'vertical', 'commercial'),
            $e('hipaa compliant it support', 'vertical', 'commercial'),
            $e('law firm it services', 'vertical', 'commercial'),
            $e('cmmc compliance services', 'vertical', 'commercial'),
            $e('financial services it support', 'vertical', 'commercial'),
            $e('dental office it support', 'vertical', 'commercial'),

            // Problem searches (KSEO-009) — how buyers describe the pain.
            $e('ransomware attack recovery', 'problem', 'commercial'),
            $e('server keeps crashing', 'problem', 'informational'),
            $e('email not working for whole office', 'problem', 'informational'),
            $e('slow network in office', 'problem', 'informational'),
            $e('failed cyber insurance audit', 'problem', 'commercial'),
            $e('it guy quit', 'problem', 'commercial'),
            $e('recover deleted files from server', 'problem', 'informational'),

            // Long-tail (KSEO-011) — specific questions with buying context.
            $e('how much does managed it services cost for small business', 'long_tail', 'commercial'),
            $e('difference between msp and break fix it support', 'long_tail', 'informational'),
            $e('what does an msp do for a small business', 'long_tail', 'informational'),
            $e('how to choose a managed service provider', 'long_tail', 'commercial'),
            $e('do i need soc 2 compliance for my business', 'long_tail', 'informational'),
            $e('best it support company for 20 person office', 'long_tail', 'commercial'),

            // Bottom-funnel (KSEO-012) — ready to talk.
            $e('managed it services pricing', 'bottom_funnel', 'transactional'),
            $e('it support near me', 'bottom_funnel', 'transactional'),
            $e('msp near me', 'bottom_funnel', 'transactional'),
            $e('it support quote', 'bottom_funnel', 'transactional'),
            $e('managed it services proposal', 'bottom_funnel', 'transactional'),
            $e('it services consultation', 'bottom_funnel', 'transactional'),
            $e('switch it providers', 'bottom_funnel', 'transactional'),
        ];
    }

    /** Types whose phrases are worth multiplying across the tenant's markets. */
    public const GEO_TYPES = ['solution', 'bottom_funnel'];
}
