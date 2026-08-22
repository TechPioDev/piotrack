/**
 * The embeddable Piotrack chat widget.
 *
 * Runs on the CUSTOMER's website, so it is deliberately self-contained: no React,
 * no shared app code, no Ziggy — just a small vanilla bundle that renders inside a
 * Shadow DOM. That isolation is the point: the host page's CSS cannot break the
 * widget, and the widget's CSS cannot leak into their site.
 *
 * The server owns the conversation. This client only renders the node it is given
 * and posts the visitor's reply back; it never decides what comes next, and never
 * sees scores or branching logic.
 *
 * Install:  <script src=".../widget/piotrack-chat.js" data-widget="wc_xxx" async></script>
 */

type Option = { id: string; label: string };
type ChatNode = {
    id: string;
    type: 'choice' | 'input' | 'consent';
    live?: boolean;
    text: string;
    options?: Option[];
    input?: string;
    optional?: boolean;
    privacy_url?: string | null;
};
type Message = { id?: number; role: string; body: string };
type Reply = { messages?: Message[]; node?: ChatNode | null; done?: boolean; booking_url?: string; live?: boolean; agent?: string | null };
type Config = {
    name: string;
    theme: { title: string; accent: string; position: 'bottom-left' | 'bottom-right'; company: string };
    teaser: string | null;
    consent_required: boolean;
    privacy_url: string | null;
};

const script = document.currentScript as HTMLScriptElement | null;
const widgetKey = script?.dataset.widget ?? '';
// The API lives wherever this script was served from.
const origin = script ? new URL(script.src, location.href).origin : location.origin;

const VISITOR_KEY = 'piotrack_chat_visitor';
const STATE_KEY = 'piotrack_chat_state';

function visitorId(): string {
    try {
        let id = localStorage.getItem(VISITOR_KEY);
        if (!id) {
            id = 'v_' + Math.random().toString(36).slice(2) + Date.now().toString(36);
            localStorage.setItem(VISITOR_KEY, id);
        }
        return id;
    } catch {
        return 'v_anon';
    }
}

async function api<T>(path: string, body?: unknown): Promise<T> {
    const response = await fetch(`${origin}/wc/${widgetKey}/${path}`, {
        method: body === undefined ? 'GET' : 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: body === undefined ? undefined : JSON.stringify(body),
    });
    if (!response.ok) {
        throw Object.assign(new Error('request failed'), { status: response.status, payload: await response.json().catch(() => ({})) });
    }
    return response.json() as Promise<T>;
}

const css = (accent: string) => `
:host { all: initial; }
*, *::before, *::after { box-sizing: border-box; }
.root {
    position: fixed; z-index: 2147483000; bottom: 20px;
    font-family: system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
    color: #0b1a23; line-height: 1.5;
}
.root.left { left: 20px; } .root.right { right: 20px; }
button { font: inherit; cursor: pointer; }

/* Launcher */
.launcher {
    display: flex; align-items: center; justify-content: center;
    width: 60px; height: 60px; border-radius: 50%; border: 0;
    background: ${accent}; color: #fff;
    box-shadow: 0 8px 24px -6px rgba(11,26,35,.4);
    transition: transform .18s ease, box-shadow .18s ease;
}
.launcher:hover { transform: translateY(-2px); box-shadow: 0 12px 28px -6px rgba(11,26,35,.45); }
.launcher:focus-visible { outline: 3px solid ${accent}; outline-offset: 3px; }
.launcher svg { width: 26px; height: 26px; }

/* Teaser */
.teaser {
    position: absolute; bottom: 74px; width: max-content; max-width: 260px;
    background: #fff; border: 1px solid #dbe8e8; border-radius: 14px;
    padding: 12px 36px 12px 14px; font-size: 14px; font-weight: 500;
    box-shadow: 0 12px 30px -12px rgba(11,26,35,.35);
    animation: pop .25s ease;
}
.root.right .teaser { right: 0; } .root.left .teaser { left: 0; }
.teaser-close {
    position: absolute; top: 6px; right: 6px; width: 22px; height: 22px;
    border: 0; background: transparent; color: #6b8792; border-radius: 6px; line-height: 1;
}
.teaser-close:hover { background: #eef4f4; }

/* Panel */
.panel {
    position: absolute; bottom: 74px; width: 372px; max-height: min(620px, calc(100vh - 120px));
    display: flex; flex-direction: column; overflow: hidden;
    background: #fff; border: 1px solid #dbe8e8; border-radius: 18px;
    box-shadow: 0 24px 60px -20px rgba(11,26,35,.45);
    animation: pop .22s ease;
}
.root.right .panel { right: 0; } .root.left .panel { left: 0; }
@keyframes pop { from { opacity: 0; transform: translateY(10px) scale(.98); } to { opacity: 1; transform: none; } }
@media (prefers-reduced-motion: reduce) { .panel, .teaser { animation: none; } .launcher { transition: none; } }

.head { display: flex; align-items: center; gap: 10px; padding: 14px 16px; background: ${accent}; color: #fff; }
.avatar {
    width: 34px; height: 34px; border-radius: 50%; flex: 0 0 auto;
    background: rgba(255,255,255,.22); display: flex; align-items: center; justify-content: center;
    font-weight: 700; font-size: 13px;
}
.head-text { min-width: 0; }
.head-title { font-weight: 700; font-size: 15px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.head-sub { font-size: 12px; opacity: .85; }
.head-close { margin-left: auto; width: 30px; height: 30px; border: 0; border-radius: 8px; background: transparent; color: #fff; font-size: 18px; line-height: 1; }
.head-close:hover { background: rgba(255,255,255,.18); }
.head-close:focus-visible { outline: 2px solid #fff; outline-offset: 2px; }

.log { flex: 1 1 auto; overflow-y: auto; padding: 16px; display: flex; flex-direction: column; gap: 10px; background: #f7fbfb; }
.msg { max-width: 85%; padding: 10px 13px; border-radius: 14px; font-size: 14px; white-space: pre-wrap; overflow-wrap: anywhere; }
.msg.bot { background: #fff; border: 1px solid #e6eeee; border-bottom-left-radius: 4px; align-self: flex-start; }
.msg.visitor { background: ${accent}; color: #fff; border-bottom-right-radius: 4px; align-self: flex-end; }
.typing { align-self: flex-start; display: flex; gap: 4px; padding: 12px 14px; background: #fff; border: 1px solid #e6eeee; border-radius: 14px; }
.typing i { width: 6px; height: 6px; border-radius: 50%; background: #9db3bb; animation: blink 1.2s infinite; }
.typing i:nth-child(2) { animation-delay: .2s; } .typing i:nth-child(3) { animation-delay: .4s; }
@keyframes blink { 0%,60%,100% { opacity: .3; } 30% { opacity: 1; } }

.foot { border-top: 1px solid #e6eeee; padding: 12px; background: #fff; display: flex; flex-direction: column; gap: 8px; }
.choice {
    text-align: left; width: 100%; padding: 11px 13px; font-size: 14px; font-weight: 600;
    background: #fff; color: ${accent}; border: 1.5px solid #d6e6e4; border-radius: 11px;
    transition: background .15s ease, border-color .15s ease;
}
.choice:hover { background: #f0faf8; border-color: ${accent}; }
.choice:focus-visible { outline: 2px solid ${accent}; outline-offset: 2px; }
.row { display: flex; gap: 8px; }
.row input {
    flex: 1 1 auto; min-width: 0; padding: 11px 13px; font-size: 14px;
    border: 1.5px solid #d6e6e4; border-radius: 11px; background: #fff; color: inherit;
}
.row input:focus { outline: 2px solid ${accent}; outline-offset: -1px; border-color: ${accent}; }
.send { flex: 0 0 auto; padding: 0 16px; border: 0; border-radius: 11px; background: ${accent}; color: #fff; font-weight: 700; }
.send:disabled { opacity: .55; cursor: not-allowed; }
.skip { border: 0; background: transparent; color: #6b8792; font-size: 13px; text-decoration: underline; padding: 2px; align-self: flex-start; }
.err { color: #c02a1b; font-size: 13px; }
.consent { font-size: 12.5px; color: #5b7480; }
.consent a { color: ${accent}; }
.brand { text-align: center; font-size: 11px; color: #8aa2ab; padding: 2px 0 0; }
.cta { display: block; text-align: center; padding: 11px; border-radius: 11px; background: ${accent}; color: #fff; font-weight: 700; font-size: 14px; text-decoration: none; }

@media (max-width: 480px) {
    .root { bottom: 16px; }
    .root.left { left: 16px; } .root.right { right: 16px; }
    .panel {
        position: fixed; inset: 0; width: 100vw; max-height: 100vh; height: 100dvh;
        border-radius: 0; border: 0;
    }
}
`;

class ChatWidget {
    private root!: ShadowRoot;
    private el!: HTMLDivElement;
    private log!: HTMLDivElement;
    private foot!: HTMLDivElement;
    private panel: HTMLDivElement | null = null;
    private token: string | null = null;
    private open = false;
    private busy = false;
    private live = false;
    private lastSeenId = 0;
    private pollTimer: number | null = null;

    constructor(private config: Config) {}

    mount() {
        const host = document.createElement('div');
        host.setAttribute('data-piotrack-chat', '');
        document.body.appendChild(host);
        this.root = host.attachShadow({ mode: 'open' });

        const style = document.createElement('style');
        style.textContent = css(this.config.theme.accent);
        this.root.appendChild(style);

        this.el = document.createElement('div');
        this.el.className = `root ${this.config.theme.position === 'bottom-left' ? 'left' : 'right'}`;
        this.root.appendChild(this.el);

        this.renderLauncher();
        this.track('impression');
        this.maybeTeaser();
    }

    private renderLauncher() {
        const button = document.createElement('button');
        button.className = 'launcher';
        button.setAttribute('aria-label', `Open chat with ${this.config.theme.company}`);
        button.innerHTML = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.4 8.4 0 0 1-9 8.4 8.9 8.9 0 0 1-3.8-.8L3 21l1.9-5A8.4 8.4 0 0 1 12 3.1a8.4 8.4 0 0 1 9 8.4z"/></svg>`;
        button.addEventListener('click', () => this.toggle());
        this.el.appendChild(button);
    }

    private maybeTeaser() {
        if (!this.config.teaser) return;
        try {
            if (sessionStorage.getItem(STATE_KEY) === 'seen') return;
        } catch {
            /* storage blocked: show it anyway */
        }

        setTimeout(() => {
            if (this.open) return;
            const teaser = document.createElement('div');
            teaser.className = 'teaser';
            teaser.setAttribute('role', 'status');
            const text = document.createElement('span');
            text.textContent = this.config.teaser as string;
            const close = document.createElement('button');
            close.className = 'teaser-close';
            close.setAttribute('aria-label', 'Dismiss message');
            close.textContent = '×';
            close.addEventListener('click', (e) => {
                e.stopPropagation();
                teaser.remove();
            });
            teaser.append(text, close);
            teaser.addEventListener('click', () => {
                teaser.remove();
                this.toggle();
            });
            this.el.appendChild(teaser);
            try {
                sessionStorage.setItem(STATE_KEY, 'seen');
            } catch {
                /* ignore */
            }
        }, 4000);
    }

    private toggle() {
        if (this.open) {
            this.close();
        } else {
            void this.openPanel();
        }
    }

    private close() {
        this.open = false;
        this.stopPolling();
        this.panel?.remove();
        this.panel = null;
        (this.el.querySelector('.launcher') as HTMLElement)?.focus();
    }

    private async openPanel() {
        this.open = true;
        this.el.querySelector('.teaser')?.remove();

        const panel = document.createElement('div');
        panel.className = 'panel';
        panel.setAttribute('role', 'dialog');
        panel.setAttribute('aria-modal', 'false');
        panel.setAttribute('aria-label', `Chat with ${this.config.theme.company}`);

        const initials = this.config.theme.company.trim().slice(0, 2).toUpperCase();
        panel.innerHTML = `
            <div class="head">
                <div class="avatar" aria-hidden="true">${escape(initials)}</div>
                <div class="head-text">
                    <div class="head-title">${escape(this.config.theme.title)}</div>
                    <div class="head-sub">Typically replies in a few minutes</div>
                </div>
                <button class="head-close" aria-label="Close chat">×</button>
            </div>
            <div class="log" role="log" aria-live="polite" aria-atomic="false"></div>
            <div class="foot"></div>
        `;
        this.el.appendChild(panel);
        this.panel = panel;
        this.log = panel.querySelector('.log') as HTMLDivElement;
        this.foot = panel.querySelector('.foot') as HTMLDivElement;

        panel.querySelector('.head-close')?.addEventListener('click', () => this.close());
        panel.addEventListener('keydown', (e) => {
            if ((e as KeyboardEvent).key === 'Escape') this.close();
        });

        this.track('open');

        if (this.token === null) {
            await this.begin();
        } else {
            // Reopening an existing chat must not show an empty window: replay
            // what was already said, then resume polling if it is still live.
            await this.restore();
        }
    }

    /** Re-render the transcript of a conversation the visitor already started. */
    private async restore() {
        this.typing(true);
        try {
            const data = await api<{ messages?: { id: number; role: string; body: string }[]; live?: boolean; closed?: boolean }>(
                `conversations/${this.token}/poll?since=0`,
            );
            this.typing(false);
            (data.messages ?? []).forEach((m) => {
                this.lastSeenId = Math.max(this.lastSeenId, m.id);
                this.bubble('bot', m.body);
            });
            if (data.live && !data.closed) {
                this.live = true;
                this.startPolling();
                this.renderLiveComposer();
            } else if (data.closed) {
                this.brand();
            } else {
                this.renderLiveComposer();
            }
        } catch {
            this.fail();
        }
    }

    /** A plain free-text composer, used when a human is handling the chat. */
    private renderLiveComposer() {
        this.foot.innerHTML = '';
        const row = document.createElement('div');
        row.className = 'row';
        const input = document.createElement('input');
        input.type = 'text';
        input.setAttribute('aria-label', 'Type a message');
        input.placeholder = 'Type your message…';
        const send = document.createElement('button');
        send.className = 'send';
        send.textContent = 'Send';
        const submit = () => {
            const value = input.value.trim();
            if (!value) return;
            input.value = '';
            void this.send({ value }, value);
        };
        send.addEventListener('click', submit);
        input.addEventListener('keydown', (e) => {
            if ((e as KeyboardEvent).key === 'Enter') submit();
        });
        row.append(input, send);
        this.foot.appendChild(row);
        this.brand();
    }

    private async begin() {
        this.typing(true);
        try {
            const params = new URLSearchParams(location.search);
            const utm: Record<string, string> = {};
            ['source', 'medium', 'campaign', 'term', 'content'].forEach((k) => {
                const v = params.get(`utm_${k}`);
                if (v) utm[k] = v;
            });

            const reply = await api<Reply & { token: string }>('conversations', {
                visitor: visitorId(),
                page: location.href.slice(0, 500),
                referrer: document.referrer.slice(0, 500),
                utm,
            });
            this.token = reply.token;
            this.render(reply);
        } catch {
            this.fail();
        }
    }

    private async send(payload: { option?: string; value?: string }, echo: string) {
        if (this.busy || !this.token) return;
        this.busy = true;
        this.bubble('visitor', echo);
        this.foot.innerHTML = '';
        this.typing(true);

        try {
            const reply = await api<Reply>(`conversations/${this.token}/messages`, payload);
            this.render(reply);
        } catch (error) {
            const status = (error as { status?: number }).status;
            const payloadErr = (error as { payload?: { errors?: Record<string, string[]> } }).payload;
            this.typing(false);
            if (status === 422 && payloadErr?.errors) {
                const message = Object.values(payloadErr.errors)[0]?.[0] ?? 'Please check that answer.';
                this.error(message);
            } else {
                this.fail();
            }
        } finally {
            this.busy = false;
        }
    }

    /**
     * Live chat arrives by polling: this stack has no websocket server, and the
     * product runs on isolated networks where one could not be reached anyway.
     * Polling stops as soon as the conversation closes or the widget is shut.
     */
    private startPolling() {
        if (this.pollTimer !== null || !this.token) return;
        this.pollTimer = window.setInterval(() => void this.poll(), 4000);
    }

    private stopPolling() {
        if (this.pollTimer !== null) {
            window.clearInterval(this.pollTimer);
            this.pollTimer = null;
        }
    }

    private async poll() {
        if (!this.token || !this.open) return;
        try {
            const data = await api<{ messages?: { id: number; role: string; body: string }[]; live?: boolean; closed?: boolean }>(
                `conversations/${this.token}/poll?since=${this.lastSeenId}`,
            );
            (data.messages ?? []).forEach((m) => {
                this.lastSeenId = Math.max(this.lastSeenId, m.id);
                this.bubble('bot', m.body);
            });
            if (data.closed) this.stopPolling();
        } catch {
            /* a blip must not break the visitor's chat */
        }
    }

    private render(reply: Reply) {
        this.typing(false);
        (reply.messages ?? []).forEach((m) => {
            if (typeof m.id === 'number') this.lastSeenId = Math.max(this.lastSeenId, m.id);
            this.bubble('bot', m.body);
        });
        this.foot.innerHTML = '';

        if (reply.booking_url) {
            const link = document.createElement('a');
            link.className = 'cta';
            link.href = reply.booking_url;
            link.target = '_blank';
            link.rel = 'noopener noreferrer';
            link.textContent = 'Choose a time';
            this.foot.appendChild(link);
        }

        if (reply.live) {
            this.live = true;
            this.startPolling();
            const sub = this.panel?.querySelector('.head-sub');
            if (sub) sub.textContent = reply.agent ? `${reply.agent} is here to help` : 'Connected to our team';
        }

        const node = reply.node;
        if (!node) {
            this.stopPolling();
            this.brand();
            return;
        }

        if (node.type === 'choice' || node.type === 'consent') {
            if (node.type === 'consent') {
                const note = document.createElement('div');
                note.className = 'consent';
                if (node.privacy_url) {
                    const a = document.createElement('a');
                    a.href = node.privacy_url;
                    a.target = '_blank';
                    a.rel = 'noopener noreferrer';
                    a.textContent = 'Privacy Policy';
                    note.append('See our ', a, '.');
                } else {
                    note.textContent = 'Your details are only used to respond to this enquiry.';
                }
                this.foot.appendChild(note);
            }
            (node.options ?? []).forEach((option) => {
                const button = document.createElement('button');
                button.className = 'choice';
                button.textContent = option.label;
                button.addEventListener('click', () => this.send({ option: option.id }, option.label));
                this.foot.appendChild(button);
            });
            (this.foot.querySelector('.choice') as HTMLElement)?.focus();
        }

        if (node.type === 'input') {
            const row = document.createElement('div');
            row.className = 'row';
            const input = document.createElement('input');
            input.type = node.input === 'email' ? 'email' : node.input === 'phone' ? 'tel' : node.input === 'number' ? 'number' : 'text';
            input.setAttribute('aria-label', node.text);
            input.placeholder = 'Type your answer…';
            const send = document.createElement('button');
            send.className = 'send';
            send.textContent = 'Send';
            const submit = () => {
                const value = input.value.trim();
                if (!value && !node.optional) return;
                this.send({ value }, value || '—');
            };
            send.addEventListener('click', submit);
            input.addEventListener('keydown', (e) => {
                if ((e as KeyboardEvent).key === 'Enter') submit();
            });
            row.append(input, send);
            this.foot.appendChild(row);

            if (node.optional) {
                const skip = document.createElement('button');
                skip.className = 'skip';
                skip.textContent = 'Skip this';
                skip.addEventListener('click', () => this.send({ value: '' }, '—'));
                this.foot.appendChild(skip);
            }
            input.focus();
        }

        this.brand();
    }

    private bubble(role: 'bot' | 'visitor', body: string) {
        const div = document.createElement('div');
        div.className = `msg ${role}`;
        div.textContent = body;
        this.log.appendChild(div);
        this.log.scrollTop = this.log.scrollHeight;
    }

    private typing(on: boolean) {
        this.log.querySelector('.typing')?.remove();
        if (!on) return;
        const dots = document.createElement('div');
        dots.className = 'typing';
        dots.setAttribute('aria-label', 'Typing');
        dots.innerHTML = '<i></i><i></i><i></i>';
        this.log.appendChild(dots);
        this.log.scrollTop = this.log.scrollHeight;
    }

    private error(message: string) {
        const div = document.createElement('div');
        div.className = 'err';
        div.setAttribute('role', 'alert');
        div.textContent = message;
        this.foot.prepend(div);
    }

    private brand() {
        if (this.foot.querySelector('.brand')) return;
        const div = document.createElement('div');
        div.className = 'brand';
        div.textContent = 'Powered by Piotrack';
        this.foot.appendChild(div);
    }

    /** Never break the host site: show a calm fallback instead of an error. */
    private fail() {
        this.typing(false);
        this.bubble('bot', 'Sorry — our chat is temporarily unavailable. Please use the contact details on this page and we will get back to you.');
        this.foot.innerHTML = '';
        this.brand();
    }

    private track(type: string) {
        void api('events', { type }).catch(() => undefined);
    }
}

function escape(value: string): string {
    return value.replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c] as string);
}

async function boot() {
    if (!widgetKey) return;
    // A missing/paused widget, a blocked origin or an outage must all fail
    // silently — the customer's website keeps working exactly as before.
    try {
        const config = await api<Config>('config');
        new ChatWidget(config).mount();
    } catch {
        /* stay invisible */
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
} else {
    void boot();
}

export {};
