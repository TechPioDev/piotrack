import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, useForm, usePage } from '@inertiajs/react';
import { TriangleAlert } from 'lucide-react';
import { FormEventHandler } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'AI Agent', href: '/ai/agent' }];

type Contact = {
    id: number;
    name: string;
    lifecycle_stage: string | null;
    lead_score: number | null;
};

type Deal = {
    id: number;
    name: string;
    value: number | null;
};

/**
 * The agent flashes back one of three shapes depending on the task, so the panel
 * renders whichever keys are present rather than assuming one of them.
 */
type AiResult = {
    text?: string;
    qualified?: boolean;
    reason?: string;
    raw?: string;
    score?: number;
};

const textareaClass =
    'border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring flex min-h-28 w-full rounded-md border px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-hidden';

function money(cents: number): string {
    return `$${(cents / 100).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

function humanizeTask(task: string): string {
    const words = task.replace(/_/g, ' ');

    return words.charAt(0).toUpperCase() + words.slice(1);
}

function ResultPanel({ result }: { result: AiResult }) {
    const hasScore = typeof result.score === 'number';
    const hasQualified = typeof result.qualified === 'boolean';

    return (
        <Card>
            <CardContent className="space-y-3 p-4">
                <div className="flex flex-wrap items-center gap-2">
                    <h3 className="text-sm font-medium">Agent output</h3>
                    {hasQualified && (
                        <Badge variant={result.qualified ? 'default' : 'secondary'}>{result.qualified ? 'Qualified' : 'Not qualified'}</Badge>
                    )}
                    {hasScore && <Badge variant="outline">Score {result.score}</Badge>}
                </div>

                {result.reason !== undefined && result.reason !== '' && (
                    <div>
                        <p className="text-muted-foreground text-sm">Reason</p>
                        <p className="text-sm whitespace-pre-wrap">{result.reason}</p>
                    </div>
                )}

                {result.text !== undefined && <p className="text-sm whitespace-pre-wrap">{result.text}</p>}

                {result.raw !== undefined && (
                    <details>
                        <summary className="text-muted-foreground cursor-pointer text-sm">Raw completion</summary>
                        <pre className="bg-muted mt-2 overflow-x-auto rounded-lg p-3 text-xs whitespace-pre-wrap">{result.raw}</pre>
                    </details>
                )}

                <p className="text-muted-foreground border-t pt-3 text-sm">
                    This output is advisory. Nothing here has been written to the CRM — proposed changes go to the approval queue first.
                </p>
            </CardContent>
        </Card>
    );
}

function RunTaskForm({ contacts, tasks }: { contacts: Contact[]; tasks: string[] }) {
    const form = useForm<{ contact_id: string; task: string; purpose: string }>({
        contact_id: '',
        task: tasks[0] ?? '',
        purpose: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.transform((data) => ({
            ...data,
            contact_id: data.contact_id === '' ? null : Number(data.contact_id),
            purpose: data.purpose === '' ? null : data.purpose,
        }));
        form.post(route('ai.agent.run'), { preserveScroll: true });
    };

    return (
        <Card>
            <CardContent className="p-4">
                <h3 className="mb-3 text-sm font-medium">Run a task on a contact</h3>
                <form onSubmit={submit} className="space-y-3">
                    <div className="grid gap-1">
                        <Label htmlFor="agent_contact">Contact</Label>
                        <Select value={form.data.contact_id} onValueChange={(v) => form.setData('contact_id', v)}>
                            <SelectTrigger id="agent_contact">
                                <SelectValue placeholder="Pick a contact" />
                            </SelectTrigger>
                            <SelectContent>
                                {contacts.map((contact) => (
                                    <SelectItem key={contact.id} value={String(contact.id)}>
                                        {contact.name}
                                        {contact.lifecycle_stage !== null ? ` — ${contact.lifecycle_stage}` : ''}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={form.errors.contact_id} />
                    </div>
                    <div className="grid gap-1">
                        <Label htmlFor="agent_task">Task</Label>
                        <Select value={form.data.task} onValueChange={(v) => form.setData('task', v)}>
                            <SelectTrigger id="agent_task">
                                <SelectValue placeholder="Pick a task" />
                            </SelectTrigger>
                            <SelectContent>
                                {tasks.map((task) => (
                                    <SelectItem key={task} value={task}>
                                        {humanizeTask(task)}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={form.errors.task} />
                    </div>
                    <div className="grid gap-1">
                        <Label htmlFor="agent_purpose">Purpose (optional)</Label>
                        <Input
                            id="agent_purpose"
                            value={form.data.purpose}
                            placeholder="introduce our managed services"
                            onChange={(e) => form.setData('purpose', e.target.value)}
                        />
                        <p className="text-muted-foreground text-sm">Used when drafting an email; ignored by the other tasks.</p>
                        <InputError message={form.errors.purpose} />
                    </div>
                    <Button type="submit" disabled={form.processing}>
                        Run task
                    </Button>
                </form>
            </CardContent>
        </Card>
    );
}

function ObjectionForm() {
    const form = useForm<{ objection: string }>({ objection: '' });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(route('ai.agent.objection'), { preserveScroll: true });
    };

    return (
        <Card>
            <CardContent className="p-4">
                <h3 className="mb-3 text-sm font-medium">Handle an objection</h3>
                <form onSubmit={submit} className="space-y-3">
                    <div className="grid gap-1">
                        <Label htmlFor="agent_objection">What did the prospect say?</Label>
                        <textarea
                            id="agent_objection"
                            className={textareaClass}
                            value={form.data.objection}
                            placeholder="We already have an MSP and the contract runs another year."
                            onChange={(e) => form.setData('objection', e.target.value)}
                        />
                        <InputError message={form.errors.objection} />
                    </div>
                    <Button type="submit" disabled={form.processing}>
                        Suggest a response
                    </Button>
                </form>
            </CardContent>
        </Card>
    );
}

function CrmUpdateForm({ contacts }: { contacts: Contact[] }) {
    const form = useForm<{ contact_id: string; lifecycle_stage: string; lead_score: string }>({
        contact_id: '',
        lifecycle_stage: '',
        lead_score: '',
    });

    const hasChange = form.data.lifecycle_stage !== '' || form.data.lead_score !== '';

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.transform((data) => {
            const changes: Record<string, string | number> = {};
            if (data.lifecycle_stage !== '') changes.lifecycle_stage = data.lifecycle_stage;
            if (data.lead_score !== '') changes.lead_score = Number(data.lead_score);

            return { contact_id: data.contact_id === '' ? null : Number(data.contact_id), changes };
        });
        form.post(route('ai.agent.crm-update'), {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    };

    return (
        <Card>
            <CardContent className="p-4">
                <h3 className="mb-1 text-sm font-medium">Propose a CRM update</h3>
                <p className="text-muted-foreground mb-3 text-sm">
                    The change is queued for approval, not applied. Confirm it on the approvals page before it touches the record.
                </p>
                <form onSubmit={submit} className="space-y-3">
                    <div className="grid gap-1">
                        <Label htmlFor="crm_contact">Contact</Label>
                        <Select value={form.data.contact_id} onValueChange={(v) => form.setData('contact_id', v)}>
                            <SelectTrigger id="crm_contact">
                                <SelectValue placeholder="Pick a contact" />
                            </SelectTrigger>
                            <SelectContent>
                                {contacts.map((contact) => (
                                    <SelectItem key={contact.id} value={String(contact.id)}>
                                        {contact.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={form.errors.contact_id} />
                    </div>
                    <div className="grid grid-cols-2 gap-3">
                        <div className="grid gap-1">
                            <Label htmlFor="crm_stage">Lifecycle stage</Label>
                            <Input
                                id="crm_stage"
                                value={form.data.lifecycle_stage}
                                placeholder="leave blank to skip"
                                onChange={(e) => form.setData('lifecycle_stage', e.target.value)}
                            />
                        </div>
                        <div className="grid gap-1">
                            <Label htmlFor="crm_score">Lead score</Label>
                            <Input
                                id="crm_score"
                                type="number"
                                value={form.data.lead_score}
                                placeholder="leave blank to skip"
                                onChange={(e) => form.setData('lead_score', e.target.value)}
                            />
                        </div>
                    </div>
                    <Button type="submit" variant="secondary" disabled={form.processing || !hasChange}>
                        Propose change
                    </Button>
                </form>
            </CardContent>
        </Card>
    );
}

export default function AiAgent({ contacts, deals, tasks }: { contacts: Contact[]; deals: Deal[]; tasks: string[] }) {
    const { can } = usePermissions();
    const canUse = can('ai.agent.use');

    const page = usePage<SharedData>();
    const flash = page.props.flash as { ai_result?: AiResult } | undefined;
    const result = flash?.ai_result ?? (page.props.ai_result as AiResult | undefined) ?? null;
    const aiError = page.props.errors.ai;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="AI Agent" />
            <div className="space-y-6 p-4">
                <Heading title="AI sales agent" description="Qualification, research, drafting and objection handling — all advisory output" />

                {aiError && (
                    <Alert variant="destructive">
                        <TriangleAlert className="h-4 w-4" />
                        <AlertTitle>The agent could not run</AlertTitle>
                        <AlertDescription>{aiError}</AlertDescription>
                    </Alert>
                )}

                {result !== null && <ResultPanel result={result} />}

                {canUse ? (
                    <>
                        <div className="grid gap-3 lg:grid-cols-2">
                            <RunTaskForm contacts={contacts} tasks={tasks} />
                            <ObjectionForm />
                        </div>
                        <CrmUpdateForm contacts={contacts} />
                    </>
                ) : (
                    <p className="text-muted-foreground text-sm">
                        You can view agent output but not run the agent. Running it requires the <code>ai.agent.use</code> permission.
                    </p>
                )}

                <div>
                    <h3 className="mb-2 text-sm font-medium">Open deals for context</h3>
                    {deals.length === 0 ? (
                        <p className="text-muted-foreground text-sm">No deals yet. Deals appear here as context once the pipeline has records.</p>
                    ) : (
                        <div className="overflow-x-auto rounded-lg border">
                            <table className="w-full text-left text-sm">
                                <thead className="bg-muted/50 text-muted-foreground">
                                    <tr>
                                        <th className="p-3">Deal</th>
                                        <th className="p-3 text-right">Value</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {deals.map((deal) => (
                                        <tr key={deal.id} className="hover:bg-muted/40">
                                            <td className="p-3 font-medium">{deal.name}</td>
                                            <td className="text-muted-foreground p-3 text-right">{deal.value === null ? '—' : money(deal.value)}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
