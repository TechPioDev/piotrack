<?php

namespace App\Billing;

/**
 * Boolean feature entitlements (access on/off). Gated on the backend via the
 * `entitlement:` middleware and shared to the frontend for upgrade prompts.
 * Grows per module; a plan grants a subset (see PlanCatalog).
 */
enum Feature: string
{
    case Crm = 'crm';
    case Marketing = 'marketing';
    case Content = 'content';
    case Seo = 'seo';
    case Advertising = 'advertising';
    case Sales = 'sales';
    case Analytics = 'analytics';
    case Ai = 'ai';
    case Automation = 'automation';
    case AuditLog = 'audit_log';
    case AiVisibility = 'ai_visibility';
    case Api = 'api';
    case Teams = 'teams';
    case WhiteLabel = 'white_label';
    case Chat = 'chat';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $f) => $f->value, self::cases());
    }
}
