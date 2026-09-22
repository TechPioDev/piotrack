import { FormErrors } from '@/components/form-errors';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { widgetPayload } from '@/lib/chat-widget';
import { copyText } from '@/lib/clipboard';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { Check, Copy, ImageIcon } from 'lucide-react';
import { FormEventHandler, useRef, useState } from 'react';

type Widget = {
    id: number;
    name: string;
    description: string | null;
    status: string;
    public_key: string;
    theme: Record<string, string>;
    consent: Record<string, unknown>;
    settings: Record<string, unknown>;
    targeting: Record<string, unknown>;
    business_hours: Record<string, unknown>;
    allowed_domains: string[];
    logo_url: string | null;
    can_hide_branding: boolean;
    embed: string;
};

const DAYS = [
    { key: 'mon', label: 'Monday' },
    { key: 'tue', label: 'Tuesday' },
    { key: 'wed', label: 'Wednesday' },
    { key: 'thu', label: 'Thursday' },
    { key: 'fri', label: 'Friday' },
    { key: 'sat', label: 'Saturday' },
    { key: 'sun', label: 'Sunday' },
];

/**
 * The widget logo: uploads on choice rather than with "Save settings", because
 * a file cannot ride along in the JSON settings payload. preserveState keeps any
 * unsaved edits elsewhere on the page intact across the upload.
 */
function LogoField({ widgetId, logoUrl }: { widgetId: number; logoUrl: string | null }) {
    const upload = useForm<{ logo: File | null }>({ logo: null });
    const input = useRef<HTMLInputElement>(null);
    const [removing, setRemoving] = useState(false);

    const choose = (file: File | undefined) => {
        if (!file) return;
        upload.transform(() => ({ logo: file }));
        upload.post(route('chat.widgets.logo.store', widgetId), {
            forceFormData: true,
            preserveScroll: true,
            preserveState: true,
            onFinish: () => {
                if (input.current) input.current.value = '';
            },
        });
    };

    const remove = () =>
        router.delete(route('chat.widgets.logo.destroy', widgetId), {
            preserveScroll: true,
            preserveState: true,
            onStart: () => setRemoving(true),
            onFinish: () => setRemoving(false),
        });

    return (
        <div className="grid gap-1">
            <Label htmlFor="logo">Logo</Label>
            <div className="flex flex-wrap items-center gap-3">
                <span className="border-border bg-background flex size-12 shrink-0 items-center justify-center overflow-hidden rounded-full border p-1">
                    {logoUrl ? (
                        <img src={logoUrl} alt="Current logo" className="size-full object-contain" />
                    ) : (
                        <ImageIcon className="text-muted-foreground size-5" aria-hidden />
                    )}
                </span>
                <input
                    ref={input}
                    id="logo"
                    type="file"
                    accept="image/png,image/jpeg,image/webp"
                    className="sr-only"
                    aria-describedby="logo-help"
                    onChange={(e) => choose(e.target.files?.[0])}
                    disabled={upload.processing}
                />
                <Button type="button" variant="outline" size="sm" onClick={() => input.current?.click()} disabled={upload.processing}>
                    {upload.processing ? 'Uploading…' : logoUrl ? 'Replace logo' : 'Upload logo'}
                </Button>
                {logoUrl && (
                    <Button type="button" variant="ghost" size="sm" onClick={remove} disabled={removing || upload.processing}>
                        {removing ? 'Removing…' : 'Remove'}
                    </Button>
                )}
            </div>
            <p id="logo-help" className="text-muted-foreground text-xs">
                PNG, JPG or WebP, up to 512 KB. Shown in the chat header on your website.
            </p>
            <InputError message={upload.errors.logo} />
        </div>
    );
}

/** A titled block of settings, so the page reads as sections rather than a wall of fields. */
function Section({ title, description, children }: { title: string; description?: string; children: React.ReactNode }) {
    return (
        <div className="border-border bg-card rounded-lg border">
            <div className="border-border border-b px-4 py-3">
                <h2 className="text-foreground text-sm font-semibold">{title}</h2>
                {description && <p className="text-muted-foreground text-xs">{description}</p>}
            </div>
            <div className="space-y-4 p-4">{children}</div>
        </div>
    );
}

export default function WidgetSettings({ widget }: { widget: Widget }) {
    const [copied, setCopied] = useState(false);
    const [copyFailed, setCopyFailed] = useState(false);
    const snippetRef = useRef<HTMLPreElement>(null);

    const hours = (widget.business_hours.days ?? {}) as Record<string, [string, string] | null>;

    const form = useForm({
        name: widget.name,
        description: widget.description ?? '',
        theme: {
            title: widget.theme.title ?? 'Chat with us',
            company: widget.theme.company ?? widget.name,
            accent: widget.theme.accent ?? '#0bb39e',
            position: widget.theme.position ?? 'bottom-right',
        },
        settings: {
            teaser: (widget.settings.teaser as string) ?? '',
            teaser_b: (widget.settings.teaser_b as string) ?? '',
            teaser_delay: (widget.settings.teaser_delay as number) ?? 4,
            mode: (widget.settings.mode as string) ?? 'bot',
            experiment: (widget.settings.experiment as string) ?? '',
            variant: (widget.settings.variant as string) ?? '',
            fallback_contact: (widget.settings.fallback_contact as string) ?? '',
            suggested_questions: ((widget.settings.suggested_questions as string[]) ?? []).join('\n'),
            attachments: (widget.settings.attachments as boolean | undefined) ?? true,
            email_replies: (widget.settings.email_replies as boolean | undefined) ?? true,
            hide_branding: Boolean(widget.settings.hide_branding),
        },
        consent: {
            required: Boolean(widget.consent.required),
            message: (widget.consent.message as string) ?? '',
            privacy_url: (widget.consent.privacy_url as string) ?? '',
        },
        targeting: {
            include: ((widget.targeting.include as string[]) ?? []).join('\n'),
            exclude: ((widget.targeting.exclude as string[]) ?? []).join('\n'),
            visitor: (widget.targeting.visitor as string) ?? 'all',
            delay_seconds: (widget.targeting.delay_seconds as number) ?? 0,
            scroll_percent: (widget.targeting.scroll_percent as number) ?? 0,
            exit_intent: Boolean(widget.targeting.exit_intent),
        },
        business_hours: {
            timezone: (widget.business_hours.timezone as string) ?? 'UTC',
            closed_message: (widget.business_hours.closed_message as string) ?? '',
            days: hours,
        },
        allowed_domains: widget.allowed_domains.join('\n'),
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.transform(widgetPayload);
        form.patch(route('chat.widgets.update', widget.id), { preserveScroll: true });
    };

    const copy = async () => {
        const ok = await copyText(widget.embed);
        if (!ok) {
            // Copying can be blocked; select the snippet so Ctrl+C still works.
            snippetRef.current?.focus();
            window.getSelection()?.selectAllChildren(snippetRef.current as Node);
            setCopyFailed(true);
            setTimeout(() => setCopyFailed(false), 5000);
            return;
        }
        setCopied(true);
        setTimeout(() => setCopied(false), 2000);
    };

    const setDay = (day: string, index: 0 | 1, value: string) => {
        const current = (form.data.business_hours.days[day] ?? ['09:00', '17:00']) as [string, string];
        const next: [string, string] = index === 0 ? [value, current[1]] : [current[0], value];
        form.setData('business_hours', { ...form.data.business_hours, days: { ...form.data.business_hours.days, [day]: next } });
    };

    const toggleDay = (day: string, open: boolean) => {
        form.setData('business_hours', {
            ...form.data.business_hours,
            days: { ...form.data.business_hours.days, [day]: open ? ['09:00', '17:00'] : null },
        });
    };

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Website Chat', href: '/chat' },
        { title: 'Widgets', href: '/chat/widgets' },
        { title: widget.name, href: `/chat/widgets/${widget.id}/settings` },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`${widget.name} — settings`} />
            <form onSubmit={submit} className="space-y-4 p-4">
                <FormErrors errors={form.errors} />
                <PageHeader
                    title="Widget settings"
                    description="How this widget looks, who sees it, and when your team is available."
                    actions={
                        <div className="flex items-center gap-2">
                            {/* Confirmation next to the button, not only at the top
                                of the page: on a long form the flash banner can be
                                scrolled out of sight and the save looks ignored. */}
                            {form.recentlySuccessful && (
                                <span className="flex items-center gap-1 text-sm font-medium text-emerald-600 dark:text-emerald-400">
                                    <Check className="size-4" aria-hidden /> Saved
                                </span>
                            )}
                            <Button variant="outline" asChild>
                                <Link href={route('chat.flow.edit', widget.id)}>Edit conversation</Link>
                            </Button>
                            <Button type="submit" disabled={form.processing}>
                                {form.processing ? 'Saving…' : 'Save settings'}
                            </Button>
                        </div>
                    }
                />

                <div className="grid gap-4 lg:grid-cols-2">
                    <Section title="Appearance" description="How the widget presents itself on your website.">
                        <div className="grid gap-3 sm:grid-cols-2">
                            <div className="grid gap-1">
                                <Label htmlFor="title">Chat title</Label>
                                <Input
                                    id="title"
                                    value={form.data.theme.title}
                                    onChange={(e) => form.setData('theme', { ...form.data.theme, title: e.target.value })}
                                />
                            </div>
                            <div className="grid gap-1">
                                <Label htmlFor="company">Company name</Label>
                                <Input
                                    id="company"
                                    value={form.data.theme.company}
                                    onChange={(e) => form.setData('theme', { ...form.data.theme, company: e.target.value })}
                                />
                            </div>
                            <div className="grid gap-1">
                                <Label htmlFor="accent">Accent colour</Label>
                                <div className="flex gap-2">
                                    <input
                                        id="accent"
                                        type="color"
                                        className="border-border h-9 w-12 shrink-0 rounded-md border"
                                        value={form.data.theme.accent}
                                        onChange={(e) => form.setData('theme', { ...form.data.theme, accent: e.target.value })}
                                        aria-label="Accent colour"
                                    />
                                    <Input
                                        value={form.data.theme.accent}
                                        onChange={(e) => form.setData('theme', { ...form.data.theme, accent: e.target.value })}
                                    />
                                </div>
                            </div>
                            <div className="grid gap-1">
                                <Label>Position</Label>
                                <Select
                                    value={form.data.theme.position}
                                    onValueChange={(v) => form.setData('theme', { ...form.data.theme, position: v })}
                                >
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="bottom-right">Bottom right</SelectItem>
                                        <SelectItem value="bottom-left">Bottom left</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>

                        <div className="grid gap-3 sm:grid-cols-[1fr_120px]">
                            <div className="grid gap-1">
                                <Label htmlFor="teaser">Welcome teaser</Label>
                                <Input
                                    id="teaser"
                                    placeholder="Need help with IT or cybersecurity?"
                                    value={form.data.settings.teaser}
                                    onChange={(e) => form.setData('settings', { ...form.data.settings, teaser: e.target.value })}
                                />
                            </div>
                            <div className="grid gap-1">
                                <Label htmlFor="teaser_b">Teaser B (A/B test)</Label>
                                <Input
                                    id="teaser_b"
                                    placeholder="Leave blank for no test"
                                    value={form.data.settings.teaser_b}
                                    onChange={(e) => form.setData('settings', { ...form.data.settings, teaser_b: e.target.value })}
                                />
                            </div>
                            <div className="grid gap-1">
                                <Label htmlFor="teaser_delay">After (seconds)</Label>
                                <Input
                                    id="teaser_delay"
                                    type="number"
                                    min={0}
                                    value={form.data.settings.teaser_delay}
                                    onChange={(e) => form.setData('settings', { ...form.data.settings, teaser_delay: Number(e.target.value) })}
                                />
                            </div>
                        </div>

                        <LogoField widgetId={widget.id} logoUrl={widget.logo_url} />

                        <div className="grid gap-1">
                            <label className="flex items-center gap-2 text-sm">
                                <input
                                    type="checkbox"
                                    checked={form.data.settings.hide_branding}
                                    disabled={!widget.can_hide_branding}
                                    aria-describedby="branding-help"
                                    onChange={(e) => form.setData('settings', { ...form.data.settings, hide_branding: e.target.checked })}
                                />
                                Hide “Powered by Piotrack”
                            </label>
                            <p id="branding-help" className="text-muted-foreground text-xs">
                                {widget.can_hide_branding
                                    ? 'The chat window then carries only your own brand.'
                                    : 'Included in the Agency and Enterprise plans.'}
                            </p>
                            <InputError message={(form.errors as Record<string, string | undefined>)['settings.hide_branding']} />
                        </div>

                        {/* A live preview of the chat header and launcher, so colour and logo choices are not blind. */}
                        <div className="border-border bg-muted/30 space-y-2 rounded-lg border p-3" aria-hidden>
                            <div className="flex items-center gap-3">
                                <div
                                    className="flex min-w-0 flex-1 items-center gap-2.5 rounded-md px-3 py-2 text-white"
                                    style={{ background: form.data.theme.accent }}
                                >
                                    {widget.logo_url ? (
                                        <span className="flex size-8 shrink-0 items-center justify-center overflow-hidden rounded-full bg-white p-0.5">
                                            <img src={widget.logo_url} alt="" className="size-full object-contain" />
                                        </span>
                                    ) : (
                                        <span className="flex size-8 shrink-0 items-center justify-center rounded-full bg-white/20 text-xs font-bold">
                                            {(form.data.theme.company || widget.name).trim().slice(0, 2).toUpperCase()}
                                        </span>
                                    )}
                                    <span className="truncate text-sm font-semibold">{form.data.theme.title || 'Chat with us'}</span>
                                </div>
                                <span
                                    className="flex size-11 shrink-0 items-center justify-center rounded-full text-white shadow-md"
                                    style={{ background: form.data.theme.accent }}
                                >
                                    <svg
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        strokeWidth="2"
                                        strokeLinecap="round"
                                        className="size-5"
                                    >
                                        <path d="M21 11.5a8.4 8.4 0 0 1-9 8.4 8.9 8.9 0 0 1-3.8-.8L3 21l1.9-5A8.4 8.4 0 0 1 12 3.1a8.4 8.4 0 0 1 9 8.4z" />
                                    </svg>
                                </span>
                            </div>
                            <p className="text-muted-foreground text-xs">
                                Chat header and launcher preview · {form.data.theme.position === 'bottom-left' ? 'bottom left' : 'bottom right'}
                            </p>
                        </div>
                    </Section>

                    <Section title="Who can chat" description="Bot only, a human, or the bot first with a handover.">
                        <div className="grid gap-1 sm:max-w-xs">
                            <Label>Chat mode</Label>
                            <Select
                                value={form.data.settings.mode}
                                onValueChange={(v) => form.setData('settings', { ...form.data.settings, mode: v })}
                            >
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="bot">Automated only</SelectItem>
                                    <SelectItem value="bot_then_human">Automated, then a person</SelectItem>
                                    <SelectItem value="live">Straight to a person</SelectItem>
                                </SelectContent>
                            </Select>
                            <p className="text-muted-foreground text-xs">
                                A person is only offered when someone is online and you are inside business hours.
                            </p>
                        </div>

                        <div className="grid gap-1">
                            <Label htmlFor="suggested_questions">Suggested questions (one per line, up to 6)</Label>
                            <textarea
                                id="suggested_questions"
                                className="border-input bg-background min-h-24 w-full rounded-md border px-3 py-2 text-sm"
                                placeholder={'What services do you offer?\nDo you support Microsoft 365?'}
                                value={form.data.settings.suggested_questions}
                                onChange={(e) => form.setData('settings', { ...form.data.settings, suggested_questions: e.target.value })}
                            />
                            <p className="text-muted-foreground text-xs">
                                Shown as tappable chips when a visitor reaches the AI question step, so they know what it can answer.
                            </p>
                        </div>

                        <div className="grid gap-1">
                            <Label htmlFor="fallback">Fallback contact</Label>
                            <Input
                                id="fallback"
                                placeholder="support@yourcompany.com"
                                value={form.data.settings.fallback_contact}
                                onChange={(e) => form.setData('settings', { ...form.data.settings, fallback_contact: e.target.value })}
                            />
                            <p className="text-muted-foreground text-xs">
                                Shown if the chat service is ever unreachable, so a visitor is never stuck.
                            </p>
                        </div>

                        <div className="grid gap-2">
                            <label className="flex items-start gap-2 text-sm">
                                <input
                                    type="checkbox"
                                    className="mt-1"
                                    checked={form.data.settings.attachments}
                                    onChange={(e) => form.setData('settings', { ...form.data.settings, attachments: e.target.checked })}
                                />
                                <span>
                                    Let visitors send files
                                    <span className="text-muted-foreground block text-xs">
                                        Images, PDFs, text, Word and Excel files up to 5 MB, checked before they are stored. Only your team can open
                                        them.
                                    </span>
                                </span>
                            </label>
                            <label className="flex items-start gap-2 text-sm">
                                <input
                                    type="checkbox"
                                    className="mt-1"
                                    checked={form.data.settings.email_replies}
                                    onChange={(e) => form.setData('settings', { ...form.data.settings, email_replies: e.target.checked })}
                                />
                                <span>
                                    Email replies to visitors who have left
                                    <span className="text-muted-foreground block text-xs">
                                        If a visitor has closed the chat when your team replies, the reply is sent to the email they gave. Their
                                        answer by email goes straight to whoever replied.
                                    </span>
                                </span>
                            </label>
                        </div>

                        <div className="grid gap-3 sm:grid-cols-2">
                            <div className="grid gap-1">
                                <Label htmlFor="experiment">Experiment key</Label>
                                <Input
                                    id="experiment"
                                    placeholder="homepage-greeting"
                                    value={form.data.settings.experiment}
                                    onChange={(e) => form.setData('settings', { ...form.data.settings, experiment: e.target.value })}
                                />
                            </div>
                            <div className="grid gap-1">
                                <Label htmlFor="variant">Variant</Label>
                                <Input
                                    id="variant"
                                    placeholder="A"
                                    value={form.data.settings.variant}
                                    onChange={(e) => form.setData('settings', { ...form.data.settings, variant: e.target.value })}
                                />
                            </div>
                            <p className="text-muted-foreground text-xs sm:col-span-2">
                                Give two widgets the same experiment key to compare them side by side in analytics.
                            </p>
                        </div>
                    </Section>

                    <Section title="Where it appears" description="Leave the page rules empty to show on every page.">
                        <div className="grid gap-3 sm:grid-cols-2">
                            <div className="grid gap-1">
                                <Label htmlFor="include">Only these pages</Label>
                                <textarea
                                    id="include"
                                    rows={4}
                                    className="border-border bg-background focus:outline-brand w-full rounded-md border p-2 font-mono text-xs"
                                    placeholder={'/cybersecurity\n/services/*'}
                                    value={form.data.targeting.include}
                                    onChange={(e) => form.setData('targeting', { ...form.data.targeting, include: e.target.value })}
                                />
                            </div>
                            <div className="grid gap-1">
                                <Label htmlFor="exclude">Never these pages</Label>
                                <textarea
                                    id="exclude"
                                    rows={4}
                                    className="border-border bg-background focus:outline-brand w-full rounded-md border p-2 font-mono text-xs"
                                    placeholder={'/careers\n/privacy'}
                                    value={form.data.targeting.exclude}
                                    onChange={(e) => form.setData('targeting', { ...form.data.targeting, exclude: e.target.value })}
                                />
                            </div>
                            <p className="text-muted-foreground text-xs sm:col-span-2">
                                One rule per line; <code>*</code> matches anything. To match a link setting such as a campaign tag, add it after a{' '}
                                <code>?</code> — <code>?utm_source=google</code> on any page, or <code>/pricing?ref=partner*</code> on one.
                            </p>
                        </div>

                        <div className="grid gap-3 sm:grid-cols-3">
                            <div className="grid gap-1">
                                <Label>Show to</Label>
                                <Select
                                    value={form.data.targeting.visitor}
                                    onValueChange={(v) => form.setData('targeting', { ...form.data.targeting, visitor: v })}
                                >
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">Everyone</SelectItem>
                                        <SelectItem value="first">First-time visitors</SelectItem>
                                        <SelectItem value="returning">Returning visitors</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="grid gap-1">
                                <Label htmlFor="delay">Wait (seconds)</Label>
                                <Input
                                    id="delay"
                                    type="number"
                                    min={0}
                                    value={form.data.targeting.delay_seconds}
                                    onChange={(e) => form.setData('targeting', { ...form.data.targeting, delay_seconds: Number(e.target.value) })}
                                />
                            </div>
                            <div className="grid gap-1">
                                <Label htmlFor="scroll">After scrolling (%)</Label>
                                <Input
                                    id="scroll"
                                    type="number"
                                    min={0}
                                    max={100}
                                    value={form.data.targeting.scroll_percent}
                                    onChange={(e) => form.setData('targeting', { ...form.data.targeting, scroll_percent: Number(e.target.value) })}
                                />
                            </div>
                        </div>

                        <label className="flex items-center gap-2 text-sm">
                            <input
                                type="checkbox"
                                checked={form.data.targeting.exit_intent}
                                onChange={(e) => form.setData('targeting', { ...form.data.targeting, exit_intent: e.target.checked })}
                            />
                            Also show when a visitor moves to leave the page
                        </label>
                    </Section>

                    <Section title="Business hours" description="Outside these hours the chat captures details instead of offering a person.">
                        <div className="grid gap-1 sm:max-w-xs">
                            <Label htmlFor="tz">Timezone</Label>
                            <Input
                                id="tz"
                                placeholder="America/New_York"
                                value={form.data.business_hours.timezone}
                                onChange={(e) => form.setData('business_hours', { ...form.data.business_hours, timezone: e.target.value })}
                            />
                        </div>

                        <div className="space-y-1.5">
                            {DAYS.map((day) => {
                                const window = form.data.business_hours.days[day.key];
                                const open = Array.isArray(window);
                                return (
                                    <div key={day.key} className="flex items-center gap-2">
                                        <label className="flex w-32 items-center gap-2 text-sm">
                                            <input type="checkbox" checked={open} onChange={(e) => toggleDay(day.key, e.target.checked)} />
                                            {day.label}
                                        </label>
                                        {open ? (
                                            <>
                                                <Input
                                                    type="time"
                                                    className="w-32"
                                                    value={window[0]}
                                                    onChange={(e) => setDay(day.key, 0, e.target.value)}
                                                    aria-label={`${day.label} opens`}
                                                />
                                                <span className="text-muted-foreground text-sm">to</span>
                                                <Input
                                                    type="time"
                                                    className="w-32"
                                                    value={window[1]}
                                                    onChange={(e) => setDay(day.key, 1, e.target.value)}
                                                    aria-label={`${day.label} closes`}
                                                />
                                            </>
                                        ) : (
                                            <span className="text-muted-foreground text-sm">Closed</span>
                                        )}
                                    </div>
                                );
                            })}
                            <p className="text-muted-foreground text-xs">Leave every day closed to keep the widget always available.</p>
                        </div>

                        <div className="grid gap-1">
                            <Label htmlFor="closed">Out-of-hours message</Label>
                            <Input
                                id="closed"
                                placeholder="Our team is offline right now — leave your details and we will reply next business day."
                                value={form.data.business_hours.closed_message}
                                onChange={(e) => form.setData('business_hours', { ...form.data.business_hours, closed_message: e.target.value })}
                            />
                        </div>
                    </Section>

                    <Section title="Privacy & consent" description="Shown before anything the visitor types is stored.">
                        <label className="flex items-center gap-2 text-sm">
                            <input
                                type="checkbox"
                                checked={form.data.consent.required}
                                onChange={(e) => form.setData('consent', { ...form.data.consent, required: e.target.checked })}
                            />
                            Ask visitors to accept before chatting
                        </label>
                        <div className="grid gap-1">
                            <Label htmlFor="consent-message">Consent message</Label>
                            <Input
                                id="consent-message"
                                placeholder="We use this chat to respond to your request and may retain the conversation."
                                value={form.data.consent.message}
                                onChange={(e) => form.setData('consent', { ...form.data.consent, message: e.target.value })}
                            />
                        </div>
                        <div className="grid gap-1">
                            <Label htmlFor="privacy">Privacy policy URL</Label>
                            <Input
                                id="privacy"
                                type="url"
                                placeholder="https://yourcompany.com/privacy"
                                value={form.data.consent.privacy_url}
                                onChange={(e) => form.setData('consent', { ...form.data.consent, privacy_url: e.target.value })}
                            />
                        </div>
                    </Section>

                    <Section title="Install" description="Paste this before the closing body tag of your website.">
                        <pre ref={snippetRef} tabIndex={-1} className="bg-muted overflow-x-auto rounded-md p-3 font-mono text-xs">
                            {widget.embed}
                        </pre>
                        <div className="flex flex-wrap items-center gap-2">
                            <Button type="button" variant="outline" onClick={copy}>
                                {copied ? (
                                    <>
                                        <Check className="size-3.5" aria-hidden /> Copied
                                    </>
                                ) : (
                                    <>
                                        <Copy className="size-3.5" aria-hidden /> Copy install code
                                    </>
                                )}
                            </Button>
                            {copyFailed && (
                                <span className="text-muted-foreground text-xs">
                                    Your browser blocked copying — the code is selected, press Ctrl+C.
                                </span>
                            )}
                        </div>

                        <div className="grid gap-1">
                            <Label htmlFor="domains">Allowed domains</Label>
                            <textarea
                                id="domains"
                                rows={3}
                                className="border-border bg-background focus:outline-brand w-full rounded-md border p-2 font-mono text-xs"
                                placeholder={'acmeit.com\nwww.acmeit.com'}
                                value={form.data.allowed_domains}
                                onChange={(e) => form.setData('allowed_domains', e.target.value)}
                            />
                            <p className="text-muted-foreground text-xs">
                                One per line. Leave empty to allow any site. Subdomains of a listed domain are allowed.
                            </p>
                        </div>

                        <div className="text-muted-foreground space-y-1 text-xs">
                            <p className="text-foreground font-medium">Other ways to install</p>
                            <p>
                                <strong>WordPress:</strong> paste the snippet into your theme&apos;s footer, or any &quot;header and footer
                                scripts&quot; plugin.
                            </p>
                            <p>
                                <strong>Google Tag Manager:</strong> new tag → Custom HTML → paste → trigger on All Pages.
                            </p>
                            <p>
                                <strong>Webflow / Squarespace / Wix:</strong> site settings → custom code → footer.
                            </p>
                            <p>These are instructions, not one-click integrations — the snippet is all any of them needs.</p>
                        </div>
                    </Section>
                </div>
            </form>
        </AppLayout>
    );
}
