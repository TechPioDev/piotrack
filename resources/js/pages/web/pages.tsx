import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogFooter, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import { copyText } from '@/lib/clipboard';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { ArrowDown, ArrowUp, Check, Copy, ExternalLink, Eye, EyeOff, TriangleAlert } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Website', href: '/website' }];

type HealthCheck = {
    check: string;
    passed: boolean;
    detail: string;
};

type Health = {
    score: number;
    checks: HealthCheck[];
};

type Section = {
    id: number;
    type: string;
    heading: string | null;
    body: string | null;
    sort_order: number;
    is_visible: boolean;
};

type SitePage = {
    id: number;
    type: string;
    slug: string;
    title: string;
    meta_title: string | null;
    meta_description: string | null;
    headline: string | null;
    subheadline: string | null;
    status: string;
    view_count: number;
    published_at: string | null;
    service_line_id: number | null;
    vertical_id: number | null;
    seo_location_id: number | null;
    form_id: number | null;
    health: Health;
    sections: Section[];
};

type TemplateOption = {
    key: string;
    name: string;
    type: string;
    description: string;
    sections: number;
};

type SiteReport = {
    pages: number;
    published: number;
    average_score: number;
    weakest: { id: number; title: string; slug: string; score: number }[];
};

type NavigationItem = {
    id: number;
    label: string;
    url: string | null;
    site_page_id: number | null;
    placement: string;
    sort_order: number;
};

type Option = { id: number; name: string };

const NONE = 'none';

const PLACEMENTS = ['header', 'footer'];

const textareaClass =
    'border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring flex min-h-28 w-full rounded-md border px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-hidden';

const humanize = (value: string) => value.replace(/_/g, ' ');

function formatTime(iso: string | null): string {
    return iso === null ? '—' : new Date(iso).toLocaleString();
}

/**
 * A low score means unfinished work, not a fault — so it never renders as an
 * error. The badge steps down in emphasis rather than turning red.
 */
function scoreVariant(score: number): 'default' | 'secondary' | 'outline' {
    if (score >= 80) return 'default';
    if (score >= 50) return 'secondary';

    return 'outline';
}

function nameOf(id: number | null, options: Option[]): string | null {
    return id === null ? null : (options.find((option) => option.id === id)?.name ?? null);
}

function nullIfBlank(value: string): string | null {
    return value.trim() === '' ? null : value;
}

function idOrNull(value: string): number | null {
    return value === NONE ? null : Number(value);
}

/**
 * Published pages are reachable at /s/{slug}; drafts are not reachable at all.
 * The path is rendered rather than the absolute URL so it is identical under SSR,
 * and the absolute URL is only assembled when the user copies it.
 */
function PublicUrl({ page }: { page: SitePage }) {
    const [copied, setCopied] = useState(false);
    const [copyFailed, setCopyFailed] = useState(false);
    const path = `/s/${page.slug}`;

    if (page.status !== 'published') {
        return (
            <p className="text-muted-foreground text-sm">
                Draft — nothing is served at <code>{path}</code> yet. Publishing this page is what makes that URL reachable.
            </p>
        );
    }

    // copyText falls back to execCommand: navigator.clipboard only exists in a
    // secure context, so on a plain-HTTP install the optional chain here used to
    // evaluate to undefined and the button did nothing at all — no copy, and no
    // hint that anything had gone wrong.
    const copy = async () => {
        const ok = await copyText(new URL(path, window.location.origin).toString());
        setCopied(ok);
        setCopyFailed(!ok);
        window.setTimeout(() => {
            setCopied(false);
            setCopyFailed(false);
        }, 3000);
    };

    return (
        <div className="flex flex-wrap items-center gap-2 text-sm">
            <span className="text-muted-foreground">Live at</span>
            <a className="inline-flex items-center gap-1 font-medium underline" href={path} target="_blank" rel="noreferrer">
                <code>{path}</code>
                <ExternalLink className="size-3.5" aria-hidden="true" />
            </a>
            <Button size="sm" variant="outline" onClick={copy} aria-label={`Copy the public URL of ${page.title}`}>
                {copied ? <Check className="size-4" aria-hidden="true" /> : <Copy className="size-4" aria-hidden="true" />}
                {copied ? 'Copied' : 'Copy link'}
            </Button>
            {copyFailed && <span className="text-muted-foreground text-xs">Your browser blocked copying — select the link above instead.</span>}
        </div>
    );
}

/**
 * The score on its own is not actionable, so every failing check is named with
 * the detail the scorer saw. Passing checks stay collapsed — they need no work.
 */
function PageHealth({ health }: { health: Health }) {
    const failing = health.checks.filter((check) => !check.passed);
    const passing = health.checks.filter((check) => check.passed);

    return (
        <div className="space-y-2 rounded-lg border p-3">
            <div className="flex flex-wrap items-center justify-between gap-2 text-sm">
                <span className="font-medium">Page health</span>
                <span className="flex items-center gap-2">
                    <span className="text-muted-foreground text-xs">
                        {passing.length} of {health.checks.length} checks pass
                    </span>
                    <Badge variant={scoreVariant(health.score)}>{health.score}</Badge>
                </span>
            </div>
            <div className="bg-muted h-2 overflow-hidden rounded-full">
                <div className="bg-primary h-full rounded-full" style={{ width: `${health.score}%` }} />
            </div>
            {failing.length === 0 ? (
                <p className="text-muted-foreground text-sm">Every check passes. There is nothing left to fix on this page.</p>
            ) : (
                <div className="space-y-1">
                    <p className="text-muted-foreground text-sm">Still to do — each one fixed raises the score:</p>
                    <ul className="space-y-1">
                        {failing.map((check) => (
                            <li key={check.check} className="flex flex-wrap items-baseline gap-x-2">
                                <span className="text-sm font-medium">{check.check}</span>
                                <span className="text-muted-foreground text-xs">{check.detail}</span>
                            </li>
                        ))}
                    </ul>
                </div>
            )}
            {passing.length > 0 && (
                <details>
                    <summary className="text-muted-foreground cursor-pointer text-sm">Checks already passing ({passing.length})</summary>
                    <ul className="mt-1 space-y-1">
                        {passing.map((check) => (
                            <li key={check.check} className="text-muted-foreground flex flex-wrap items-baseline gap-x-2 text-xs">
                                <span className="font-medium">{check.check}</span>
                                <span>{check.detail}</span>
                            </li>
                        ))}
                    </ul>
                </details>
            )}
        </div>
    );
}

function TemplateGalleryDialog({ templates }: { templates: TemplateOption[] }) {
    const [open, setOpen] = useState(false);
    const [busy, setBusy] = useState<string | null>(null);

    const apply = (key: string) =>
        router.post(
            route('web.pages.template'),
            { template: key },
            {
                preserveScroll: true,
                onStart: () => setBusy(key),
                onFinish: () => setBusy(null),
                onSuccess: () => setOpen(false),
            },
        );

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="outline">Start from template</Button>
            </DialogTrigger>
            <DialogContent className="max-h-[85vh] overflow-y-auto sm:max-w-2xl">
                <DialogTitle>Page templates</DialogTitle>
                <p className="text-muted-foreground text-sm">
                    Each template drafts a page with the buyer-journey sections already ordered and filled with structural guidance. Replace the
                    guidance with your own content, then publish — the health checks hold the page to the same bar as one built from scratch.
                </p>
                <div className="grid gap-3 sm:grid-cols-2">
                    {templates.map((template) => (
                        <Card key={template.key}>
                            <CardContent className="flex h-full flex-col gap-2 p-4">
                                <div className="flex items-center justify-between gap-2">
                                    <p className="font-medium">{template.name}</p>
                                    <Badge variant="secondary">{humanize(template.type)}</Badge>
                                </div>
                                <p className="text-muted-foreground flex-1 text-sm">{template.description}</p>
                                <div className="flex items-center justify-between">
                                    <span className="text-muted-foreground text-xs">{template.sections} sections</span>
                                    <Button size="sm" disabled={busy !== null} onClick={() => apply(template.key)}>
                                        {busy === template.key ? 'Drafting…' : 'Use template'}
                                    </Button>
                                </div>
                            </CardContent>
                        </Card>
                    ))}
                </div>
            </DialogContent>
        </Dialog>
    );
}

function NewPageDialog({
    pageTypes,
    serviceLines,
    verticals,
    locations,
    forms,
}: {
    pageTypes: string[];
    serviceLines: Option[];
    verticals: Option[];
    locations: Option[];
    forms: Option[];
}) {
    const [open, setOpen] = useState(false);
    const form = useForm<{
        title: string;
        slug: string;
        type: string;
        meta_title: string;
        meta_description: string;
        headline: string;
        subheadline: string;
        service_line_id: string;
        vertical_id: string;
        seo_location_id: string;
        form_id: string;
    }>({
        title: '',
        slug: '',
        type: pageTypes[0] ?? 'landing',
        meta_title: '',
        meta_description: '',
        headline: '',
        subheadline: '',
        service_line_id: NONE,
        vertical_id: NONE,
        seo_location_id: NONE,
        form_id: NONE,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.transform((data) => ({
            title: data.title,
            slug: nullIfBlank(data.slug),
            type: data.type,
            meta_title: nullIfBlank(data.meta_title),
            meta_description: nullIfBlank(data.meta_description),
            headline: nullIfBlank(data.headline),
            subheadline: nullIfBlank(data.subheadline),
            service_line_id: idOrNull(data.service_line_id),
            vertical_id: idOrNull(data.vertical_id),
            seo_location_id: idOrNull(data.seo_location_id),
            form_id: idOrNull(data.form_id),
        }));
        form.post(route('web.pages.store'), {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                setOpen(false);
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button>New page</Button>
            </DialogTrigger>
            <DialogContent className="max-h-[85vh] overflow-y-auto">
                <DialogTitle>New page</DialogTitle>
                <form onSubmit={submit} className="space-y-3">
                    <p className="text-muted-foreground text-sm">
                        The page starts as a draft. Add sections and publish it when it is ready — nothing is served publicly until then.
                    </p>
                    <div className="grid gap-1">
                        <Label htmlFor="page_title">Title</Label>
                        <Input id="page_title" value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} />
                        <InputError message={form.errors.title} />
                    </div>
                    <div className="grid grid-cols-2 gap-3">
                        <div className="grid gap-1">
                            <Label htmlFor="page_type">Type</Label>
                            <Select value={form.data.type} onValueChange={(v) => form.setData('type', v)}>
                                <SelectTrigger id="page_type">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {pageTypes.map((type) => (
                                        <SelectItem key={type} value={type}>
                                            {humanize(type)}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={form.errors.type} />
                        </div>
                        <div className="grid gap-1">
                            <Label htmlFor="page_slug">Slug</Label>
                            <Input
                                id="page_slug"
                                value={form.data.slug}
                                onChange={(e) => form.setData('slug', e.target.value)}
                                placeholder="Derived from the title"
                            />
                            <InputError message={form.errors.slug} />
                        </div>
                    </div>
                    <div className="grid gap-1">
                        <Label htmlFor="page_headline">Headline</Label>
                        <Input id="page_headline" value={form.data.headline} onChange={(e) => form.setData('headline', e.target.value)} />
                        <InputError message={form.errors.headline} />
                    </div>
                    <div className="grid gap-1">
                        <Label htmlFor="page_subheadline">Subheadline</Label>
                        <Input id="page_subheadline" value={form.data.subheadline} onChange={(e) => form.setData('subheadline', e.target.value)} />
                        <InputError message={form.errors.subheadline} />
                    </div>
                    <div className="grid gap-1">
                        <Label htmlFor="page_meta_title">Meta title</Label>
                        <Input id="page_meta_title" value={form.data.meta_title} onChange={(e) => form.setData('meta_title', e.target.value)} />
                        <InputError message={form.errors.meta_title} />
                    </div>
                    <div className="grid gap-1">
                        <Label htmlFor="page_meta_description">Meta description</Label>
                        <textarea
                            id="page_meta_description"
                            className={textareaClass}
                            value={form.data.meta_description}
                            onChange={(e) => form.setData('meta_description', e.target.value)}
                            placeholder="50 to 160 characters scores best"
                        />
                        <InputError message={form.errors.meta_description} />
                    </div>
                    <div className="grid grid-cols-2 gap-3">
                        <div className="grid gap-1">
                            <Label htmlFor="page_service_line">Service line</Label>
                            <Select value={form.data.service_line_id} onValueChange={(v) => form.setData('service_line_id', v)}>
                                <SelectTrigger id="page_service_line">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={NONE}>Not targeted</SelectItem>
                                    {serviceLines.map((option) => (
                                        <SelectItem key={option.id} value={String(option.id)}>
                                            {option.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={form.errors.service_line_id} />
                        </div>
                        <div className="grid gap-1">
                            <Label htmlFor="page_vertical">Vertical</Label>
                            <Select value={form.data.vertical_id} onValueChange={(v) => form.setData('vertical_id', v)}>
                                <SelectTrigger id="page_vertical">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={NONE}>Not targeted</SelectItem>
                                    {verticals.map((option) => (
                                        <SelectItem key={option.id} value={String(option.id)}>
                                            {option.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={form.errors.vertical_id} />
                        </div>
                        <div className="grid gap-1">
                            <Label htmlFor="page_location">Location</Label>
                            <Select value={form.data.seo_location_id} onValueChange={(v) => form.setData('seo_location_id', v)}>
                                <SelectTrigger id="page_location">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={NONE}>Not targeted</SelectItem>
                                    {locations.map((option) => (
                                        <SelectItem key={option.id} value={String(option.id)}>
                                            {option.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={form.errors.seo_location_id} />
                        </div>
                        <div className="grid gap-1">
                            <Label htmlFor="page_form">Form</Label>
                            <Select value={form.data.form_id} onValueChange={(v) => form.setData('form_id', v)}>
                                <SelectTrigger id="page_form">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={NONE}>No form</SelectItem>
                                    {forms.map((option) => (
                                        <SelectItem key={option.id} value={String(option.id)}>
                                            {option.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={form.errors.form_id} />
                        </div>
                    </div>
                    <DialogFooter>
                        <Button type="submit" disabled={form.processing || form.data.title.trim() === ''}>
                            Create draft
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function EditPageDialog({ page, forms }: { page: SitePage; forms: Option[] }) {
    const [open, setOpen] = useState(false);
    const form = useForm<{
        title: string;
        meta_title: string;
        meta_description: string;
        headline: string;
        subheadline: string;
        form_id: string;
    }>({
        title: page.title,
        meta_title: page.meta_title ?? '',
        meta_description: page.meta_description ?? '',
        headline: page.headline ?? '',
        subheadline: page.subheadline ?? '',
        form_id: page.form_id === null ? NONE : String(page.form_id),
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.transform((data) => ({
            title: data.title,
            meta_title: nullIfBlank(data.meta_title),
            meta_description: nullIfBlank(data.meta_description),
            headline: nullIfBlank(data.headline),
            subheadline: nullIfBlank(data.subheadline),
            form_id: idOrNull(data.form_id),
        }));
        form.patch(route('web.pages.update', page.id), {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm" variant="outline">
                    Edit page
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[85vh] overflow-y-auto">
                <DialogTitle>Edit {page.title}</DialogTitle>
                <form onSubmit={submit} className="space-y-3">
                    <p className="text-muted-foreground text-sm">
                        The slug is fixed once the page exists — a published URL that changes underneath its visitors is worse than an imperfect one.
                    </p>
                    <div className="grid gap-1">
                        <Label htmlFor={`edit_title_${page.id}`}>Title</Label>
                        <Input id={`edit_title_${page.id}`} value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} />
                        <InputError message={form.errors.title} />
                    </div>
                    <div className="grid gap-1">
                        <Label htmlFor={`edit_headline_${page.id}`}>Headline</Label>
                        <Input
                            id={`edit_headline_${page.id}`}
                            value={form.data.headline}
                            onChange={(e) => form.setData('headline', e.target.value)}
                        />
                        <InputError message={form.errors.headline} />
                    </div>
                    <div className="grid gap-1">
                        <Label htmlFor={`edit_subheadline_${page.id}`}>Subheadline</Label>
                        <Input
                            id={`edit_subheadline_${page.id}`}
                            value={form.data.subheadline}
                            onChange={(e) => form.setData('subheadline', e.target.value)}
                        />
                        <InputError message={form.errors.subheadline} />
                    </div>
                    <div className="grid gap-1">
                        <Label htmlFor={`edit_meta_title_${page.id}`}>Meta title</Label>
                        <Input
                            id={`edit_meta_title_${page.id}`}
                            value={form.data.meta_title}
                            onChange={(e) => form.setData('meta_title', e.target.value)}
                        />
                        <InputError message={form.errors.meta_title} />
                    </div>
                    <div className="grid gap-1">
                        <Label htmlFor={`edit_meta_description_${page.id}`}>Meta description</Label>
                        <textarea
                            id={`edit_meta_description_${page.id}`}
                            className={textareaClass}
                            value={form.data.meta_description}
                            onChange={(e) => form.setData('meta_description', e.target.value)}
                        />
                        <p className="text-muted-foreground text-xs">
                            {form.data.meta_description.length} characters. The health check wants between 50 and 160.
                        </p>
                        <InputError message={form.errors.meta_description} />
                    </div>
                    <div className="grid gap-1">
                        <Label htmlFor={`edit_form_${page.id}`}>Form</Label>
                        <Select value={form.data.form_id} onValueChange={(v) => form.setData('form_id', v)}>
                            <SelectTrigger id={`edit_form_${page.id}`}>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={NONE}>No form</SelectItem>
                                {forms.map((option) => (
                                    <SelectItem key={option.id} value={String(option.id)}>
                                        {option.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={form.errors.form_id} />
                    </div>
                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={() => setOpen(false)}>
                            Cancel
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            Save
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function AddSectionDialog({ page, sectionTypes }: { page: SitePage; sectionTypes: string[] }) {
    const [open, setOpen] = useState(false);
    const form = useForm<{ type: string; heading: string; body: string; is_visible: boolean }>({
        type: sectionTypes[0] ?? 'content',
        heading: '',
        body: '',
        is_visible: true,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.transform((data) => ({
            type: data.type,
            heading: nullIfBlank(data.heading),
            body: nullIfBlank(data.body),
            is_visible: data.is_visible,
        }));
        form.post(route('web.sections.store', page.id), {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                setOpen(false);
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm" variant="outline">
                    Add section
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Add a section to {page.title}</DialogTitle>
                <form onSubmit={submit} className="space-y-3">
                    <div className="grid gap-1">
                        <Label htmlFor={`section_type_${page.id}`}>Type</Label>
                        <Select value={form.data.type} onValueChange={(v) => form.setData('type', v)}>
                            <SelectTrigger id={`section_type_${page.id}`}>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {sectionTypes.map((type) => (
                                    <SelectItem key={type} value={type}>
                                        {humanize(type)}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={form.errors.type} />
                    </div>
                    <div className="grid gap-1">
                        <Label htmlFor={`section_heading_${page.id}`}>Heading</Label>
                        <Input
                            id={`section_heading_${page.id}`}
                            value={form.data.heading}
                            onChange={(e) => form.setData('heading', e.target.value)}
                        />
                        <InputError message={form.errors.heading} />
                    </div>
                    <div className="grid gap-1">
                        <Label htmlFor={`section_body_${page.id}`}>Body</Label>
                        <textarea
                            id={`section_body_${page.id}`}
                            className={textareaClass}
                            value={form.data.body}
                            onChange={(e) => form.setData('body', e.target.value)}
                        />
                        <InputError message={form.errors.body} />
                    </div>
                    <label className="flex items-start gap-3 rounded-lg border p-3" htmlFor={`section_visible_${page.id}`}>
                        <Checkbox
                            id={`section_visible_${page.id}`}
                            checked={form.data.is_visible}
                            onCheckedChange={(v) => form.setData('is_visible', v === true)}
                        />
                        <span className="text-sm">
                            <span className="font-medium">Visible on the page</span>
                            <span className="text-muted-foreground block">
                                Hidden sections stay in the builder but are not rendered, and do not count towards health or publishing.
                            </span>
                        </span>
                    </label>
                    <DialogFooter>
                        <Button type="submit" disabled={form.processing}>
                            Add
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function EditSectionDialog({ section }: { section: Section }) {
    const [open, setOpen] = useState(false);
    const form = useForm<{ heading: string; body: string; is_visible: boolean }>({
        heading: section.heading ?? '',
        body: section.body ?? '',
        is_visible: section.is_visible,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.transform((data) => ({
            heading: nullIfBlank(data.heading),
            body: nullIfBlank(data.body),
            is_visible: data.is_visible,
        }));
        form.patch(route('web.sections.update', section.id), {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm" variant="outline">
                    Edit
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Edit {humanize(section.type)} section</DialogTitle>
                <form onSubmit={submit} className="space-y-3">
                    <p className="text-muted-foreground text-sm">The section type is fixed — add a different section if you need another block.</p>
                    <div className="grid gap-1">
                        <Label htmlFor={`edit_section_heading_${section.id}`}>Heading</Label>
                        <Input
                            id={`edit_section_heading_${section.id}`}
                            value={form.data.heading}
                            onChange={(e) => form.setData('heading', e.target.value)}
                        />
                        <InputError message={form.errors.heading} />
                    </div>
                    <div className="grid gap-1">
                        <Label htmlFor={`edit_section_body_${section.id}`}>Body</Label>
                        <textarea
                            id={`edit_section_body_${section.id}`}
                            className={textareaClass}
                            value={form.data.body}
                            onChange={(e) => form.setData('body', e.target.value)}
                        />
                        <InputError message={form.errors.body} />
                    </div>
                    <label className="flex items-start gap-3 rounded-lg border p-3" htmlFor={`edit_section_visible_${section.id}`}>
                        <Checkbox
                            id={`edit_section_visible_${section.id}`}
                            checked={form.data.is_visible}
                            onCheckedChange={(v) => form.setData('is_visible', v === true)}
                        />
                        <span className="text-sm">
                            <span className="font-medium">Visible on the page</span>
                        </span>
                    </label>
                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={() => setOpen(false)}>
                            Cancel
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            Save
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

/**
 * Order is content, not decoration: the sections render top to bottom in
 * `sort_order`. Moving one posts the whole reordered id list, so the server
 * always receives the complete intended order rather than a delta.
 */
function SectionsTable({ page, canManage }: { page: SitePage; canManage: boolean }) {
    const sections = [...page.sections].sort((a, b) => a.sort_order - b.sort_order);

    const move = (index: number, delta: number) => {
        const target = index + delta;

        if (target < 0 || target >= sections.length) return;

        const ordered = sections.map((section) => section.id);
        [ordered[index], ordered[target]] = [ordered[target], ordered[index]];

        router.post(route('web.sections.reorder', page.id), { ids: ordered }, { preserveScroll: true });
    };

    const toggleVisible = (section: Section) =>
        router.patch(route('web.sections.update', section.id), { is_visible: !section.is_visible }, { preserveScroll: true });

    const remove = (section: Section) => router.delete(route('web.sections.destroy', section.id), { preserveScroll: true });

    if (sections.length === 0) {
        return (
            <p className="text-muted-foreground text-sm">No sections yet. A page needs at least one visible section before it can be published.</p>
        );
    }

    return (
        <div className="overflow-x-auto rounded-lg border">
            <table className="w-full text-left text-sm">
                <thead className="bg-muted/50 text-muted-foreground">
                    <tr>
                        <th className="p-3 text-center font-medium">#</th>
                        <th className="p-3 font-medium">Type</th>
                        <th className="p-3 font-medium">Heading</th>
                        <th className="p-3 font-medium">Body</th>
                        <th className="p-3 font-medium">Visibility</th>
                        {canManage && <th className="p-3 text-right font-medium">Actions</th>}
                    </tr>
                </thead>
                <tbody className="divide-y">
                    {sections.map((section, index) => (
                        <tr key={section.id} className="hover:bg-muted/40 align-top">
                            <td className="text-muted-foreground p-3 text-center">{index + 1}</td>
                            <td className="p-3">
                                <Badge variant="outline">{humanize(section.type)}</Badge>
                            </td>
                            <td className="p-3 font-medium">{section.heading ?? '—'}</td>
                            <td className="text-muted-foreground max-w-80 p-3">{section.body ?? '—'}</td>
                            <td className="p-3">
                                {section.is_visible ? (
                                    <span className="flex items-center gap-1 font-medium">
                                        <Eye className="size-4" aria-hidden="true" />
                                        Visible
                                    </span>
                                ) : (
                                    <span className="text-muted-foreground flex items-center gap-1">
                                        <EyeOff className="size-4" aria-hidden="true" />
                                        Hidden
                                    </span>
                                )}
                            </td>
                            {canManage && (
                                <td className="p-3">
                                    <div className="flex flex-wrap justify-end gap-2">
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            disabled={index === 0}
                                            onClick={() => move(index, -1)}
                                            aria-label={`Move ${humanize(section.type)} section up`}
                                        >
                                            <ArrowUp className="size-4" aria-hidden="true" />
                                        </Button>
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            disabled={index === sections.length - 1}
                                            onClick={() => move(index, 1)}
                                            aria-label={`Move ${humanize(section.type)} section down`}
                                        >
                                            <ArrowDown className="size-4" aria-hidden="true" />
                                        </Button>
                                        <Button size="sm" variant="outline" onClick={() => toggleVisible(section)}>
                                            {section.is_visible ? 'Hide' : 'Show'}
                                        </Button>
                                        <EditSectionDialog section={section} />
                                        <Button size="sm" variant="outline" onClick={() => remove(section)}>
                                            Delete
                                        </Button>
                                    </div>
                                </td>
                            )}
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

function PageCard({
    page,
    canManage,
    forms,
    serviceLines,
    verticals,
    locations,
    sectionTypes,
    publishError,
    onPublishAttempt,
}: {
    page: SitePage;
    canManage: boolean;
    forms: Option[];
    serviceLines: Option[];
    verticals: Option[];
    locations: Option[];
    sectionTypes: string[];
    publishError: string | null;
    onPublishAttempt: (id: number) => void;
}) {
    const published = page.status === 'published';

    const publish = () => {
        onPublishAttempt(page.id);
        router.post(route('web.pages.publish', page.id), {}, { preserveScroll: true });
    };

    const unpublish = () => {
        onPublishAttempt(page.id);
        router.post(route('web.pages.unpublish', page.id), {}, { preserveScroll: true });
    };

    const remove = () => router.delete(route('web.pages.destroy', page.id), { preserveScroll: true });

    const targets = [
        ['Service line', nameOf(page.service_line_id, serviceLines)],
        ['Vertical', nameOf(page.vertical_id, verticals)],
        ['Location', nameOf(page.seo_location_id, locations)],
        ['Form', nameOf(page.form_id, forms)],
    ].filter(([, value]) => value !== null) as [string, string][];

    return (
        <Card>
            <CardContent className="space-y-4 p-4">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div className="min-w-0 space-y-1">
                        <div className="flex flex-wrap items-center gap-2">
                            <p className="font-medium">{page.title}</p>
                            <Badge variant="outline">{humanize(page.type)}</Badge>
                            <Badge variant={published ? 'default' : 'secondary'}>{published ? 'Published' : 'Draft'}</Badge>
                        </div>
                        <PublicUrl page={page} />
                        <p className="text-muted-foreground text-sm">
                            {page.view_count} views · {published ? `published ${formatTime(page.published_at)}` : 'never published'}
                        </p>
                        {targets.length === 0 ? (
                            <p className="text-muted-foreground text-sm">No service line, vertical, location or form attached.</p>
                        ) : (
                            <p className="text-muted-foreground text-sm">{targets.map(([label, value]) => `${label}: ${value}`).join(' · ')}</p>
                        )}
                    </div>
                    {canManage && (
                        <div className="flex shrink-0 flex-wrap items-center gap-2">
                            <EditPageDialog page={page} forms={forms} />
                            <AddSectionDialog page={page} sectionTypes={sectionTypes} />
                            {published ? (
                                <Button size="sm" variant="outline" onClick={unpublish}>
                                    Unpublish
                                </Button>
                            ) : (
                                <Button size="sm" variant="secondary" onClick={publish}>
                                    Publish
                                </Button>
                            )}
                            <Button size="sm" variant="outline" onClick={remove}>
                                Delete
                            </Button>
                        </div>
                    )}
                </div>

                {publishError !== null && (
                    <Alert variant="destructive">
                        <TriangleAlert className="h-4 w-4" />
                        <AlertTitle>This page was not published</AlertTitle>
                        <AlertDescription>{publishError}</AlertDescription>
                    </Alert>
                )}

                <PageHealth health={page.health} />

                <div>
                    <h4 className="mb-2 text-sm font-medium">Sections</h4>
                    <SectionsTable page={page} canManage={canManage} />
                </div>
            </CardContent>
        </Card>
    );
}

function NavigationPanel({ navigation, pages, canManage }: { navigation: NavigationItem[]; pages: SitePage[]; canManage: boolean }) {
    const form = useForm<{ label: string; site_page_id: string; url: string; placement: string; sort_order: string }>({
        label: '',
        site_page_id: NONE,
        url: '',
        placement: 'header',
        sort_order: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.transform((data) => ({
            label: data.label,
            site_page_id: idOrNull(data.site_page_id),
            url: nullIfBlank(data.url),
            placement: data.placement,
            sort_order: data.sort_order === '' ? null : Number(data.sort_order),
        }));
        form.post(route('web.navigation.store'), {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    };

    const remove = (item: NavigationItem) => router.delete(route('web.navigation.destroy', item.id), { preserveScroll: true });

    return (
        <div className="space-y-3">
            {navigation.length === 0 ? (
                <p className="text-muted-foreground text-sm">
                    Nothing in the header or footer yet. A published page is only findable once it is linked.
                </p>
            ) : (
                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-left text-sm">
                        <thead className="bg-muted/50 text-muted-foreground">
                            <tr>
                                <th className="p-3 font-medium">Label</th>
                                <th className="p-3 font-medium">Placement</th>
                                <th className="p-3 font-medium">Target</th>
                                <th className="p-3 text-center font-medium">Order</th>
                                {canManage && <th className="p-3 text-right font-medium">Actions</th>}
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {navigation.map((item) => {
                                const linked = pages.find((page) => page.id === item.site_page_id);

                                return (
                                    <tr key={item.id} className="hover:bg-muted/40">
                                        <td className="p-3 font-medium">{item.label}</td>
                                        <td className="p-3">
                                            <Badge variant="outline">{item.placement}</Badge>
                                        </td>
                                        <td className="text-muted-foreground p-3">
                                            {linked ? `${linked.title} (/s/${linked.slug})` : (item.url ?? '—')}
                                        </td>
                                        <td className="text-muted-foreground p-3 text-center">{item.sort_order}</td>
                                        {canManage && (
                                            <td className="p-3 text-right">
                                                <Button size="sm" variant="outline" onClick={() => remove(item)}>
                                                    Remove
                                                </Button>
                                            </td>
                                        )}
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>
            )}

            {canManage && (
                <form onSubmit={submit} className="grid gap-3 rounded-lg border p-3 sm:grid-cols-5">
                    <div className="grid gap-1">
                        <Label htmlFor="nav_label">Label</Label>
                        <Input id="nav_label" value={form.data.label} onChange={(e) => form.setData('label', e.target.value)} />
                        <InputError message={form.errors.label} />
                    </div>
                    <div className="grid gap-1">
                        <Label htmlFor="nav_placement">Placement</Label>
                        <Select value={form.data.placement} onValueChange={(v) => form.setData('placement', v)}>
                            <SelectTrigger id="nav_placement">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {PLACEMENTS.map((placement) => (
                                    <SelectItem key={placement} value={placement}>
                                        {placement}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={form.errors.placement} />
                    </div>
                    <div className="grid gap-1">
                        <Label htmlFor="nav_page">Page</Label>
                        <Select value={form.data.site_page_id} onValueChange={(v) => form.setData('site_page_id', v)}>
                            <SelectTrigger id="nav_page">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={NONE}>External URL</SelectItem>
                                {pages.map((page) => (
                                    <SelectItem key={page.id} value={String(page.id)}>
                                        {page.title}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={form.errors.site_page_id} />
                    </div>
                    <div className="grid gap-1">
                        <Label htmlFor="nav_url">URL</Label>
                        <Input
                            id="nav_url"
                            value={form.data.url}
                            onChange={(e) => form.setData('url', e.target.value)}
                            placeholder="Used when no page is chosen"
                        />
                        <InputError message={form.errors.url} />
                    </div>
                    <div className="grid gap-1">
                        <Label htmlFor="nav_sort">Order</Label>
                        <Input
                            id="nav_sort"
                            type="number"
                            min={0}
                            value={form.data.sort_order}
                            onChange={(e) => form.setData('sort_order', e.target.value)}
                        />
                        <InputError message={form.errors.sort_order} />
                        <Button type="submit" className="mt-1" disabled={form.processing || form.data.label.trim() === ''}>
                            Add link
                        </Button>
                    </div>
                </form>
            )}
        </div>
    );
}

export default function WebPages({
    pages,
    report,
    navigation,
    service_lines,
    verticals,
    locations,
    forms,
    page_types,
    section_types,
    templates,
}: {
    pages: SitePage[];
    report: SiteReport;
    navigation: NavigationItem[];
    service_lines: Option[];
    verticals: Option[];
    locations: Option[];
    forms: Option[];
    page_types: string[];
    section_types: string[];
    templates: TemplateOption[];
}) {
    const { can } = usePermissions();
    const canManage = can('web.pages.manage');

    // Publishing is refused server-side for a page with no title or no visible
    // section. That refusal comes back under its own key rather than a field, so
    // it is pinned to the page whose publish button was pressed.
    const publishError = usePage<SharedData>().props.errors.page ?? null;
    const [publishTarget, setPublishTarget] = useState<number | null>(null);

    const summary: { label: string; value: string | number; note: string }[] = [
        { label: 'Pages', value: report.pages, note: 'built in total' },
        { label: 'Published', value: report.published, note: `${report.pages - report.published} still draft` },
        { label: 'Average health', value: report.average_score, note: 'out of 100 across every page' },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Website pages" />
            <div className="space-y-6 p-4">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <Heading
                        title="Website pages"
                        description="Every page, how healthy it is, and what has to be fixed before it earns its public URL"
                    />
                    {canManage && (
                        <div className="flex flex-wrap items-center gap-2">
                            <TemplateGalleryDialog templates={templates ?? []} />
                            <NewPageDialog
                                pageTypes={page_types}
                                serviceLines={service_lines}
                                verticals={verticals}
                                locations={locations}
                                forms={forms}
                            />
                        </div>
                    )}
                </div>

                {publishError !== null && publishTarget === null && (
                    <Alert variant="destructive">
                        <TriangleAlert className="h-4 w-4" />
                        <AlertTitle>A page was not published</AlertTitle>
                        <AlertDescription>{publishError}</AlertDescription>
                    </Alert>
                )}

                <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    {summary.map((card) => (
                        <Card key={card.label}>
                            <CardContent className="p-4">
                                <p className="text-muted-foreground text-sm">{card.label}</p>
                                <p className="text-2xl font-semibold">{card.value}</p>
                                <p className="text-muted-foreground text-xs">{card.note}</p>
                            </CardContent>
                        </Card>
                    ))}
                </div>

                <div className="space-y-2 rounded-lg border p-4">
                    <div className="flex flex-wrap items-center justify-between gap-2 text-sm">
                        <span className="font-medium">Site health</span>
                        <Badge variant={scoreVariant(report.average_score)}>{report.average_score}</Badge>
                    </div>
                    <div className="bg-muted h-3 overflow-hidden rounded-full">
                        <div className="bg-primary h-full rounded-full" style={{ width: `${report.average_score}%` }} />
                    </div>
                    {report.weakest.length === 0 ? (
                        <p className="text-muted-foreground text-sm">No pages yet, so there is nothing to score.</p>
                    ) : (
                        <div className="space-y-2">
                            <p className="text-muted-foreground text-sm">Weakest pages first — these are where the next hour of work pays best.</p>
                            <div className="divide-y rounded-lg border">
                                {report.weakest.map((weak) => (
                                    <div key={weak.id} className="flex flex-wrap items-center justify-between gap-3 p-3">
                                        <div className="min-w-0">
                                            <p className="text-sm font-medium">{weak.title}</p>
                                            <p className="text-muted-foreground text-xs">
                                                <code>/s/{weak.slug}</code>
                                            </p>
                                        </div>
                                        <div className="flex min-w-40 flex-1 items-center gap-3">
                                            <div className="bg-muted h-2 flex-1 overflow-hidden rounded-full">
                                                <div className="bg-primary h-full rounded-full" style={{ width: `${weak.score}%` }} />
                                            </div>
                                            <Badge variant={scoreVariant(weak.score)}>{weak.score}</Badge>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}
                </div>

                <div>
                    <h3 className="mb-2 text-sm font-medium">Pages ({pages.length})</h3>
                    {pages.length === 0 ? (
                        <p className="text-muted-foreground text-sm">No pages yet. Create one to start building the site.</p>
                    ) : (
                        <div className="space-y-4">
                            {pages.map((page) => (
                                <PageCard
                                    key={page.id}
                                    page={page}
                                    canManage={canManage}
                                    forms={forms}
                                    serviceLines={service_lines}
                                    verticals={verticals}
                                    locations={locations}
                                    sectionTypes={section_types}
                                    publishError={publishTarget === page.id ? publishError : null}
                                    onPublishAttempt={setPublishTarget}
                                />
                            ))}
                        </div>
                    )}
                </div>

                <div>
                    <h3 className="mb-2 text-sm font-medium">Navigation</h3>
                    <NavigationPanel navigation={navigation} pages={pages} canManage={canManage} />
                </div>
            </div>
        </AppLayout>
    );
}
