"""Build the Feature Traceability Register (docs/register/feature-register.csv).

Parses the extracted text of the Jumpfactor Competitor Complete Feature Inventory
(source-of-truth) so that EVERY bullet becomes a register row, then appends the
platform/commercial infrastructure features mandated by the Master Development
Prompt. Re-run after updating either source extract.
"""

import csv
import io
import re
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
INVENTORY_TXT = Path(sys.argv[1]) if len(sys.argv) > 1 else ROOT / "docs" / "source" / "feature-inventory-raw.txt"
OUT_CSV = ROOT / "docs" / "register" / "feature-register.csv"

MODULE_CODES = {
    1: "STRAT", 2: "BRAND", 3: "WEB", 4: "TSEO", 5: "KSEO", 6: "LSEO",
    7: "CONT", 8: "AEO", 9: "GEO", 10: "LLMO", 11: "PPC", 12: "LIAD",
    13: "META", 14: "RETG", 15: "SOC", 16: "VID", 17: "POD", 18: "LEAD",
    19: "CRM", 20: "AUTO", 21: "EMAIL", 22: "SMS", 23: "LSCR", 24: "INTENT",
    25: "ALERT", 26: "AISA", 27: "BOOK", 28: "ABM", 29: "ENAB", 30: "REP",
    31: "DPR", 32: "LINK", 33: "OMNI", 34: "FUNL", 35: "ANLY", 36: "ATTR",
    37: "CALL", 38: "CRO", 39: "PORTAL", 40: "PROJ", 41: "TRAIN", 42: "MLOC",
    43: "VERT", 44: "SVC", 45: "PERF", 46: "METH", 47: "BENCH", 48: "CINT",
    49: "GSCORE", 50: "AIVIS",
}

PAGE_MARKER = re.compile(r"^--- PAGE (\d+) ---$")
FOOTER = re.compile(r"^MSP Growth Platform Feature Inventory \| Page \d+$")
SECTION = re.compile(r"^(\d{1,2})\.\s+(.+)$")


def parse_inventory(path: Path):
    rows = []
    notes = {}
    section_num = None
    section_name = None
    submodule = ""
    page = 0
    started = False
    last_kind = None

    for raw in path.read_text(encoding="utf-8").splitlines():
        line = raw.strip()
        m = PAGE_MARKER.match(line)
        if m:
            page = int(m.group(1))
            continue
        if not line or FOOTER.match(line):
            continue
        if not started:
            if line == "Detailed Feature Inventory":
                started = True
            continue
        if line == "Where to Outperform Jumpfactor":
            break

        m = SECTION.match(line)
        if m and int(m.group(1)) in MODULE_CODES and (
            section_num is None or int(m.group(1)) == section_num + 1
        ):
            section_num = int(m.group(1))
            section_name = m.group(2).strip()
            submodule = ""
            last_kind = "section"
            continue

        if line.startswith("•"):
            feature = line.lstrip("•").strip()
            rows.append({
                "section": section_num,
                "module": section_name,
                "submodule": submodule,
                "feature": feature,
                "page": page,
            })
            last_kind = "bullet"
            continue

        # Non-bullet content line: subheading or prose note.
        is_subheading = len(line) <= 45 and not line.endswith(".") and line[0].isupper()
        if is_subheading:
            submodule = line
            last_kind = "subheading"
        else:
            if last_kind == "note":
                notes[section_num] = notes[section_num] + " " + line
            else:
                notes[section_num] = notes.get(section_num, "")
                notes[section_num] = (notes[section_num] + " " + line).strip()
            last_kind = "note"

    return rows, notes


# ---------------------------------------------------------------------------
# Platform / commercial infrastructure features (Master Development Prompt).
# (code, module, submodule, feature, source-section-in-master-prompt)
# ---------------------------------------------------------------------------
MP = "Master Prompt"

def s(code, module, items):
    """items: list of (submodule, feature, source_section)"""
    return [(code, module, sub, feat, src) for (sub, feat, src) in items]

PLATFORM = []

PLATFORM += s("AUTH", "Identity & Authentication", [
    ("Registration", "Account registration with email + password", "§6, §57 Stage 1"),
    ("Registration", "Email verification flow", "§6, §57 Stage 1"),
    ("Registration", "Password setup and strength validation", "§6"),
    ("Login", "Secure login / logout", "§21, §57 Stage 1"),
    ("Login", "Password hashing (modern algorithm)", "§21"),
    ("Login", "Session management with secure cookies", "§21"),
    ("Login", "Brute-force protection and login rate limiting", "§21"),
    ("Login", "Failed-login audit events", "§20"),
    ("Recovery", "Password reset flow", "§57 Stage 1"),
    ("MFA", "MFA-ready architecture (TOTP enrollment, recovery codes)", "§21, §57 Stage 1"),
    ("API auth", "Personal/tenant API keys with create/revoke + audit", "§20, §22"),
])

PLATFORM += s("TEN", "Tenant & Organization Management", [
    ("Organizations", "Organization (tenant) creation", "§4, §57 Stage 2"),
    ("Organizations", "Organization profile and settings", "§6"),
    ("Organizations", "Organization deletion with safeguards", "§27, §40"),
    ("Isolation", "Row-level tenant scoping on every business record", "§4"),
    ("Isolation", "Tenant context resolution middleware (session/token)", "§4"),
    ("Isolation", "Automated cross-tenant access tests", "§4, §33"),
    ("Users", "User membership within organizations", "§5"),
    ("Users", "Team invitations (send, accept, expire, revoke, resend)", "§6, §34"),
    ("Users", "Teams / groups", "§57 Stage 2"),
    ("Users", "User deactivation and removal", "§20"),
])

PLATFORM += s("RBAC", "Roles & Permissions", [
    ("Platform roles", "Platform roles: Super Admin, Platform Admin, Support Admin, Finance Admin, Read-only Support", "§5"),
    ("Org roles", "Org roles: Owner, Admin, Marketing Manager, Sales Manager, Sales Rep, Marketing User, Analyst, Billing Admin, Viewer", "§5"),
    ("Engine", "Granular permission registry (resource.action naming)", "§5"),
    ("Engine", "Backend authorization enforcement on every endpoint", "§5, §21"),
    ("Engine", "Frontend permission-aware visibility (UX only, not security)", "§5"),
    ("Engine", "Permission-change audit events", "§20"),
])

PLATFORM += s("ONBD", "Customer Onboarding", [
    ("Flow", "Plan selection during signup", "§6"),
    ("Flow", "Company/organization creation step", "§6"),
    ("Flow", "Payment and subscription activation step", "§6"),
    ("Flow", "Trial signup support", "§6"),
    ("Setup wizard", "Team invitation step", "§6"),
    ("Setup wizard", "Business profile (industry, services, geography)", "§6"),
    ("Setup wizard", "Website details capture", "§6"),
    ("Setup wizard", "Marketing goals capture", "§6"),
    ("Setup wizard", "ICP setup", "§6"),
    ("Setup wizard", "Competitor setup", "§6"),
    ("Setup wizard", "Integration connection wizard", "§6"),
    ("Setup wizard", "Initial audit trigger on completion", "§6"),
    ("UX", "Progress indicator and setup checklist", "§6"),
    ("UX", "Leave and resume onboarding later", "§6"),
])

PLATFORM += s("BILL", "Billing & Subscriptions", [
    ("Plans", "Configurable plan catalog (Starter, Growth, Professional, Agency, Enterprise)", "§7"),
    ("Pricing", "Monthly and annual pricing with annual discount", "§7"),
    ("Pricing", "Per-user pricing support", "§7"),
    ("Pricing", "Usage-based pricing support", "§7"),
    ("Pricing", "Add-ons", "§7"),
    ("Pricing", "Custom enterprise pricing", "§7"),
    ("Pricing", "Coupons / promo codes", "§7, §29"),
    ("Checkout", "Checkout flow: billing details, company details, tax info, promo code, payment method, order summary", "§7"),
    ("Checkout", "Invoice / receipt generation", "§7"),
    ("Provider", "Payment-provider abstraction layer (Stripe first)", "§7"),
    ("Lifecycle", "Trial and trial expiration handling", "§7"),
    ("Lifecycle", "Subscription activation and renewal", "§7"),
    ("Lifecycle", "Upgrade / downgrade with proration", "§7"),
    ("Lifecycle", "Quantity changes and add-on changes", "§7"),
    ("Lifecycle", "Cancellation, scheduled cancellation, reactivation", "§7"),
    ("Lifecycle", "Failed payment handling, dunning, grace periods", "§7"),
    ("Lifecycle", "Suspended and expired subscription states", "§7"),
    ("Portal", "Billing portal: plan, usage, payment method, invoices, history, billing contact, tax details, cancel", "§7"),
    ("Webhooks", "Billing webhook processing (authenticated, idempotent, logged, retry-safe)", "§7"),
])

PLATFORM += s("ENTL", "Feature Entitlements & Usage Limits", [
    ("Entitlements", "Central entitlement service (Plan → Entitlements → Limits → Subscription → Feature Access)", "§8"),
    ("Entitlements", "Plan/feature entitlement matrix administration", "§8, §29"),
    ("Entitlements", "Feature gating API for backend and frontend", "§8"),
    ("Limits", "Usage limits: users, contacts, leads, emails, SMS, AI credits, keywords, competitors, locations, websites, storage, API calls, automations, workflow executions, reports, data retention", "§9"),
    ("Metering", "Usage metering and aggregation per tenant", "§9"),
    ("Metering", "Usage display: current, allowance, remaining, overages", "§9"),
    ("Metering", "Limit enforcement and overage handling", "§9"),
])

PLATFORM += s("DSGN", "Design System & UX Standards", [
    ("Foundations", "Color, typography, spacing and icon token system", "§11"),
    ("Components", "Reusable component library (buttons, forms, tables, modals, charts, toasts, etc.)", "§11"),
    ("Standards", "Responsive behavior verified: large desktop → mobile", "§12"),
    ("Standards", "WCAG-oriented accessibility (keyboard, focus, contrast, semantics)", "§13"),
    ("Standards", "Data table standard (search, filter, sort, columns, pagination, bulk actions, export, saved views)", "§41"),
    ("Standards", "Dashboard standard (actionable KPIs, date filters)", "§42"),
    ("Standards", "Deliberate empty states with CTAs", "§39"),
    ("Standards", "Confirmation flows for destructive actions", "§40"),
    ("Standards", "Loading, error, partial-failure and retry states", "§25"),
])

PLATFORM += s("NOTIF", "Notification System", [
    ("Channels", "In-app notification center", "§17"),
    ("Channels", "Email notifications", "§17"),
    ("Channels", "SMS notifications", "§17"),
    ("Channels", "Slack / Teams notifications", "§17"),
    ("Channels", "Webhook notifications", "§17"),
    ("Events", "Business alerts: hot lead, new SQL, appointment, lead surge", "§17"),
    ("Events", "Operational alerts: workflow failure, integration disconnected, usage limit warning", "§17"),
    ("Events", "Billing alerts: payment failure, subscription expiring", "§17"),
    ("Events", "Marketing alerts: ranking decrease, traffic anomaly, AI visibility change, budget warning", "§17"),
    ("Preferences", "Per-user notification preference center", "§17"),
])

PLATFORM += s("SRCH", "Global Search", [
    ("Search", "Global search across contacts, companies, leads, opportunities, campaigns, content, tasks, reports, files, tickets", "§18"),
    ("Search", "Search suggestions and recent searches", "§18"),
    ("Search", "Entity grouping and filters in results", "§18"),
])

PLATFORM += s("IMEX", "Import / Export", [
    ("Import", "CSV import: contacts, companies, leads, opportunities, keywords, competitors", "§19"),
    ("Import", "Import pipeline: mapping, validation, preview, duplicate detection, error report, history", "§19"),
    ("Export", "CSV and Excel exports", "§19"),
    ("Export", "PDF report exports", "§19"),
])

PLATFORM += s("AUDIT", "Audit Logging", [
    ("Framework", "Central audit log (actor, tenant, action, resource, before/after, IP/device)", "§20"),
    ("Coverage", "Security events: logins, failed logins, password/MFA changes", "§20"),
    ("Coverage", "Admin events: user/role/permission changes, API keys, impersonation", "§20"),
    ("Coverage", "Data events: deletes, deal changes, campaign changes, exports", "§20"),
    ("Coverage", "Billing and subscription change events", "§20"),
    ("Access", "Audit log viewer with filters (org + platform level)", "§20, §29"),
])

PLATFORM += s("SEC", "Security", [
    ("AppSec", "CSRF, XSS, SQL-injection and SSRF protections", "§21"),
    ("AppSec", "Secure headers and API rate limiting", "§21"),
    ("AppSec", "File-upload validation and scanning", "§21"),
    ("Secrets", "Secrets management; no secrets in frontend or repo", "§21, §48"),
    ("Crypto", "Encryption in transit; encryption at rest for sensitive fields (OAuth tokens, credentials)", "§21"),
    ("Verification", "Webhook signature verification (inbound)", "§21"),
    ("Verification", "Least-privilege service accounts", "§21"),
    ("Verification", "Security logging and alerting", "§21, §24"),
])

PLATFORM += s("API", "API Platform", [
    ("Standards", "Consistent REST API: auth, tenant scoping, validation, pagination, filtering, sorting, search", "§22"),
    ("Standards", "Standard error responses with request IDs", "§22, §25"),
    ("Standards", "API versioning strategy", "§22"),
    ("Standards", "Idempotency keys for unsafe operations", "§22"),
    ("Public API", "Customer-facing API with keys and docs (higher plans)", "§22"),
])

PLATFORM += s("JOBS", "Background Jobs & Queues", [
    ("Infrastructure", "Queue/worker infrastructure for email, SMS, imports, exports, reports, crawling, rank checks, AI analysis, sync, notifications, analytics, webhook retries", "§23"),
    ("Reliability", "Retries with backoff and dead-letter handling", "§23"),
    ("Reliability", "Job idempotency", "§23"),
    ("Operations", "Job monitoring dashboard", "§23, §29"),
])

PLATFORM += s("OBS", "Observability & Operations", [
    ("Logging", "Structured application, error, security, integration and job logs", "§24"),
    ("Metrics", "Metrics: API latency, error rate, queue depth, job failures, DB performance, login failures, integration failures", "§24"),
    ("Monitoring", "Health checks: app, API, database, workers, integrations", "§24, §50"),
    ("Alerting", "Administrator alerting on critical failures", "§24"),
])

PLATFORM += s("BCK", "Backups & Disaster Recovery", [
    ("Backups", "Automated database backups with point-in-time recovery", "§26"),
    ("Backups", "File storage backups and retention periods", "§26"),
    ("Recovery", "Documented, tested restore and DR procedures", "§26"),
    ("Recovery", "Backup verification", "§26"),
])

PLATFORM += s("PRIV", "Privacy & Data Management", [
    ("Legal", "Privacy policy and terms acceptance tracking", "§27"),
    ("Legal", "Cookie preferences where applicable", "§27"),
    ("Rights", "Data export, data deletion, account and organization deletion", "§27"),
    ("Rights", "Retention rules", "§27"),
    ("Consent", "Consent tracking, communication opt-outs, email unsubscribe, SMS STOP handling", "§27, §46"),
    ("Compliance", "Suppression lists, bounce and complaint handling, delivery tracking", "§46"),
])

PLATFORM += s("SUPP", "Customer Support Infrastructure", [
    ("Help", "Help center and knowledge base", "§28"),
    ("Tickets", "Support tickets: creation, status, priority, attachments, threaded communication", "§28"),
    ("Comms", "Product announcements and release notes", "§28"),
])

PLATFORM += s("ADMIN", "Platform Administration (Super Admin)", [
    ("Management", "Tenant, user and subscription management", "§29"),
    ("Management", "Plan, entitlement, coupon and payment management", "§29"),
    ("Management", "Usage and system health visibility", "§29"),
    ("Management", "Feature flags (beta, tenant rollouts, staged releases, kill switches)", "§29, §30"),
    ("Management", "Announcements and support tooling", "§29"),
    ("Impersonation", "Support impersonation: permissioned, visibly indicated, fully audited, time-limited", "§29"),
])

PLATFORM += s("INTG", "Integration Framework", [
    ("Framework", "Reusable connector framework (OAuth, credentials vault, scopes)", "§16"),
    ("Framework", "Sync engine: status, last sync, failures, retry, reconnection", "§16"),
    ("Framework", "Per-connector error logs and health", "§16"),
    ("Connectors", "Google: Analytics, Search Console, Ads, Business Profile", "§16"),
    ("Connectors", "Microsoft: Ads, 365, Outlook, Teams, Dynamics", "§16"),
    ("Connectors", "Social/ads: LinkedIn, Meta, YouTube", "§16"),
    ("Connectors", "CRM: HubSpot, Salesforce", "§16"),
    ("Connectors", "Comms: Gmail, Google Workspace, Twilio, SendGrid, Mailgun, Slack, Zoom", "§16"),
    ("Connectors", "Ops: CallRail, Calendly, Stripe, QuickBooks, Zapier, generic webhooks", "§16"),
    ("Connectors", "AI providers (abstracted)", "§16, §44"),
])

PLATFORM += s("AIPF", "AI Platform Infrastructure", [
    ("Core", "Prompt management and versioning", "§44"),
    ("Core", "AI provider abstraction and model configuration", "§44"),
    ("Governance", "AI cost tracking: requests, tokens, estimated cost per tenant/user/feature/provider/model", "§45"),
    ("Governance", "Plan-based AI usage limits", "§45"),
    ("Governance", "AI error handling, retry behavior, auditability", "§44"),
    ("Governance", "Human confirmation for sensitive AI actions", "§44"),
])

PLATFORM += s("FILE", "Files & Documents", [
    ("Storage", "Tenant-scoped file storage with upload validation", "§3, §21"),
    ("Storage", "Documents attached to CRM records, tickets, projects", "§3"),
])

PLATFORM += s("DEVX", "Engineering Foundation & Delivery", [
    ("Foundation", "Repository structure, coding standards, environment configuration", "§48, §57 Stage 0"),
    ("Foundation", "Versioned, reviewable database migrations", "§49"),
    ("Foundation", "Local / development / staging / production environments", "§47"),
    ("CI/CD", "Pipeline gates: lint, type check, unit, integration, build, security, migration validation, staging smoke test", "§51"),
    ("CI/CD", "Deployment with health checks, rollback, version tracking", "§50"),
    ("Quality", "Automated test framework (unit, API, integration, UI, E2E)", "§31–§34"),
    ("Quality", "Feature Register + Module Completion Report process", "§1, §36"),
    ("Docs", "README, architecture docs, API docs, ADRs, runbooks", "§54"),
])


def load_existing_state():
    """Preserve tracking fields across regenerations, keyed by feature ID."""
    if not OUT_CSV.exists():
        return {}
    with open(OUT_CSV, encoding="utf-8-sig") as f:
        return {
            r["id"]: {k: r[k] for k in ("status", "backend", "frontend", "tests", "docs", "depends_on", "notes")}
            for r in csv.DictReader(f)
        }


# Gaps found by QA against an expectation the source inventory does not contain.
# They are appended here rather than hand-edited into the CSV, because main()
# rebuilds every row from the inventory and PLATFORM: a row that exists only in
# the CSV is silently dropped on the next regeneration, which is exactly the
# disappearance Master Prompt §65 forbids.
QA_FINDINGS = s("AUTO", "Marketing Automation", [
    ("Automated actions", "Conditional branching in workflows", "QA §21 (2026-08-19)"),
    ("Automated actions", "Contact tagging", "QA §21 (2026-08-19)"),
])

QA_FINDINGS += s("SUPP", "Support System", [
    ("Support tickets", "Ticket notifications to requester and assignee", "QA §53 (2026-08-20)"),
])

# Website Chat / Conversations — a whole module the source inventory never named:
# an embeddable conversational lead-qualification widget for the tenant's own site.
# Requested 2026-08-22; Phase 1 (core vertical slice) delivered.
QA_FINDINGS += s("CHAT", "Website Chat", [
    ("Chat widgets", "Embeddable website chat widget", "Module brief (2026-08-22)"),
    ("Chat widgets", "Multiple widgets per tenant", "Module brief (2026-08-22)"),
    ("Chat widgets", "Widget appearance and branding", "Module brief (2026-08-22)"),
    ("Chat widgets", "Floating launcher and welcome teaser", "Module brief (2026-08-22)"),
    ("Chat widgets", "One-line installation snippet", "Module brief (2026-08-22)"),
    ("Chat widgets", "Authorized domain restriction", "Module brief (2026-08-22)"),
    ("Qualification", "Configurable conversation flow", "Module brief (2026-08-22)"),
    ("Qualification", "Conditional branching by answer", "Module brief (2026-08-22)"),
    ("Qualification", "Message, choice and input node types", "Module brief (2026-08-22)"),
    ("Qualification", "Visual flow builder", "Module brief (2026-08-22)"),
    ("Qualification", "Flow template library", "Module brief (2026-08-22)"),
    ("Qualification", "MSP and cybersecurity qualification templates", "Module brief (2026-08-22)"),
    ("Qualification", "Existing-customer support routing", "Module brief (2026-08-22)"),
    ("Qualification", "High-priority security incident routing", "Module brief (2026-08-22)"),
    ("Lead capture", "Contact capture with configurable fields", "Module brief (2026-08-22)"),
    ("Lead capture", "Duplicate detection on capture", "Module brief (2026-08-22)"),
    ("Lead capture", "Progressive profiling for known visitors", "Module brief (2026-08-22)"),
    ("Lead capture", "CRM contact and lead creation", "Module brief (2026-08-22)"),
    ("Lead capture", "Chat lead scoring", "Module brief (2026-08-22)"),
    ("Lead capture", "Sales routing and assignment", "Module brief (2026-08-22)"),
    ("Lead capture", "Hot-lead sales alert", "Module brief (2026-08-22)"),
    ("Lead capture", "Chat source attribution", "Module brief (2026-08-22)"),
    ("Lead capture", "In-chat appointment booking", "Module brief (2026-08-22)"),
    ("Privacy", "Configurable consent gate", "Module brief (2026-08-22)"),
    ("Privacy", "Per-tenant privacy and terms links", "Module brief (2026-08-22)"),
    ("Inbox", "Agent conversation inbox", "Module brief (2026-08-22)"),
    ("Inbox", "Conversation transcript view", "Module brief (2026-08-22)"),
    ("Inbox", "Conversation status workflow", "Module brief (2026-08-22)"),
    ("Inbox", "Agent replies", "Module brief (2026-08-22)"),
    ("Inbox", "Internal notes", "Module brief (2026-08-22)"),
    ("Inbox", "Team @mentions", "Module brief (2026-08-22)"),
    ("Live chat", "Live human chat", "Module brief (2026-08-22)"),
    ("Live chat", "Bot to human handoff", "Module brief (2026-08-22)"),
    ("Live chat", "Agent availability states", "Module brief (2026-08-22)"),
    ("Live chat", "Business hours and offline capture", "Module brief (2026-08-22)"),
    ("Targeting", "Page targeting rules", "Module brief (2026-08-22)"),
    ("Targeting", "Visitor behavior targeting", "Module brief (2026-08-22)"),
    ("Analytics", "Chat engagement analytics", "Module brief (2026-08-22)"),
    ("Analytics", "Conversion funnel report", "Module brief (2026-08-22)"),
    ("Analytics", "Per-question drop-off report", "Module brief (2026-08-22)"),
    ("Analytics", "Chat A/B testing", "Module brief (2026-08-22)"),
    ("Notifications", "Chat notifications to agents", "Module brief (2026-08-22)"),
])



# Delivery status for QA_FINDINGS rows, keyed by feature name:
#   (status, backend, frontend, tests)
# Anything not listed stays Planned/Pending, which is the honest default — the
# register must never claim more than the code does.
DELIVERED = {
    # Website Chat, Phase 1 (core vertical slice), 2026-08-22.
    "Embeddable website chat widget": ("Tested", "Done", "Done", "Done"),
    "Multiple widgets per tenant": ("Tested", "Done", "Done", "Done"),
    "Floating launcher and welcome teaser": ("Tested", "Done", "Done", "Done"),
    "One-line installation snippet": ("Tested", "Done", "Done", "Done"),
    "Authorized domain restriction": ("Tested", "Done", "Done", "Done"),
    "Configurable conversation flow": ("Tested", "Done", "Done", "Done"),
    "Conditional branching by answer": ("Tested", "Done", "Done", "Done"),
    "Message, choice and input node types": ("Tested", "Done", "Done", "Done"),
    "Visual flow builder": ("Tested", "Done", "Done", "Done"),
    # Phase 3 — live human chat, 2026-08-22.
    "Live human chat": ("Tested", "Done", "Done", "Done"),
    "Bot to human handoff": ("Tested", "Done", "Done", "Done"),
    "Agent availability states": ("Tested", "Done", "Done", "Done"),
    "Business hours and offline capture": ("Tested", "Done", "Done", "Done"),
    "Team @mentions": ("Tested", "Done", "Done", "Done"),
    "Agent replies": ("Tested", "Done", "Done", "Done"),
    "Internal notes": ("Tested", "Done", "Done", "Done"),
    # Phase 4 — analytics, targeting, appearance, 2026-08-22.
    "Widget appearance and branding": ("Tested", "Done", "Done", "Done"),
    "Progressive profiling for known visitors": ("Tested", "Done", "Done", "Done"),
    "Page targeting rules": ("Tested", "Done", "Done", "Done"),
    "Visitor behavior targeting": ("Tested", "Done", "Done", "Done"),
    "Chat engagement analytics": ("Tested", "Done", "Done", "Done"),
    "Conversion funnel report": ("Tested", "Done", "Done", "Done"),
    "Per-question drop-off report": ("Tested", "Done", "Done", "Done"),
    "Chat A/B testing": ("Partially Implemented", "Done", "Done", "Done"),
    "Chat notifications to agents": ("Tested", "Done", "Done", "Done"),
    "Flow template library": ("Tested", "Done", "Done", "Done"),
    "MSP and cybersecurity qualification templates": ("Tested", "Done", "Done", "Done"),
    "Existing-customer support routing": ("Tested", "Done", "Done", "Done"),
    "High-priority security incident routing": ("Tested", "Done", "Done", "Done"),
    "Contact capture with configurable fields": ("Tested", "Done", "Done", "Done"),
    "Duplicate detection on capture": ("Tested", "Done", "Done", "Done"),
    "CRM contact and lead creation": ("Tested", "Done", "Done", "Done"),
    "Chat lead scoring": ("Tested", "Done", "Done", "Done"),
    "Sales routing and assignment": ("Tested", "Done", "Done", "Done"),
    "Hot-lead sales alert": ("Tested", "Done", "Done", "Done"),
    "Chat source attribution": ("Tested", "Done", "Done", "Done"),
    "In-chat appointment booking": ("Partially Implemented", "Done", "Done", "Done"),
    "Configurable consent gate": ("Tested", "Done", "Done", "Done"),
    "Per-tenant privacy and terms links": ("Tested", "Done", "Done", "Done"),
    "Agent conversation inbox": ("Tested", "Done", "Done", "Done"),
    "Conversation transcript view": ("Tested", "Done", "Done", "Done"),
    "Conversation status workflow": ("Tested", "Done", "Done", "Done"),
}


def main():
    existing = load_existing_state()
    rows, notes = parse_inventory(INVENTORY_TXT)

    # Sanity: expect 50 sections
    sections = sorted({r["section"] for r in rows})
    assert sections == list(range(1, 51)), f"Missing sections: {set(range(1,51)) - set(sections)}"

    OUT_CSV.parent.mkdir(parents=True, exist_ok=True)
    counters = {}
    out_rows = []

    for r in rows:
        code = MODULE_CODES[r["section"]]
        counters[code] = counters.get(code, 0) + 1
        fid = f"{code}-{counters[code]:03d}"
        out_rows.append({
            "id": fid,
            "module_code": code,
            "module": r["module"],
            "submodule": r["submodule"],
            "feature": r["feature"],
            "origin": "Feature Inventory",
            "source": f"Inventory §{r['section']} p.{r['page']}",
            "status": "Planned",
            "backend": "Pending", "frontend": "Pending",
            "tests": "Pending", "docs": "Pending",
            "depends_on": "", "notes": "",
        })

    for (code, module, sub, feat, src) in PLATFORM:
        counters[code] = counters.get(code, 0) + 1
        fid = f"{code}-{counters[code]:03d}"
        out_rows.append({
            "id": fid,
            "module_code": code,
            "module": module,
            "submodule": sub,
            "feature": feat,
            "origin": "Master Prompt",
            "source": src,
            "status": "Planned",
            "backend": "Pending", "frontend": "Pending",
            "tests": "Pending", "docs": "Pending",
            "depends_on": "", "notes": "",
        })

    for (code, module, sub, feat, src) in QA_FINDINGS:
        counters[code] = counters.get(code, 0) + 1
        fid = f"{code}-{counters[code]:03d}"
        delivered = DELIVERED.get(feat)
        out_rows.append({
            "id": fid,
            "module_code": code,
            "module": module,
            "submodule": sub,
            "feature": feat,
            "origin": "QA Audit",
            "source": src,
            "status": delivered[0] if delivered else "Planned",
            "backend": delivered[1] if delivered else "Pending",
            "frontend": delivered[2] if delivered else "Pending",
            "tests": delivered[3] if delivered else "Pending", "docs": "Pending",
            "depends_on": "", "notes": "",
        })

    for row in out_rows:
        if row["id"] in existing:
            row.update(existing[row["id"]])

    with open(OUT_CSV, "w", newline="", encoding="utf-8-sig") as f:
        w = csv.DictWriter(f, fieldnames=list(out_rows[0].keys()))
        w.writeheader()
        w.writerows(out_rows)

    inv_count = sum(1 for r in out_rows if r["origin"] == "Feature Inventory")
    mp_count = len(out_rows) - inv_count
    print(f"Register written: {OUT_CSV}")
    print(f"Inventory features: {inv_count}  |  Platform features: {mp_count}  |  Total: {len(out_rows)}")
    print("\nPer-module counts (inventory):")
    for n in range(1, 51):
        code = MODULE_CODES[n]
        c = sum(1 for r in rows if r["section"] == n)
        print(f"  {n:2d}. {code:7s} {c:3d}")
    if notes:
        print("\nSection notes captured:", sorted(k for k in notes if k is not None))


if __name__ == "__main__":
    main()
