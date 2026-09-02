import { ConfirmAction } from '@/components/confirm-action';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { CheckCircle2, XCircle } from 'lucide-react';
import { FormEventHandler } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'LLMO', href: '/seo/llmo' }];

const textareaClass =
    'border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring flex min-h-16 w-full rounded-md border px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-hidden';

type CheckItem = { key: string; label: string; ok: boolean; detail: string };

type Expert = {
    id: number;
    name: string;
    title: string | null;
    credentials: string[];
    knows_about: string[];
    is_active: boolean;
};

type Props = {
    completeness: { score: number; items: CheckItem[] };
    entity: {
        legal_name: string | null;
        alternate_names: string[];
        website_url: string | null;
        logo_url: string | null;
        founded_year: number | null;
        same_as: string[];
        disambiguation: string | null;
    };
    experts: Expert[];
    graphJson: string;
    nodeCount: number;
    published: { id: number; created_at: string } | null;
    scoreResult: { score: number; factors: CheckItem[] } | null;
};

function Checklist({ items }: { items: CheckItem[] }) {
    return (
        <ul className="space-y-2">
            {items.map((item) => (
                <li key={item.key} className="flex items-start gap-2 text-sm">
                    {item.ok ? (
                        <CheckCircle2 className="mt-0.5 size-4 shrink-0 text-green-600" />
                    ) : (
                        <XCircle className="text-destructive mt-0.5 size-4 shrink-0" />
                    )}
                    <div>
                        <span className="font-medium">{item.label}</span>
                        <span className="text-muted-foreground"> — {item.detail}</span>
                    </div>
                </li>
            ))}
        </ul>
    );
}

const splitList = (value: string): string[] =>
    value
        .split('\n')
        .map((line) => line.trim())
        .filter((line) => line !== '');

export default function Llmo({ completeness, entity, experts, graphJson, nodeCount, published, scoreResult }: Props) {
    const { can } = usePermissions();
    const canManage = can('seo.ai.manage');

    const entityForm = useForm({
        legal_name: entity.legal_name ?? '',
        alternate_names: entity.alternate_names.join('\n'),
        website_url: entity.website_url ?? '',
        logo_url: entity.logo_url ?? '',
        founded_year: entity.founded_year !== null ? String(entity.founded_year) : '',
        same_as: entity.same_as.join('\n'),
        disambiguation: entity.disambiguation ?? '',
    });

    const saveEntity: FormEventHandler = (e) => {
        e.preventDefault();
        entityForm.transform((data) => ({
            legal_name: data.legal_name || null,
            alternate_names: splitList(data.alternate_names),
            website_url: data.website_url || null,
            logo_url: data.logo_url || null,
            founded_year: data.founded_year === '' ? null : Number(data.founded_year),
            same_as: splitList(data.same_as),
            disambiguation: data.disambiguation || null,
        }));
        entityForm.post(route('seo.llmo.entity'), { preserveScroll: true });
    };

    const expertForm = useForm({ name: '', title: '', bio: '', credentials: '', knows_about: '' });

    const addExpert: FormEventHandler = (e) => {
        e.preventDefault();
        expertForm.transform((data) => ({
            name: data.name,
            title: data.title || null,
            bio: data.bio || null,
            credentials: splitList(data.credentials),
            knows_about: splitList(data.knows_about),
        }));
        expertForm.post(route('seo.llmo.experts.store'), { preserveScroll: true, onSuccess: () => expertForm.reset() });
    };

    const scoreForm = useForm({ html: '' });

    const scoreContent: FormEventHandler = (e) => {
        e.preventDefault();
        scoreForm.post(route('seo.llmo.score'), { preserveScroll: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="LLMO" />
            <div className="space-y-6 p-4">
                <Heading
                    title="LLM Optimization"
                    description="Make your brand one resolvable entity for answer engines: knowledge graph, expertise signals, quotable content"
                />

                <Card>
                    <CardContent className="p-4">
                        <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
                            <h3 className="text-sm font-medium">Retrieval readiness</h3>
                            <Badge variant={completeness.score >= 80 ? 'default' : 'secondary'}>{completeness.score}/100</Badge>
                        </div>
                        <Checklist items={completeness.items} />
                    </CardContent>
                </Card>

                {canManage && (
                    <Card>
                        <CardContent className="p-4">
                            <h3 className="mb-3 text-sm font-medium">Organization entity</h3>
                            <form onSubmit={saveEntity} className="space-y-3">
                                <div className="grid gap-3 sm:grid-cols-2">
                                    <div className="grid gap-1">
                                        <Label htmlFor="legal_name">Legal name</Label>
                                        <Input
                                            id="legal_name"
                                            value={entityForm.data.legal_name}
                                            onChange={(e) => entityForm.setData('legal_name', e.target.value)}
                                        />
                                        <InputError message={entityForm.errors.legal_name} />
                                    </div>
                                    <div className="grid gap-1">
                                        <Label htmlFor="founded_year">Founded year</Label>
                                        <Input
                                            id="founded_year"
                                            type="number"
                                            value={entityForm.data.founded_year}
                                            onChange={(e) => entityForm.setData('founded_year', e.target.value)}
                                        />
                                        <InputError message={entityForm.errors.founded_year} />
                                    </div>
                                    <div className="grid gap-1">
                                        <Label htmlFor="website_url">Website URL</Label>
                                        <Input
                                            id="website_url"
                                            type="url"
                                            placeholder="https://your-msp.com"
                                            value={entityForm.data.website_url}
                                            onChange={(e) => entityForm.setData('website_url', e.target.value)}
                                        />
                                        <InputError message={entityForm.errors.website_url} />
                                    </div>
                                    <div className="grid gap-1">
                                        <Label htmlFor="logo_url">Logo URL</Label>
                                        <Input
                                            id="logo_url"
                                            type="url"
                                            value={entityForm.data.logo_url}
                                            onChange={(e) => entityForm.setData('logo_url', e.target.value)}
                                        />
                                        <InputError message={entityForm.errors.logo_url} />
                                    </div>
                                    <div className="grid gap-1">
                                        <Label htmlFor="alternate_names">Alternate names (one per line)</Label>
                                        <textarea
                                            className={textareaClass}
                                            id="alternate_names"
                                            rows={3}
                                            value={entityForm.data.alternate_names}
                                            onChange={(e) => entityForm.setData('alternate_names', e.target.value)}
                                        />
                                        <InputError message={entityForm.errors.alternate_names} />
                                    </div>
                                    <div className="grid gap-1">
                                        <Label htmlFor="same_as">sameAs profile links (one per line)</Label>
                                        <textarea
                                            className={textareaClass}
                                            id="same_as"
                                            rows={3}
                                            placeholder={'https://www.linkedin.com/company/…\nhttps://g.page/…'}
                                            value={entityForm.data.same_as}
                                            onChange={(e) => entityForm.setData('same_as', e.target.value)}
                                        />
                                        <InputError message={entityForm.errors.same_as} />
                                    </div>
                                </div>
                                <div className="grid gap-1">
                                    <Label htmlFor="disambiguation">Disambiguating description</Label>
                                    <textarea
                                        className={textareaClass}
                                        id="disambiguation"
                                        rows={2}
                                        placeholder="One sentence that separates you from every similarly named business."
                                        value={entityForm.data.disambiguation}
                                        onChange={(e) => entityForm.setData('disambiguation', e.target.value)}
                                    />
                                    <InputError message={entityForm.errors.disambiguation} />
                                </div>
                                <Button type="submit" disabled={entityForm.processing}>
                                    Save entity
                                </Button>
                            </form>
                        </CardContent>
                    </Card>
                )}

                <Card>
                    <CardContent className="p-4">
                        <h3 className="mb-3 text-sm font-medium">Expert profiles</h3>
                        {experts.length === 0 ? (
                            <p className="text-muted-foreground mb-3 text-sm">
                                No expert profiles yet. Credentialed people are the expertise signal answer engines verify.
                            </p>
                        ) : (
                            <div className="mb-4 space-y-2">
                                {experts.map((expert) => (
                                    <div key={expert.id} className="flex flex-wrap items-center justify-between gap-2 rounded-md border p-3">
                                        <div>
                                            <span className="text-sm font-medium">{expert.name}</span>
                                            {expert.title && <span className="text-muted-foreground text-sm"> · {expert.title}</span>}
                                            <div className="mt-1 flex flex-wrap gap-1">
                                                {expert.credentials.map((credential) => (
                                                    <Badge key={credential} variant="outline">
                                                        {credential}
                                                    </Badge>
                                                ))}
                                            </div>
                                        </div>
                                        {canManage && (
                                            <ConfirmAction
                                                title={`Remove ${expert.name}?`}
                                                description="The expert profile leaves the knowledge graph on the next publish."
                                                confirmLabel="Remove"
                                                onConfirm={() =>
                                                    router.delete(route('seo.llmo.experts.destroy', expert.id), { preserveScroll: true })
                                                }
                                            >
                                                <Button size="sm" variant="ghost" className="text-destructive">
                                                    Remove
                                                </Button>
                                            </ConfirmAction>
                                        )}
                                    </div>
                                ))}
                            </div>
                        )}
                        {canManage && (
                            <form onSubmit={addExpert} className="space-y-3">
                                <div className="grid gap-3 sm:grid-cols-2">
                                    <div className="grid gap-1">
                                        <Label htmlFor="expert_name">Name</Label>
                                        <Input
                                            id="expert_name"
                                            value={expertForm.data.name}
                                            onChange={(e) => expertForm.setData('name', e.target.value)}
                                        />
                                        <InputError message={expertForm.errors.name} />
                                    </div>
                                    <div className="grid gap-1">
                                        <Label htmlFor="expert_title">Title</Label>
                                        <Input
                                            id="expert_title"
                                            placeholder="vCIO, Security Lead…"
                                            value={expertForm.data.title}
                                            onChange={(e) => expertForm.setData('title', e.target.value)}
                                        />
                                    </div>
                                    <div className="grid gap-1">
                                        <Label htmlFor="expert_credentials">Credentials (one per line)</Label>
                                        <textarea
                                            className={textareaClass}
                                            id="expert_credentials"
                                            rows={2}
                                            placeholder={'CISSP\nCCNA'}
                                            value={expertForm.data.credentials}
                                            onChange={(e) => expertForm.setData('credentials', e.target.value)}
                                        />
                                    </div>
                                    <div className="grid gap-1">
                                        <Label htmlFor="expert_knows">Knows about (one per line)</Label>
                                        <textarea
                                            className={textareaClass}
                                            id="expert_knows"
                                            rows={2}
                                            placeholder={'CMMC compliance\nMicrosoft 365'}
                                            value={expertForm.data.knows_about}
                                            onChange={(e) => expertForm.setData('knows_about', e.target.value)}
                                        />
                                    </div>
                                </div>
                                <div className="grid gap-1">
                                    <Label htmlFor="expert_bio">Bio</Label>
                                    <textarea
                                        className={textareaClass}
                                        id="expert_bio"
                                        rows={2}
                                        value={expertForm.data.bio}
                                        onChange={(e) => expertForm.setData('bio', e.target.value)}
                                    />
                                </div>
                                <Button type="submit" variant="outline" disabled={expertForm.processing}>
                                    Add expert
                                </Button>
                            </form>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardContent className="p-4">
                        <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
                            <div className="flex items-center gap-2">
                                <h3 className="text-sm font-medium">Knowledge graph</h3>
                                <Badge variant="outline">{nodeCount} nodes</Badge>
                            </div>
                            {canManage && (
                                <Button size="sm" onClick={() => router.post(route('seo.llmo.graph'), {}, { preserveScroll: true })}>
                                    Publish as structured data
                                </Button>
                            )}
                        </div>
                        {published && (
                            <p className="text-muted-foreground mb-2 text-xs">
                                Last published as schema #{published.id} — find it on the Schema page to embed.
                            </p>
                        )}
                        <pre className="bg-muted/50 max-h-96 overflow-auto rounded-md border p-3 text-xs">{graphJson}</pre>
                    </CardContent>
                </Card>

                {canManage && (
                    <Card>
                        <CardContent className="p-4">
                            <h3 className="mb-3 text-sm font-medium">Score content for LLM quotability</h3>
                            <form onSubmit={scoreContent} className="space-y-3">
                                <div className="grid gap-1">
                                    <Label htmlFor="score_html">Page HTML</Label>
                                    <textarea
                                        className={textareaClass}
                                        id="score_html"
                                        rows={6}
                                        placeholder="Paste the page's HTML to score citations, definitions and fact density…"
                                        value={scoreForm.data.html}
                                        onChange={(e) => scoreForm.setData('html', e.target.value)}
                                    />
                                    <InputError message={scoreForm.errors.html} />
                                </div>
                                <Button type="submit" variant="outline" disabled={scoreForm.processing}>
                                    Score content
                                </Button>
                            </form>
                            {scoreResult && (
                                <div className="mt-4 space-y-3">
                                    <div className="flex items-center gap-2">
                                        <Badge variant={scoreResult.score >= 70 ? 'default' : 'secondary'}>{scoreResult.score}/100</Badge>
                                        <span className="text-muted-foreground text-sm">LLMO content score</span>
                                    </div>
                                    <Checklist items={scoreResult.factors} />
                                </div>
                            )}
                        </CardContent>
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}
