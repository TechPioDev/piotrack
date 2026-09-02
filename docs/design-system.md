# piotrack Design System (DSGN)

This document is the reference for how piotrack's UI is built. It describes the
design tokens, the component library, layout patterns, and the conventions every
new screen must follow so the product stays visually and behaviourally
consistent as it grows.

The system is a **Tailwind CSS v4 + shadcn/ui (Radix)** stack rendered through
**Inertia + React 19 + TypeScript**. Styling is token-driven: components never
hard-code colours, they consume semantic CSS variables that flip automatically
between light and dark themes.

---

## 1. Design tokens

Tokens are defined as CSS custom properties in [`resources/css/app.css`](../resources/css/app.css)
and exposed to Tailwind via the `@theme` block, so every token is usable as a
utility class (e.g. `bg-primary`, `text-muted-foreground`, `border-border`).

### Colour (semantic roles)

Never reference a raw colour. Use the role token that describes intent, so dark
mode and future re-theming "just work".

| Token                                        | Utility                             | Used for                             |
| -------------------------------------------- | ----------------------------------- | ------------------------------------ |
| `--background` / `--foreground`              | `bg-background` / `text-foreground` | Page surface + body text             |
| `--card` / `--card-foreground`               | `bg-card`                           | Raised surfaces, panels              |
| `--popover` / `--popover-foreground`         | —                                   | Menus, dialogs, tooltips             |
| `--primary` / `--primary-foreground`         | `bg-primary`                        | Primary actions, emphasis            |
| `--secondary` / `--secondary-foreground`     | `bg-secondary`                      | Secondary buttons/badges             |
| `--muted` / `--muted-foreground`             | `text-muted-foreground`             | De-emphasised text, table headers    |
| `--accent` / `--accent-foreground`           | `bg-accent`                         | Hover/active nav backgrounds         |
| `--destructive` / `--destructive-foreground` | `bg-destructive`                    | Delete, errors, danger states        |
| `--border` / `--input` / `--ring`            | `border-border`                     | Borders, field outlines, focus rings |
| `--chart-1` … `--chart-5`                    | —                                   | Data-viz series                      |
| `--sidebar-*`                                | —                                   | App sidebar surfaces                 |

Both a **light** palette (`:root`) and a **dark** palette (`.dark`) are defined
for every role. The theme is toggled by adding/removing the `.dark` class on the
document root (see §5).

### Radius

One base radius drives all rounding:

- `--radius: 0.5rem` → `--radius-sm` / `--radius-md` / `--radius-lg` derive from it.
- Use `rounded-md` / `rounded-lg` (never arbitrary pixel radii).

### Typography

- Font family: `--font-sans` (Instrument Sans + system fallbacks).
- Scale via Tailwind utilities: body `text-sm`, section titles `text-base`/`font-medium`,
  page headings through the `Heading` / `HeadingSmall` components.
- `muted-foreground` for secondary/descriptive copy.

### Spacing

Use Tailwind's spacing scale (`gap-*`, `p-*`, `space-y-*`). Common rhythms:
`space-y-6` between form sections, `gap-3` in card grids, `p-3` in table cells,
`p-4` in cards.

---

## 2. Component library

Reusable primitives live in [`resources/js/components/ui/`](../resources/js/components/ui)
(shadcn/ui over Radix). Compose these — do not hand-roll equivalents.

**Available primitives:** `alert`, `avatar`, `badge`, `breadcrumb`, `button`,
`card`, `checkbox`, `collapsible`, `dialog`, `dropdown-menu`, `icon`, `input`,
`label`, `navigation-menu`, `select`, `separator`, `sheet`, `sidebar`,
`skeleton`, `toggle` / `toggle-group`, `tooltip`.

Application-level shared components include `Heading`, `HeadingSmall`,
`InputError`, and the app shell (sidebar, breadcrumbs, user menu).

### Buttons

`Button` (CVA variants) is the single button primitive:

| Variant       | When                                        |
| ------------- | ------------------------------------------- |
| `default`     | Primary action on a screen (one per view)   |
| `secondary`   | Alternative action                          |
| `outline`     | Low-emphasis action                         |
| `ghost`       | Toolbar / inline actions, table row actions |
| `destructive` | Irreversible/danger action                  |
| `link`        | Navigation styled as text                   |

Sizes: `sm`, `default`, `lg`, `icon`. Use `asChild` to render a `Link`/`<a>` with
button styling. Destructive row actions add `className="text-red-600"` on a
`ghost` button (see the files and integrations screens).

### Badges

`Badge` communicates status. Convention used across the app:

- `default` → positive / connected / success
- `secondary` → neutral / not-connected / running
- `destructive` → error / failed
- `outline` → informational

### Feedback & empty states

- **Empty state:** a single `text-muted-foreground text-sm` sentence that tells
  the user what to do next (never a blank table).
- **Validation:** `InputError` under the field; server errors arrive via Inertia
  `form.errors`.
- **Flash:** controllers return `->with('status', …)`; the layout surfaces it.

---

## 3. Layout patterns

Two layouts frame every page:

- [`app-layout.tsx`](../resources/js/layouts/app-layout.tsx) — the authenticated
  shell (sidebar nav, breadcrumbs, user menu). All in-product screens use it.
- [`auth-layout.tsx`](../resources/js/layouts/auth-layout.tsx) — centred card for
  login/registration/verification.

Settings screens additionally wrap their content in
[`settings/layout.tsx`](../resources/js/layouts/settings/layout.tsx), which renders
the settings sub-navigation. The sub-nav is **permission-gated**: items appear
only when `usePermissions().can(...)` is true for that user (see §4).

### Page skeleton (settings example)

```tsx
<AppLayout breadcrumbs={breadcrumbs}>
    <Head title="…" />
    <SettingsLayout>
        <div className="space-y-6">
            <HeadingSmall title="…" description="…" />
            {/* content */}
        </div>
    </SettingsLayout>
</AppLayout>
```

### Tables

Use the shared table styling (see `files.tsx`, `integrations.tsx`):

```
overflow-x-auto rounded-lg border  →  <table class="w-full text-left text-sm">
thead: bg-muted/50 text-muted-foreground · th p-3 font-medium
tbody: divide-y · td p-3
```

Wrap wide tables in `overflow-x-auto` so the page body never scrolls sideways.

### Responsiveness

- Mobile-first; layer breakpoints with `sm:` / `md:` / `lg:`.
- Card grids: `grid gap-3 sm:grid-cols-2`.
- The sidebar collapses to a sheet on mobile (`use-mobile`).

---

## 4. Permission-aware UI

Authorization is enforced on the server (the Gate), but the UI **reflects** it so
users never see actions they cannot take. Use the `usePermissions` hook:

```tsx
const { can } = usePermissions();
{
    can('integrations.manage') && <Button>Connect</Button>;
}
```

`can(...)` reads the resolved permission list shared by `HandleInertiaRequests`.
This is a UX affordance, **not** a security boundary — every gated action is also
protected by `can:`/`entitlement:` middleware server-side.

Entitlements (plan features) are shared the same way; gate premium UI on the
entitlement map and show upgrade prompts rather than dead buttons.

---

## 5. Theming (light / dark)

- The palette is fully duplicated for `.dark` in `app.css`.
- `use-appearance` persists the user's choice (light / dark / system) and toggles
  the `.dark` class on `<html>`; system mode follows `prefers-color-scheme`.
- Because components only use role tokens, **no component needs dark-mode-specific
  code**. If you find yourself writing `dark:bg-…`, prefer a semantic token first.

---

## 6. Accessibility

- All interactive primitives are Radix-based → keyboard navigation, focus
  management, and ARIA are handled. Keep using them rather than raw elements.
- Preserve visible focus rings (`--ring`); never remove outlines without a
  replacement.
- Label every input (`Label` + `htmlFor`); associate errors with fields.
- Maintain contrast by using foreground/background token pairs together.
- Icon-only buttons must carry an accessible name (`aria-label` or `sr-only`).

---

## 7. Conventions checklist (new screen)

1. Wrap in `AppLayout` (+ `SettingsLayout` for settings), set `<Head title>`.
2. Use role tokens for all colour; no hex/hsl in components.
3. Compose `ui/` primitives; add a new primitive only if none fits.
4. Provide empty, loading, and error states.
5. Gate every action with `can(...)` / entitlements to match the server.
6. Make it responsive and keyboard-accessible.
7. Keep copy in the muted/foreground hierarchy; one primary action per view.

---

## Status

The token system, component library, layout shell, permission-aware rendering,
and theming described here are **implemented and in use across every shipped
module** (Auth, Tenancy, Billing, Core Platform, CRM, Integrations). This
document is the living reference; extend it (don't fork conventions) as new
patterns are introduced.

---

## 8. UX standards closed in Phase 13 (2 Sep 2026)

### Confirmation flows (DSGN-008)

Destructive actions never fire from a single click. Wrap the trigger in
`components/confirm-action.tsx` — the dialog names exactly what is about to
happen, only the explicit confirm runs the action (`confirm-action.test.tsx`
pins this). Wired: franchise unlink, LLMO expert removal, schema delete; adopt
it for every new destructive control.

### Async states (DSGN-009)

`components/async-states.tsx` is the standard voice for waiting and failure:
`LoadingState` (live-region skeleton), `ErrorState` (explains + a retry that
retries), `PartialFailure` (names what is missing, keeps the rest visible —
adopted on AI Visibility for fixture-provider provenance). The shared Inertia
error page offers **Try again** on 500/503. Tests: `async-states.test.tsx`.

### Automated accessibility checks (DSGN-004)

`components/a11y.test.tsx` runs **axe-core** on the core composites (labeled
form field, data table, open confirmation dialog, async states) on every
`npm run test`. jsdom cannot paint, so color-contrast was measured against the
live app instead: body text **18.7:1**, muted text **5.03:1** (raised from a
measured 4.48:1 by darkening `--muted-foreground` to 42%). A formal external
WCAG audit remains on the production-hardening list.

### Responsive verification matrix (DSGN-003)

Measured live (document scrollWidth vs clientWidth) on 2 Sep 2026 at 375,
768, 1280 and 1920px across: /dashboard, /crm/deals, /crm/contacts,
/seo/keywords, /website/taxonomy, /settings/franchise, /seo/llmo, /seo/audits.
**All 8 pages contained at all 4 widths** after two fixes this pass: the
keywords toolbar wrapped (`flex-wrap`) and regional-calendar cards got
`min-w-0` in their grid. Standard: wide content scrolls inside its own
`overflow-x-auto` container; grid/flex children that hold tables or long text
carry `min-w-0`; toolbars wrap.

### Dashboard date ranges (DSGN-006)

The Command Center compares a user-selected window (30/60/90 days) against the
window before it — KPIs and trends always describe the same days
(`CommandCenterService::forWindow`, pinned in CommandCenterTest).
