import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import type { Flow } from '@/lib/flow-tree';
import { useEffect, useState } from 'react';

type TestNode = { id: string; type: string; text: string; options?: { id: string; label: string }[]; input?: string; optional?: boolean };
type TestMessage = { role: string; body: string };

/**
 * POST JSON to the app. The project has no axios; Laravel accepts the
 * XSRF-TOKEN cookie Inertia already maintains as an X-XSRF-TOKEN header.
 */
export async function postJson<T>(url: string, body: unknown): Promise<T> {
    const xsrf = document.cookie
        .split('; ')
        .find((c) => c.startsWith('XSRF-TOKEN='))
        ?.split('=')[1];

    const response = await fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            ...(xsrf ? { 'X-XSRF-TOKEN': decodeURIComponent(xsrf) } : {}),
        },
        credentials: 'same-origin',
        body: JSON.stringify(body),
    });

    const data = await response.json().catch(() => ({}));
    if (!response.ok) {
        throw Object.assign(new Error('request failed'), { status: response.status, data });
    }
    return data as T;
}

/** Runs the draft through the real engine so the tenant tests what visitors get. */
export function TestDialog({
    widgetId,
    flow,
    open,
    onOpenChange,
}: {
    widgetId: number;
    flow: Flow;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const [token, setToken] = useState<string | null>(null);
    const [messages, setMessages] = useState<TestMessage[]>([]);
    const [node, setNode] = useState<TestNode | null>(null);
    const [done, setDone] = useState(false);
    const [value, setValue] = useState('');
    const [error, setError] = useState<string | null>(null);

    const send = async (payload: { token?: string | null; option?: string; value?: string }, echo?: string) => {
        setError(null);
        if (echo !== undefined) setMessages((m) => [...m, { role: 'visitor', body: echo }]);
        try {
            const data = await postJson<{ token?: string; messages?: TestMessage[]; node?: TestNode | null; done?: boolean }>(
                route('chat.flow.test', widgetId),
                {
                    flow,
                    ...payload,
                },
            );
            setToken(data.token ?? payload.token ?? null);
            setMessages((m) => [...m, ...(data.messages ?? [])]);
            setNode(data.node ?? null);
            setDone(Boolean(data.done));
        } catch (e) {
            const data = (e as { data?: { errors?: Record<string, string[]>; message?: string } }).data;
            setError(data?.errors ? Object.values(data.errors)[0][0] : (data?.message ?? 'Something went wrong.'));
        }
    };

    const restart = () => {
        setMessages([]);
        setNode(null);
        setDone(false);
        setToken(null);
        setError(null);
        void send({});
    };

    // Every time it opens, the test starts again from the top of the current draft.
    useEffect(() => {
        if (open) restart();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open]);

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogTitle>Test conversation</DialogTitle>
                <p className="text-muted-foreground -mt-2 text-xs">
                    This runs your unsaved draft through the real chat engine. Nothing is added to your CRM.
                </p>

                <div className="bg-muted/40 max-h-[45vh] min-h-[220px] space-y-2 overflow-y-auto rounded-lg p-3">
                    {messages.map((m, i) => (
                        <div key={i} className={`flex ${m.role === 'visitor' ? 'justify-end' : 'justify-start'}`}>
                            <div
                                className={`max-w-[85%] rounded-2xl px-3 py-2 text-sm ${
                                    m.role === 'visitor'
                                        ? 'bg-brand text-brand-foreground rounded-br-sm'
                                        : 'bg-card border-border rounded-bl-sm border'
                                }`}
                            >
                                {m.body}
                            </div>
                        </div>
                    ))}
                    {done && <p className="text-muted-foreground pt-1 text-center text-xs">Conversation finished.</p>}
                </div>

                {error && <p className="text-sm text-red-600">{error}</p>}

                {!done && (node?.type === 'choice' || node?.type === 'consent') && (
                    <div className="space-y-1.5">
                        {(node.options ?? []).map((o) => (
                            <Button
                                key={o.id}
                                variant="outline"
                                className="w-full justify-start"
                                onClick={() => send({ token, option: o.id }, o.label)}
                            >
                                {o.label}
                            </Button>
                        ))}
                    </div>
                )}

                {!done && node?.type === 'input' && (
                    <form
                        className="flex gap-2"
                        onSubmit={(e) => {
                            e.preventDefault();
                            const v = value.trim();
                            if (!v && !node.optional) return;
                            setValue('');
                            void send({ token, value: v }, v || '—');
                        }}
                    >
                        <Input value={value} onChange={(e) => setValue(e.target.value)} placeholder="Type an answer…" aria-label={node.text} />
                        <Button type="submit">Send</Button>
                        {node.optional && (
                            <Button type="button" variant="ghost" onClick={() => void send({ token, value: '' }, '—')}>
                                Skip this
                            </Button>
                        )}
                    </form>
                )}

                <Button variant="outline" size="sm" onClick={restart}>
                    Start over
                </Button>
            </DialogContent>
        </Dialog>
    );
}
