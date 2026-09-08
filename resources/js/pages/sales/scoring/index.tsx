import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Scoring', href: '/sales/scoring' }];

type Rule = {
    id: number;
    name: string;
    category: string;
    attribute: string;
    operator: string;
    value: string | null;
    points: number;
    is_active: boolean;
};

type ScoredContact = {
    id: number;
    name: string;
    email: string | null;
    lead_score: number;
    temperature: string;
    lifecycle_stage: string | null;
};

type Category = 'demographic' | 'firmographic' | 'behavioral' | 'intent';
type Attribute = 'lifecycle_stage' | 'lead_source' | 'title' | 'email_opt_in' | 'has_company' | 'intent_score';
type Operator = 'equals' | 'contains' | 'gte' | 'is_true';

const CATEGORIES: Category[] = ['demographic', 'firmographic', 'behavioral', 'intent'];
const ATTRIBUTES: Attribute[] = ['lifecycle_stage', 'lead_source', 'title', 'email_opt_in', 'has_company', 'intent_score'];
const OPERATORS: Operator[] = ['equals', 'contains', 'gte', 'is_true'];

function temperatureVariant(temperature: string): 'default' | 'secondary' | 'destructive' {
    if (temperature === 'hot') return 'destructive';
    if (temperature === 'warm') return 'default';
    return 'secondary';
}

function NewRuleDialog() {
    const [open, setOpen] = useState(false);
    const form = useForm<{ name: string; category: Category; attribute: Attribute; operator: Operator; value: string; points: string }>({
        name: '',
        category: 'demographic',
        attribute: 'lifecycle_stage',
        operator: 'equals',
        value: '',
        points: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.transform((data) => ({ ...data, points: Number(data.points) }));
        form.post(route('sales.scoring.store'), {
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
                <Button>New rule</Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>New scoring rule</DialogTitle>
                <form onSubmit={submit} className="space-y-3">
                    <div className="grid gap-1">
                        <Label htmlFor="rule_name">Name</Label>
                        <Input id="rule_name" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                        <InputError message={form.errors.name} />
                    </div>
                    <div className="grid grid-cols-2 gap-3">
                        <div className="grid gap-1">
                            <Label htmlFor="rule_category">Category</Label>
                            <Select value={form.data.category} onValueChange={(v) => form.setData('category', v as Category)}>
                                <SelectTrigger id="rule_category">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {CATEGORIES.map((category) => (
                                        <SelectItem key={category} value={category}>
                                            {category}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={form.errors.category} />
                        </div>
                        <div className="grid gap-1">
                            <Label htmlFor="rule_attribute">Attribute</Label>
                            <Select value={form.data.attribute} onValueChange={(v) => form.setData('attribute', v as Attribute)}>
                                <SelectTrigger id="rule_attribute">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {ATTRIBUTES.map((attribute) => (
                                        <SelectItem key={attribute} value={attribute}>
                                            {attribute}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={form.errors.attribute} />
                        </div>
                    </div>
                    <div className="grid grid-cols-2 gap-3">
                        <div className="grid gap-1">
                            <Label htmlFor="rule_operator">Operator</Label>
                            <Select value={form.data.operator} onValueChange={(v) => form.setData('operator', v as Operator)}>
                                <SelectTrigger id="rule_operator">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {OPERATORS.map((operator) => (
                                        <SelectItem key={operator} value={operator}>
                                            {operator}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={form.errors.operator} />
                        </div>
                        <div className="grid gap-1">
                            <Label htmlFor="rule_points">Points</Label>
                            <Input id="rule_points" type="number" value={form.data.points} onChange={(e) => form.setData('points', e.target.value)} />
                            <InputError message={form.errors.points} />
                        </div>
                    </div>
                    <div className="grid gap-1">
                        <Label htmlFor="rule_value">Value</Label>
                        <Input id="rule_value" value={form.data.value} onChange={(e) => form.setData('value', e.target.value)} />
                        <InputError message={form.errors.value} />
                    </div>
                    <DialogFooter>
                        <Button type="submit" disabled={form.processing}>
                            Create
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

type AssignmentRule = { id: number; position: number; field: string; value: string; user: string | null };

function NewRoutingRuleDialog({ fields, members }: { fields: string[]; members: { id: number; name: string }[] }) {
    const [open, setOpen] = useState(false);
    const form = useForm({ field: fields[0] ?? '', value: '', user_id: '' });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(route('sales.scoring.assignment.store'), {
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
                <Button variant="outline">New routing rule</Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>New routing rule</DialogTitle>
                <p className="text-muted-foreground text-sm">
                    New leads matching the rule are assigned to the chosen owner. Rules run in order, first match wins; anything unmatched falls back
                    to least-loaded round-robin.
                </p>
                <form onSubmit={submit} className="space-y-3">
                    <div className="grid grid-cols-2 gap-3">
                        <div className="grid gap-1">
                            <Label htmlFor="ar-field">Field</Label>
                            <Select value={form.data.field} onValueChange={(v) => form.setData('field', v)}>
                                <SelectTrigger id="ar-field">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {fields.map((field) => (
                                        <SelectItem key={field} value={field}>
                                            {field.replace(/_/g, ' ')}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="grid gap-1">
                            <Label htmlFor="ar-value">Value</Label>
                            <Input
                                id="ar-value"
                                value={form.data.value}
                                onChange={(e) => form.setData('value', e.target.value)}
                                placeholder="e.g. referral or acmecorp.com"
                            />
                            <InputError message={form.errors.value} />
                        </div>
                    </div>
                    <div className="grid gap-1">
                        <Label htmlFor="ar-user">Assign to</Label>
                        <Select value={form.data.user_id} onValueChange={(v) => form.setData('user_id', v)}>
                            <SelectTrigger id="ar-user">
                                <SelectValue placeholder="Pick a member" />
                            </SelectTrigger>
                            <SelectContent>
                                {members.map((member) => (
                                    <SelectItem key={member.id} value={String(member.id)}>
                                        {member.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={form.errors.user_id} />
                    </div>
                    <DialogFooter>
                        <Button type="submit" disabled={form.processing}>
                            Add rule
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export default function Scoring({
    rules,
    contacts,
    assignment_rules,
    assignment_fields,
    members,
}: {
    rules: Rule[];
    contacts: ScoredContact[];
    assignment_rules: AssignmentRule[];
    assignment_fields: string[];
    members: { id: number; name: string }[];
}) {
    const { can } = usePermissions();
    const canManage = can('sales.scoring.manage');

    const removeRule = (id: number) => router.delete(route('sales.scoring.destroy', id), { preserveScroll: true });
    const recompute = () => router.post(route('sales.scoring.recompute'), {}, { preserveScroll: true });

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Scoring" />
            <div className="space-y-6 p-4">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <Heading title="Lead scoring" description="Rules that score contacts and set lead temperature" />
                    {canManage && (
                        <div className="flex flex-wrap gap-2">
                            <Button variant="secondary" onClick={recompute}>
                                Recompute scores
                            </Button>
                            <NewRoutingRuleDialog fields={assignment_fields} members={members} />
                            <NewRuleDialog />
                        </div>
                    )}
                </div>

                <div>
                    <h3 className="mb-2 text-sm font-medium">Scoring rules</h3>
                    {rules.length === 0 ? (
                        <p className="text-muted-foreground text-sm">No rules yet. Create a rule to start scoring contacts.</p>
                    ) : (
                        <div className="overflow-x-auto rounded-lg border">
                            <table className="w-full text-left text-sm">
                                <thead className="bg-muted/50 text-muted-foreground">
                                    <tr>
                                        <th className="p-3 font-medium">Name</th>
                                        <th className="p-3 font-medium">Category</th>
                                        <th className="p-3 font-medium">Condition</th>
                                        <th className="p-3 text-center font-medium">Points</th>
                                        <th className="p-3 text-center font-medium">Active</th>
                                        {canManage && <th className="p-3 text-right font-medium">Actions</th>}
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {rules.map((rule) => (
                                        <tr key={rule.id} className="hover:bg-muted/40">
                                            <td className="p-3 font-medium">{rule.name}</td>
                                            <td className="p-3">
                                                <Badge variant="outline">{rule.category}</Badge>
                                            </td>
                                            <td className="text-muted-foreground p-3">
                                                {rule.attribute} {rule.operator} {rule.value ?? ''}
                                            </td>
                                            <td className="p-3 text-center">{rule.points}</td>
                                            <td className="p-3 text-center">
                                                {rule.is_active ? '✓' : <span className="text-muted-foreground">—</span>}
                                            </td>
                                            {canManage && (
                                                <td className="p-3">
                                                    <div className="flex justify-end">
                                                        <Button
                                                            size="sm"
                                                            variant="ghost"
                                                            className="text-destructive"
                                                            onClick={() => removeRule(rule.id)}
                                                        >
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
                    )}
                </div>

                <div>
                    <h3 className="mb-2 text-sm font-medium">Scored contacts</h3>
                    {contacts.length === 0 ? (
                        <p className="text-muted-foreground text-sm">No contacts yet. Contacts appear here once they are scored.</p>
                    ) : (
                        <div className="overflow-x-auto rounded-lg border">
                            <table className="w-full text-left text-sm">
                                <thead className="bg-muted/50 text-muted-foreground">
                                    <tr>
                                        <th className="p-3 font-medium">Name</th>
                                        <th className="p-3 font-medium">Email</th>
                                        <th className="p-3 text-center font-medium">Score</th>
                                        <th className="p-3 font-medium">Temperature</th>
                                        <th className="p-3 font-medium">Lifecycle stage</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {contacts.map((contact) => (
                                        <tr key={contact.id} className="hover:bg-muted/40">
                                            <td className="p-3 font-medium">{contact.name}</td>
                                            <td className="text-muted-foreground p-3">{contact.email ?? '—'}</td>
                                            <td className="p-3 text-center">{contact.lead_score}</td>
                                            <td className="p-3">
                                                <Badge variant={temperatureVariant(contact.temperature)}>{contact.temperature}</Badge>
                                            </td>
                                            <td className="text-muted-foreground p-3">{contact.lifecycle_stage ?? '—'}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>

                {/* CRM-025: routing rules — first match wins, round-robin fallback */}
                <div>
                    <h2 className="mb-2 text-lg font-semibold">Lead routing</h2>
                    <p className="text-muted-foreground mb-2 text-sm">
                        New unowned leads are matched against these rules in order; anything unmatched goes round-robin to the least-loaded active
                        member.
                    </p>
                    <div className="overflow-x-auto rounded-lg border">
                        <table className="w-full text-left text-sm">
                            <thead className="bg-muted/50 text-muted-foreground">
                                <tr>
                                    <th className="p-3 font-medium">#</th>
                                    <th className="p-3 font-medium">When</th>
                                    <th className="p-3 font-medium">Assign to</th>
                                    <th className="p-3 font-medium"></th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {assignment_rules.map((rule, i) => (
                                    <tr key={rule.id} className="hover:bg-muted/40">
                                        <td className="p-3 tabular-nums">{i + 1}</td>
                                        <td className="p-3">
                                            {rule.field.replace(/_/g, ' ')} = <span className="font-medium">{rule.value}</span>
                                        </td>
                                        <td className="p-3">{rule.user ?? '—'}</td>
                                        <td className="p-3 text-right">
                                            {canManage && (
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() =>
                                                        router.delete(route('sales.scoring.assignment.destroy', rule.id), { preserveScroll: true })
                                                    }
                                                >
                                                    Remove
                                                </Button>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                                {assignment_rules.length === 0 && (
                                    <tr>
                                        <td colSpan={4} className="text-muted-foreground p-3 text-sm">
                                            No rules — every new lead goes round-robin to the least-loaded member.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
