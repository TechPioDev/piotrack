import { LucideIcon } from 'lucide-react';

export interface OrganizationSummary {
    id: number;
    name: string;
    slug: string;
    role?: string | null;
}

export interface Auth {
    user: User;
    currentOrganization: OrganizationSummary | null;
    organizations: OrganizationSummary[];
    permissions: string[];
    role: string | null;
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavGroup {
    title: string;
    items: NavItem[];
}

export interface NavItem {
    title: string;
    url: string;
    icon?: LucideIcon | null;
    isActive?: boolean;
}

export interface Entitlements {
    features: Record<string, boolean>;
    plan: string | null;
}

export interface SharedData {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    entitlements: Entitlements;
    notifications: { unread: number };
    flash: Flash;
    impersonation: Impersonation;
    /** Signed in, enforcement is on, and this account has not enrolled yet. */
    twoFactorRequired: boolean;
    [key: string]: unknown;
}

export interface Flash {
    status?: string | null;
    ai_result?: Record<string, unknown> | null;
}

/**
 * The active support-impersonation session. `null` whenever the request is the
 * user's own — anything else means someone else is acting as them.
 */
export type Impersonation = {
    active: true;
    user: string | null;
    impersonator: string | null;
} | null;

export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    [key: string]: unknown; // This allows for additional properties...
}
