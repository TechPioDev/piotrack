<?php

namespace App\Integrations;

use App\Services\Integrations\OAuthFlow;

/**
 * The catalog of available connectors (INTG-001, INTG-004..010). Like the plan
 * catalog, this is the code source of truth. `auth_type` is none|api_key|oauth.
 * API-key connectors are connectable out of the box (the vault stores the key,
 * the sync engine reports health). OAuth connectors become connectable the
 * moment their app credentials exist in config (`services.connectors.{key}`) —
 * registering a vendor app is configuration, not code. Until then they surface
 * as "coming soon".
 */
class ConnectorRegistry
{
    private const CATALOG = [
        ['key' => 'demo_source', 'name' => 'Demo Data Source', 'category' => 'Demo', 'auth_type' => 'api_key'],

        // Google (INTG-004)
        ['key' => 'google_analytics', 'name' => 'Google Analytics', 'category' => 'Analytics', 'auth_type' => 'oauth'],
        ['key' => 'google_search_console', 'name' => 'Google Search Console', 'category' => 'SEO', 'auth_type' => 'oauth'],
        ['key' => 'google_ads', 'name' => 'Google Ads', 'category' => 'Advertising', 'auth_type' => 'oauth'],
        ['key' => 'google_business_profile', 'name' => 'Google Business Profile', 'category' => 'SEO', 'auth_type' => 'oauth'],

        // Microsoft (INTG-005)
        ['key' => 'microsoft_ads', 'name' => 'Microsoft Ads', 'category' => 'Advertising', 'auth_type' => 'oauth'],
        ['key' => 'microsoft_365', 'name' => 'Microsoft 365 / Outlook', 'category' => 'Communication', 'auth_type' => 'oauth'],
        ['key' => 'microsoft_teams', 'name' => 'Microsoft Teams', 'category' => 'Communication', 'auth_type' => 'oauth'],

        // Social / ads (INTG-006)
        ['key' => 'linkedin_ads', 'name' => 'LinkedIn Ads', 'category' => 'Advertising', 'auth_type' => 'oauth'],
        ['key' => 'meta_ads', 'name' => 'Meta Ads', 'category' => 'Advertising', 'auth_type' => 'oauth'],
        ['key' => 'youtube', 'name' => 'YouTube', 'category' => 'Social', 'auth_type' => 'oauth'],

        // CRM (INTG-007)
        ['key' => 'hubspot', 'name' => 'HubSpot', 'category' => 'CRM', 'auth_type' => 'api_key'],
        ['key' => 'salesforce', 'name' => 'Salesforce', 'category' => 'CRM', 'auth_type' => 'oauth'],

        // Comms (INTG-008)
        ['key' => 'gmail', 'name' => 'Gmail / Google Workspace', 'category' => 'Communication', 'auth_type' => 'oauth'],
        ['key' => 'slack', 'name' => 'Slack', 'category' => 'Communication', 'auth_type' => 'oauth'],
        ['key' => 'zoom', 'name' => 'Zoom', 'category' => 'Communication', 'auth_type' => 'oauth'],
        ['key' => 'twilio', 'name' => 'Twilio', 'category' => 'Communication', 'auth_type' => 'api_key'],
        ['key' => 'sendgrid', 'name' => 'SendGrid', 'category' => 'Email', 'auth_type' => 'api_key'],
        ['key' => 'mailgun', 'name' => 'Mailgun', 'category' => 'Email', 'auth_type' => 'api_key'],
        ['key' => 'mailchimp', 'name' => 'Mailchimp', 'category' => 'Email', 'auth_type' => 'api_key'],

        // Ops (INTG-009) — Zapier and generic receivers ride the outbound
        // webhooks surface on this same settings page, not a connector row.
        ['key' => 'callrail', 'name' => 'CallRail', 'category' => 'Ops', 'auth_type' => 'api_key'],
        ['key' => 'calendly', 'name' => 'Calendly', 'category' => 'Ops', 'auth_type' => 'api_key'],
        ['key' => 'stripe', 'name' => 'Stripe', 'category' => 'Billing', 'auth_type' => 'api_key'],
        ['key' => 'quickbooks', 'name' => 'QuickBooks', 'category' => 'Billing', 'auth_type' => 'oauth'],
    ];

    /**
     * @return list<array{key: string, name: string, category: string, auth_type: string, connectable: bool}>
     */
    public static function all(): array
    {
        $oauth = app(OAuthFlow::class);

        return array_map(function (array $connector) use ($oauth) {
            $connector['connectable'] = $connector['auth_type'] === 'oauth'
                ? $oauth->isConfigured($connector['key'])
                : true;

            return $connector;
        }, self::CATALOG);
    }

    /**
     * @return array{key: string, name: string, category: string, auth_type: string, connectable: bool}|null
     */
    public static function find(string $key): ?array
    {
        foreach (self::all() as $connector) {
            if ($connector['key'] === $key) {
                return $connector;
            }
        }

        return null;
    }

    public static function isConnectable(string $key): bool
    {
        return (bool) (self::find($key)['connectable'] ?? false);
    }

    public static function name(string $key): string
    {
        return self::find($key)['name'] ?? $key;
    }
}
