import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { router, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

export type Activity = {
    id: number;
    type: string;
    title: string | null;
    body: string | null;
    due_at: string | null;
    completed_at: string | null;
    client_visible: boolean;
    user: string | null;
    created_at: string;
};

const TYPES = ['note', 'task', 'call', 'email', 'meeting'];

export function ActivityTimeline({
    subjectType,
    subjectId,
    activities,
    canManage,
}: {
    subjectType: string;
    subjectId: number;
    activities: Activity[];
    canManage: boolean;
}) {
    const form = useForm({ subject_type: subjectType, subject_id: subjectId, type: 'note', title: '', body: '', due_at: '' });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(route('crm.activities.store'), { preserveScroll: true, onSuccess: () => form.reset('title', 'body', 'due_at') });
    };

    return (
        <div className="space-y-4">
            {canManage && (
                <form onSubmit={submit} className="space-y-2 rounded-lg border p-3">
                    <div className="flex gap-2">
                        <Select value={form.data.type} onValueChange={(v) => form.setData('type', v)}>
                            <SelectTrigger className="w-32">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {TYPES.map((t) => (
                                    <SelectItem key={t} value={t} className="capitalize">
                                        {t}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <Input
                            value={form.data.title}
                            onChange={(e) => form.setData('title', e.target.value)}
                            placeholder="Title (optional)"
                            className="flex-1"
                        />
                        {form.data.type === 'task' && (
                            <Input type="date" value={form.data.due_at} onChange={(e) => form.setData('due_at', e.target.value)} className="w-40" />
                        )}
                    </div>
                    <Input value={form.data.body} onChange={(e) => form.setData('body', e.target.value)} placeholder="Add a note or detail…" />
                    <Button size="sm" disabled={form.processing}>
                        Log activity
                    </Button>
                </form>
            )}

            {activities.length === 0 ? (
                <p className="text-muted-foreground text-sm">No activity yet.</p>
            ) : (
                <ul className="space-y-2">
                    {activities.map((a) => (
                        <li key={a.id} className="rounded-lg border p-3 text-sm">
                            <div className="flex items-start justify-between gap-2">
                                <div>
                                    <span className="bg-muted mr-2 rounded px-1.5 py-0.5 text-xs capitalize">{a.type}</span>
                                    <span className="font-medium">{a.title}</span>
                                    {a.body && <p className="text-muted-foreground mt-0.5">{a.body}</p>}
                                    <p className="text-muted-foreground mt-1 text-xs">
                                        {a.user ?? 'System'} · {new Date(a.created_at).toLocaleString()}
                                        {a.due_at && ` · due ${new Date(a.due_at).toLocaleDateString()}`}
                                        {a.completed_at && ' · ✓ done'}
                                    </p>
                                </div>
                                {canManage && (
                                    <div className="flex shrink-0 gap-1">
                                        {a.type === 'task' && (
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                onClick={() => router.patch(route('crm.activities.complete', a.id), {}, { preserveScroll: true })}
                                            >
                                                {a.completed_at ? 'Reopen' : 'Complete'}
                                            </Button>
                                        )}
                                        {a.type === 'meeting' && (
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                onClick={() => router.patch(route('crm.activities.visibility', a.id), {}, { preserveScroll: true })}
                                            >
                                                {a.client_visible ? 'Portal ✓' : 'Share to portal'}
                                            </Button>
                                        )}
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            className="text-red-600"
                                            onClick={() => router.delete(route('crm.activities.destroy', a.id), { preserveScroll: true })}
                                        >
                                            Delete
                                        </Button>
                                    </div>
                                )}
                            </div>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}
