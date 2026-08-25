import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { Check, CirclePlay, LoaderCircle, TriangleAlert } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'AI Provider', href: '/platform/ai' }];

type ProviderInfo = { key_hint: string | null; model: string };
type TestResult = { ok: boolean; model?: string; latency_ms?: number; reply?: string; error?: string };

const PROVIDERS: { id: string; label: string; keyUrl: string | null; note: string }[] = [
    {
        id: 'fixture',
        label: 'Fixture (built-in, no key)',
        keyUrl: null,
        note: 'Deterministic placeholder answers. Free, offline, and clearly not a real model — right for testing the plumbing, wrong for visitors.',
    },
    {
        id: 'anthropic',
        label: 'Anthropic (Claude)',
        keyUrl: 'console.anthropic.com',
        note: 'Claude models. Strong instruction-following and low fabrication — a good fit for grounded answers that must not invent commitments.',
    },
    {
        id: 'openai',
        label: 'OpenAI (GPT)',
        keyUrl: 'platform.openai.com',
        note: 'GPT models. The most widely integrated option; gpt-4o-mini is a capable, inexpensive default.',
    },
    {
        id: 'gemini',
        label: 'Google (Gemini)',
        keyUrl: 'aistudio.google.com',
        note: 'Gemini models. Aggressively priced flash tier; a solid choice if your stack is already on Google.',
    },
];

/**
 * Which language model answers, configured without a deploy. The key field is
 * write-only: the server sends back only the last four characters, so the page
 * can prove a key exists without ever holding one.
 */
export default function AiSettings({
    driver,
    activeModel,
    providers,
}: {
    driver: string;
    activeModel: string;
    providers: Record<string, ProviderInfo>;
}) {
    const form = useForm({ driver, api_key: '', model: '' });
    const [testing, setTesting] = useState(false);
    const [result, setResult] = useState<TestResult | null>(null);

    const selected = PROVIDERS.find((p) => p.id === form.data.driver) ?? PROVIDERS[0];
    const stored = providers[form.data.driver];

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(route('platform.ai.save'), {
            preserveScroll: true,
            onSuccess: () => form.setData('api_key', ''),
        });
    };

    const runTest = async () => {
        setTesting(true);
        setResult(null);
        try {
            const xsrf = document.cookie
                .split('; ')
                .find((c) => c.startsWith('XSRF-TOKEN='))
                ?.split('=')[1];
            const response = await fetch(route('platform.ai.test'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    ...(xsrf ? { 'X-XSRF-TOKEN': decodeURIComponent(xsrf) } : {}),
                },
                credentials: 'same-origin',
                body: JSON.stringify({ driver: form.data.driver }),
            });
            setResult((await response.json()) as TestResult);
        } catch {
            setResult({ ok: false, error: 'The test request itself failed — check the server is reachable.' });
        } finally {
            setTesting(false);
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="AI Provider" />
            <div className="space-y-6 p-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <Heading title="AI provider" description="Which language model answers, across every AI feature and the chat widget." />
                    <Badge variant={driver === 'fixture' ? 'secondary' : 'default'}>
                        Active: {driver} · {activeModel}
                    </Badge>
                </div>

                {driver === 'fixture' && (
                    <div className="flex items-start gap-3 rounded-lg border border-amber-500/30 bg-amber-500/10 p-4 text-sm">
                        <TriangleAlert className="mt-0.5 size-4 shrink-0 text-amber-600" aria-hidden />
                        <p>
                            The fixture model is active: every AI feature returns placeholder text. Pick a real provider below and save a key to go
                            live — visitors asking the chat widget questions are the first to notice the difference.
                        </p>
                    </div>
                )}

                <form onSubmit={submit}>
                    <Card>
                        <CardContent className="space-y-5 p-5">
                            <div className="grid gap-1 sm:max-w-sm">
                                <Label>Provider</Label>
                                <Select value={form.data.driver} onValueChange={(v) => form.setData('driver', v)}>
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {PROVIDERS.map((p) => (
                                            <SelectItem key={p.id} value={p.id}>
                                                {p.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <p className="text-muted-foreground text-sm">{selected.note}</p>
                                <InputError message={form.errors.driver} />
                            </div>

                            {form.data.driver !== 'fixture' && (
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="grid gap-1">
                                        <Label htmlFor="api_key">API key</Label>
                                        <Input
                                            id="api_key"
                                            type="password"
                                            autoComplete="off"
                                            placeholder={stored?.key_hint ? `Saved (${stored.key_hint}) — enter to replace` : 'Paste the key'}
                                            value={form.data.api_key}
                                            onChange={(e) => form.setData('api_key', e.target.value)}
                                        />
                                        <p className="text-muted-foreground text-xs">
                                            {selected.keyUrl ? `Create one at ${selected.keyUrl}. ` : ''}
                                            Stored encrypted; leaving this blank keeps the saved key.
                                        </p>
                                        <InputError message={form.errors.api_key} />
                                    </div>
                                    <div className="grid gap-1">
                                        <Label htmlFor="model">Model (optional)</Label>
                                        <Input
                                            id="model"
                                            placeholder={stored?.model || 'Provider default'}
                                            value={form.data.model}
                                            onChange={(e) => form.setData('model', e.target.value)}
                                        />
                                        <p className="text-muted-foreground text-xs">Leave blank to keep the sensible default.</p>
                                        <InputError message={form.errors.model} />
                                    </div>
                                </div>
                            )}

                            <div className="flex flex-wrap items-center gap-3">
                                <Button type="submit" disabled={form.processing}>
                                    {form.processing ? 'Saving…' : 'Save settings'}
                                </Button>
                                {form.recentlySuccessful && (
                                    <span className="inline-flex items-center gap-1 text-sm text-emerald-600">
                                        <Check className="size-4" aria-hidden /> Saved
                                    </span>
                                )}
                                <Button type="button" variant="outline" onClick={runTest} disabled={testing}>
                                    {testing ? (
                                        <LoaderCircle className="size-4 animate-spin" aria-hidden />
                                    ) : (
                                        <CirclePlay className="size-4" aria-hidden />
                                    )}
                                    {testing ? 'Testing…' : 'Test this provider'}
                                </Button>
                            </div>

                            {result && (
                                <div
                                    role="status"
                                    className={`rounded-lg border p-4 text-sm ${
                                        result.ok ? 'border-emerald-500/30 bg-emerald-500/10' : 'border-red-500/30 bg-red-500/10'
                                    }`}
                                >
                                    {result.ok ? (
                                        <>
                                            <p className="font-medium">Working.</p>
                                            <p className="text-muted-foreground mt-1">
                                                {result.model} answered in {result.latency_ms}ms
                                                {result.reply ? <> — “{result.reply}”</> : null}
                                            </p>
                                        </>
                                    ) : (
                                        <>
                                            <p className="font-medium">Not working.</p>
                                            <p className="text-muted-foreground mt-1">{result.error}</p>
                                            <p className="text-muted-foreground mt-1 text-xs">
                                                Save the key first — Test uses what is stored, not what is typed in the box.
                                            </p>
                                        </>
                                    )}
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </form>

                <p className="text-muted-foreground max-w-2xl text-sm">
                    Every AI feature — the chat widget’s answers, the sales agent, summaries — goes through one gateway that meters each tenant’s plan
                    credits, records cost per request, and audits every call. Switching provider here changes who answers; it never changes those
                    controls.
                </p>
            </div>
        </AppLayout>
    );
}
