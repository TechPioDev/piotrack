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

import { compressImage } from './images';
import { fetchWithRetry } from './retry';
import { pageMatches } from './targeting';

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
    suggestions?: string[];
};
type Attachment = { name: string; image: boolean; url: string };
type Message = { id?: number; role: string; body: string; attachment?: Attachment | null; delay?: number };
type Reply = { messages?: Message[]; node?: ChatNode | null; done?: boolean; booking_url?: string; live?: boolean; agent?: string | null };
type Targeting = {
    include: string[];
    exclude: string[];
    devices: string[];
    visitor: string;
    delay_seconds: number;
    scroll_percent: number;
    exit_intent: boolean;
};
type Config = {
    name: string;
    theme: { title: string; accent: string; position: 'bottom-left' | 'bottom-right'; company: string; logo_url?: string | null };
    teaser: string | null;
    teaser_delay?: number;
    consent_required: boolean;
    privacy_url: string | null;
    fallback_contact?: string | null;
    // Absent from an older server: files off, branding on, rating asked.
    attachments?: boolean;
    branding?: boolean;
    rating?: boolean;
    targeting?: Targeting;
};

const SEEN_KEY = 'piotrack_chat_seen';

/**
 * Page + behaviour display rules (§34, §35). These decide only WHEN the launcher
 * appears — never what the visitor may do — so evaluating them in the browser is
 * appropriate. A rule left empty always passes, so an unconfigured widget shows
 * everywhere, which is what a tenant expects.
 */
function targetingAllows(t: Targeting | undefined): boolean {
    if (!t) return true;

    if (t.include.length > 0 && !pageMatches(t.include, location.pathname, location.search)) return false;
    if (t.exclude.length > 0 && pageMatches(t.exclude, location.pathname, location.search)) return false;

    if (t.devices.length > 0) {
        const isMobile = window.matchMedia('(max-width: 767px)').matches;
        if (!t.devices.includes(isMobile ? 'mobile' : 'desktop')) return false;
    }

    if (t.visitor === 'first' || t.visitor === 'returning') {
        let seen = false;
        try {
            seen = localStorage.getItem(SEEN_KEY) === '1';
            localStorage.setItem(SEEN_KEY, '1');
        } catch {
            /* storage blocked: treat as a first visit */
        }
        if (t.visitor === 'first' && seen) return false;
        if (t.visitor === 'returning' && !seen) return false;
    }

    return true;
}

/** Wait for the configured trigger (delay, scroll depth, or exit intent). */
function waitForTrigger(t: Targeting | undefined): Promise<void> {
    if (!t) return Promise.resolve();
    const needsScroll = t.scroll_percent > 0;
    const needsExit = t.exit_intent;
    const delay = Math.max(0, t.delay_seconds) * 1000;

    if (!needsScroll && !needsExit) {
        return delay === 0 ? Promise.resolve() : new Promise((r) => setTimeout(r, delay));
    }

    return new Promise((resolve) => {
        let done = false;
        const finish = () => {
            if (done) return;
            done = true;
            window.removeEventListener('scroll', onScroll);
            document.removeEventListener('mouseout', onExit);
            resolve();
        };
        const onScroll = () => {
            const max = document.documentElement.scrollHeight - window.innerHeight;
            const pct = max > 0 ? (window.scrollY / max) * 100 : 100;
            if (pct >= t.scroll_percent) finish();
        };
        const onExit = (e: MouseEvent) => {
            if (e.relatedTarget === null && e.clientY <= 0) finish();
        };
        if (needsScroll) window.addEventListener('scroll', onScroll, { passive: true });
        if (needsExit) document.addEventListener('mouseout', onExit);
        if (delay > 0) setTimeout(finish, delay);
    });
}

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

/**
 * One call to the widget API. A momentary failure is retried once — see
 * retry.ts for why, and for what deliberately is not retried.
 */
async function api<T>(path: string, body?: unknown): Promise<T> {
    const response = await fetchWithRetry(() =>
        fetch(`${origin}/wc/${widgetKey}/${path}`, {
            method: body === undefined ? 'GET' : 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
            body: body === undefined ? undefined : JSON.stringify(body),
        }),
    );

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
    font-family: system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
    color: #1f2a37; line-height: 1.5; -webkit-font-smoothing: antialiased;
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
    background: #fff; border: 1px solid #e3e6ea; border-radius: 14px;
    padding: 12px 36px 12px 14px; font-size: 14px; font-weight: 500;
    box-shadow: 0 12px 30px -12px rgba(11,26,35,.35);
    animation: pop .25s ease;
}
.root.right .teaser { right: 0; } .root.left .teaser { left: 0; }
.teaser-close {
    position: absolute; top: 6px; right: 6px; width: 22px; height: 22px;
    border: 0; background: transparent; color: #6b7682; border-radius: 6px; line-height: 1;
}
.teaser-close:hover { background: #f2f3f5; }

/* Panel: a fixed-height window, so nothing jumps as answers come and go */
.panel {
    position: absolute; bottom: 76px; width: 400px; height: min(680px, calc(100vh - 112px));
    display: flex; flex-direction: column; overflow: hidden;
    background: #fff; border-radius: 16px;
    box-shadow: 0 5px 40px rgba(15,23,42,.16), 0 1px 3px rgba(15,23,42,.08);
    animation: pop .22s ease;
    transition: width .2s ease, height .2s ease;
}
.panel.expanded { width: min(760px, calc(100vw - 40px)); height: calc(100vh - 112px); }
.root.right .panel { right: 0; } .root.left .panel { left: 0; }
@keyframes pop { from { opacity: 0; transform: translateY(10px) scale(.98); } to { opacity: 1; transform: none; } }

/* Header */
.head { flex: 0 0 auto; display: flex; align-items: center; gap: 14px; padding: 16px 10px 16px 20px; background: ${accent}; color: #fff; }
.head-ava { position: relative; flex: 0 0 auto; }
.avatar {
    width: 44px; height: 44px; border-radius: 50%; flex: 0 0 auto; overflow: hidden;
    background: rgba(255,255,255,.22); color: #fff;
    display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 15px;
}
.avatar.logo { background: #fff; padding: 4px; }
.avatar.logo img { width: 100%; height: 100%; object-fit: contain; display: block; border-radius: 50%; }
.online { position: absolute; right: -1px; bottom: 1px; width: 13px; height: 13px; border-radius: 50%; background: #22c55e; border: 2px solid #fff; }
.head-text { flex: 1 1 auto; min-width: 0; }
.head-title { font-weight: 700; font-size: 18px; line-height: 1.3; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.head-sub { font-size: 12.5px; opacity: .9; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.head-btn {
    flex: 0 0 auto; width: 40px; height: 40px; border: 0; border-radius: 10px;
    background: transparent; color: #fff; display: flex; align-items: center; justify-content: center;
}
.head-btn svg { width: 22px; height: 22px; }
.head-btn:hover { background: rgba(255,255,255,.16); }
.head-btn:focus-visible { outline: 2px solid #fff; outline-offset: -2px; }

/* Conversation */
.log {
    position: relative; flex: 1 1 auto; overflow-y: auto; overscroll-behavior: contain;
    padding: 20px 20px 16px; display: flex; flex-direction: column; gap: 14px; background: #fff;
    scrollbar-width: thin; scrollbar-color: #d3d8de transparent;
}
.log::-webkit-scrollbar { width: 6px; }
.log::-webkit-scrollbar-thumb { background: #d3d8de; border-radius: 3px; }
.log::-webkit-scrollbar-button { display: none; width: 0; height: 0; }
.group { display: flex; gap: 12px; align-items: flex-start; max-width: 94%; }
.group .avatar { width: 36px; height: 36px; font-size: 12px; background: ${accent}; }
.group .avatar.logo { background: #fff; padding: 3px; box-shadow: 0 0 0 1px #e5e8ec; }
.stack { display: flex; flex-direction: column; align-items: flex-start; gap: 8px; min-width: 0; }
.msg { padding: 12px 16px; border-radius: 14px; font-size: 15px; line-height: 1.55; white-space: pre-wrap; overflow-wrap: anywhere; }
.msg.bot { background: #f2f3f5; color: #1f2a37; }
.msg.visitor { align-self: flex-end; max-width: 80%; background: ${accent}; color: #fff; }
.msg.visitor.sending { opacity: .6; }
.msg.picture { display: block; padding: 4px; line-height: 0; }
.msg.picture img { display: block; max-width: min(240px, 100%); max-height: 280px; border-radius: 10px; object-fit: cover; }
.msg.picture .picture-name { display: none; }
.msg.picture.waiting { padding: 12px 16px; line-height: 1.55; }
.msg.picture.waiting img { display: none; }
.msg.picture.waiting .picture-name { display: inline; }
.msg.picture:not(.waiting) { cursor: zoom-in; }

/* Picture viewer: full size over the website, the chat still open behind it */
.viewer {
    position: fixed; inset: 0; z-index: 1; display: flex; flex-direction: column;
    background: rgba(15,23,42,.9); animation: fade .15s ease;
}
.viewer-bar { flex: 0 0 auto; display: flex; align-items: center; gap: 8px; padding: 12px 16px; color: #fff; }
.viewer-name { flex: 1 1 auto; min-width: 0; font-size: 14px; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.viewer-action {
    flex: 0 0 auto; display: inline-flex; align-items: center; justify-content: center; height: 40px; padding: 0 14px;
    border: 0; border-radius: 10px; background: rgba(255,255,255,.14); color: #fff; font-size: 13px; font-weight: 600; text-decoration: none;
}
.viewer-action:hover { background: rgba(255,255,255,.24); }
.viewer-action:focus-visible { outline: 2px solid #fff; outline-offset: 2px; }
.viewer-close { width: 40px; padding: 0; }
.viewer-close svg { width: 20px; height: 20px; }
.viewer-stage { flex: 1 1 auto; min-height: 0; display: flex; align-items: center; justify-content: center; padding: 0 16px 24px; cursor: zoom-out; }
.viewer-stage img { max-width: 100%; max-height: 100%; object-fit: contain; border-radius: 8px; box-shadow: 0 20px 60px rgba(0,0,0,.5); cursor: default; }
@keyframes fade { from { opacity: 0; } to { opacity: 1; } }
.msg.picture:focus-visible, .msg.file-link:focus-visible { outline: 2px solid ${accent}; outline-offset: 2px; }
.msg.file-link { text-decoration: none; }
.msg.file-link[href] { text-decoration: underline; text-underline-offset: 2px; }
.typing { display: flex; gap: 4px; padding: 16px; background: #f2f3f5; border-radius: 14px; }
.typing i { width: 7px; height: 7px; border-radius: 50%; background: #9aa3ad; animation: blink 1.2s infinite; }
.typing i:nth-child(2) { animation-delay: .2s; } .typing i:nth-child(3) { animation-delay: .4s; }
@keyframes blink { 0%,60%,100% { opacity: .3; } 30% { opacity: 1; } }

/* Answers, offered in the conversation right under the question they answer */
.choices { display: flex; flex-direction: column; align-items: flex-start; gap: 8px; max-width: 100%; }
.rating { display: flex; flex-direction: column; gap: 6px; align-items: flex-start; }
.rating-q { font-size: 13px; color: #5b6673; }
.stars { display: flex; gap: 4px; }
.star {
    width: 34px; height: 34px; display: flex; align-items: center; justify-content: center;
    background: #fff; border: 1px solid #9aa5b1; border-radius: 10px; font-size: 17px; line-height: 1;
    color: #9aa5b1; cursor: pointer; transition: border-color .15s ease, color .15s ease, transform .15s ease;
}
.star:hover, .star:focus-visible { border-color: var(--accent); color: #f5a623; transform: translateY(-1px); }
.star[aria-pressed="true"] { color: #f5a623; border-color: #f5a623; }
.rating-thanks { font-size: 13px; color: #5b6673; }
.choice {
    max-width: 100%; text-align: left; padding: 10px 16px; font-size: 14.5px; font-weight: 600; line-height: 1.4;
    background: #fff; color: #2d3e50; border: 1px solid #9aa5b1; border-radius: 12px;
    transition: border-color .15s ease, background .15s ease;
}
.choice:hover { border-color: ${accent}; background: ${accent}14; }
.choice:focus-visible { outline: 2px solid ${accent}; outline-offset: 2px; }
.choice.quiet { font-weight: 500; color: #5b6673; border-style: dashed; }
.cta {
    display: inline-block; padding: 10px 18px; border-radius: 12px;
    background: ${accent}; color: #fff; font-weight: 700; font-size: 14.5px; text-decoration: none;
}
.cta:focus-visible { outline: 2px solid ${accent}; outline-offset: 2px; }
.note { font-size: 12.5px; color: #5b6673; }

/* Privacy notice and the message box */
.notice { flex: 0 0 auto; padding: 14px 20px; background: #f5f5f4; color: #4b5563; font-size: 13px; line-height: 1.6; }
.notice a { color: inherit; text-decoration: underline; }
.foot { flex: 0 0 auto; display: flex; flex-direction: column; gap: 6px; padding: 12px 16px 8px; background: #fff; border-top: 1px solid #eef0f2; }
.notice + .foot { border-top: 0; }
.err { color: #b42318; font-size: 13px; padding: 0 8px; }
.compose {
    display: flex; align-items: center; gap: 4px; min-height: 50px; padding: 4px 5px 4px 20px;
    border: 1px solid #c5ccd3; border-radius: 999px; background: #fff;
    transition: border-color .15s ease, box-shadow .15s ease;
}
.compose:focus-within { border-color: ${accent}; box-shadow: 0 0 0 3px ${accent}26; }
.compose input {
    flex: 1 1 auto; min-width: 0; padding: 10px 0; border: 0; outline: 0; background: transparent;
    font: inherit; font-size: 15px; color: #1f2a37;
}
.compose input::placeholder { color: #8a939d; }
.compose input:disabled { cursor: not-allowed; }
.send {
    flex: 0 0 auto; width: 40px; height: 40px; border: 0; border-radius: 50%;
    background: transparent; color: #8a939d; display: flex; align-items: center; justify-content: center;
    transition: color .15s ease, background .15s ease;
}
.send svg { width: 21px; height: 21px; }
.send.ready { color: ${accent}; }
.send:hover:not(:disabled) { background: #f2f3f5; }
.send:disabled { cursor: not-allowed; opacity: .5; }
.send:focus-visible { outline: 2px solid ${accent}; outline-offset: 1px; }
.brand { text-align: center; font-size: 11px; color: #9aa3ad; }

@media (prefers-reduced-motion: reduce) {
    .panel, .teaser, .viewer { animation: none; transition: none; }
    .launcher, .choice, .compose, .send { transition: none; }
    .typing i { animation: none; opacity: .6; }
}

@media (max-width: 480px) {
    .root { bottom: 16px; }
    .root.left { left: 16px; } .root.right { right: 16px; }
    .panel, .panel.expanded { position: fixed; inset: 0; width: 100vw; height: 100dvh; border-radius: 0; }
    .expand { display: none; }
}
`;

const svg = (paths: string) =>
    `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${paths}</svg>`;
const ICON_EXPAND = svg('<path d="M14 4h6v6"/><path d="M20 4l-7 7"/><path d="M10 20H4v-6"/><path d="M4 20l7-7"/>');
const ICON_SHRINK = svg('<path d="M20 10h-6V4"/><path d="M14 10l7-7"/><path d="M4 14h6v6"/><path d="M10 14l-7 7"/>');
const ICON_CLOSE = svg('<path d="M6 6l12 12"/><path d="M18 6L6 18"/>');
const ICON_SEND = svg('<path d="M22 2L11 13"/><path d="M22 2l-7 20-4-9-9-4 20-7z"/>');
const ICON_CLIP = svg(
    '<path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/>',
);
/**
 * Visitors may send pictures only - no documents, text, PDFs or video. This is
 * what the file picker offers (on a phone: the camera and photo library); the
 * server reads the type from the bytes and refuses anything else regardless.
 */
const INLINE_IMAGE_TYPES = ['image/png', 'image/jpeg', 'image/gif', 'image/webp'];
const ATTACHMENT_TYPES = INLINE_IMAGE_TYPES.join(',');
const PICTURES_ONLY = 'You can send pictures only: PNG, JPG, GIF or WebP.';

/** What the box is for once a person has the chat: plain messages, no script. */
const LIVE_NODE: ChatNode = { id: '_live', type: 'input', text: 'Write a message', input: 'text', optional: false, live: true };

class ChatWidget {
    private root!: ShadowRoot;
    private el!: HTMLDivElement;
    private log!: HTMLDivElement;
    private foot!: HTMLDivElement;
    private input!: HTMLInputElement;
    private sendButton!: HTMLButtonElement;
    private panel: HTMLDivElement | null = null;
    private token: string | null = null;
    /** Asked once per conversation, however many times it ends on screen. */
    private rated = false;
    private open = false;
    private busy = false;
    private live = false;
    private expanded = false;
    private attachButton: HTMLButtonElement | null = null;
    // Message ids already drawn, so a line carried by both a reply and a poll appears once.
    private shown = new Set<number>();
    private lastSeenId = 0;
    // The question on screen, kept so a refused answer can put its controls back.
    private lastNode: ChatNode | null = null;
    private pollTimer: number | null = null;
    private openTracked = false;

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

    private initials(): string {
        return this.config.theme.company.trim().slice(0, 2).toUpperCase();
    }

    /** A round avatar: the company's uploaded logo, or its initials. */
    private avatar(): HTMLDivElement {
        const avatar = document.createElement('div');
        avatar.className = 'avatar';
        avatar.setAttribute('aria-hidden', 'true');
        avatar.textContent = this.initials();
        this.showLogo(avatar, this.initials());
        return avatar;
    }

    /**
     * The company's uploaded logo in place of its initials. Built with DOM calls
     * rather than markup so the URL is never parsed as HTML, and it falls back
     * to the initials if the image fails to load, so the chat never shows a
     * broken-image icon on the customer's site.
     */
    private showLogo(avatar: HTMLDivElement, initials: string) {
        const url = this.config.theme.logo_url;
        if (!url) return;

        const img = document.createElement('img');
        img.alt = '';
        img.src = url;
        img.addEventListener('error', () => {
            avatar.classList.remove('logo');
            avatar.textContent = initials;
        });
        avatar.textContent = '';
        avatar.classList.add('logo');
        avatar.appendChild(img);
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

        setTimeout(
            () => {
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
            },
            Math.max(0, this.config.teaser_delay ?? 4) * 1000,
        );
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
        this.el.querySelector('.viewer')?.remove();
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
        panel.innerHTML = `
            <div class="head">
                <div class="head-ava"><span class="online" aria-hidden="true"></span></div>
                <div class="head-text">
                    <div class="head-title">${escape(this.config.theme.title)}</div>
                    <div class="head-sub">Typically replies in a few minutes</div>
                </div>
                <button type="button" class="head-btn expand"></button>
                <button type="button" class="head-btn head-close" aria-label="Close chat">${ICON_CLOSE}</button>
            </div>
            <div class="log" role="log" aria-live="polite" aria-atomic="false"></div>
            <div class="foot">
                <div class="compose">
                    <input type="text" placeholder="Write a message…" aria-label="Write a message" autocomplete="off" maxlength="1000">
                    ${
                        this.config.attachments
                            ? `<button type="button" class="send attach" aria-label="Send a picture" title="Send a picture">${ICON_CLIP}</button>
                               <input type="file" class="file" hidden accept="${ATTACHMENT_TYPES}">`
                            : ''
                    }
                    <button type="button" class="send" aria-label="Send message">${ICON_SEND}</button>
                </div>
                ${this.config.branding === false ? '' : '<div class="brand">Powered by Piotrack</div>'}
            </div>
        `;
        this.el.appendChild(panel);
        this.panel = panel;
        panel.querySelector('.head-ava')?.prepend(this.avatar());
        this.log = panel.querySelector('.log') as HTMLDivElement;
        this.foot = panel.querySelector('.foot') as HTMLDivElement;
        this.input = panel.querySelector('.compose input[type="text"]') as HTMLInputElement;
        this.sendButton = panel.querySelector('.send:not(.attach)') as HTMLButtonElement;
        this.attachButton = panel.querySelector('.attach');
        this.shown.clear();
        this.renderNotice();
        this.setExpanded(this.expanded);

        panel.querySelector('.head-close')?.addEventListener('click', () => this.close());
        panel.querySelector('.expand')?.addEventListener('click', () => this.setExpanded(!this.expanded));
        panel.addEventListener('keydown', (e) => {
            if ((e as KeyboardEvent).key === 'Escape') this.close();
        });
        this.input.addEventListener('input', () => this.sendButton.classList.toggle('ready', this.input.value.trim() !== ''));
        this.input.addEventListener('keydown', (e) => {
            if ((e as KeyboardEvent).key === 'Enter') {
                e.preventDefault();
                this.submit();
            }
        });
        this.sendButton.addEventListener('click', () => this.submit());
        const picker = panel.querySelector('.file') as HTMLInputElement | null;
        this.attachButton?.addEventListener('click', () => picker?.click());
        picker?.addEventListener('change', () => {
            const file = picker.files?.[0];
            picker.value = '';
            if (file) void this.attach(file);
        });
        this.setComposer(false);

        // Count one open per page load: a visitor toggling the panel is still a
        // single opened chat, and counting each toggle would push the funnel's
        // open rate above 100% and make the whole report untrustworthy.
        if (!this.openTracked) {
            this.openTracked = true;
            this.track('open');
        }

        if (this.token === null) {
            await this.begin();
        } else {
            // Reopening an existing chat must not show an empty window: replay
            // what was already said, then resume polling if it is still live.
            await this.restore();
        }
    }

    /**
     * The standing privacy line above the message box, linking the company's
     * own policy. Shown only when the company has given one to link to.
     */
    private renderNotice() {
        const url = this.config.privacy_url;
        if (!url || !/^https?:\/\//i.test(url)) return;

        const notice = document.createElement('div');
        notice.className = 'notice';
        const link = document.createElement('a');
        link.href = url;
        link.target = '_blank';
        link.rel = 'noopener noreferrer';
        link.textContent = 'Privacy Policy';
        notice.append(
            'By using this chat, you agree to it being recorded and to your details being used to respond to you, as set out in our ',
            link,
            '.',
        );
        this.foot.before(notice);
    }

    private setExpanded(on: boolean) {
        this.expanded = on;
        this.panel?.classList.toggle('expanded', on);
        const button = this.panel?.querySelector('.expand');
        if (!button) return;
        button.innerHTML = on ? ICON_SHRINK : ICON_EXPAND;
        button.setAttribute('aria-label', on ? 'Make the chat smaller' : 'Make the chat bigger');
        button.setAttribute('aria-pressed', String(on));
    }

    private setComposer(enabled: boolean, placeholder = 'Write a message…') {
        this.input.disabled = !enabled;
        this.sendButton.disabled = !enabled;
        if (this.attachButton) this.attachButton.disabled = !enabled;
        this.input.placeholder = placeholder;
    }

    /**
     * Send a picture the visitor picked. Anything that is not a picture is
     * refused here, before it is uploaded (the server refuses it too). The
     * picture is made smaller first and shows in the conversation straight
     * away. It does not answer the question on screen, which stays as it was.
     */
    private async attach(picked: File) {
        if (!this.token || this.input.disabled) return;
        this.clearError();

        // A picker can still be switched to "All files", and files can be dropped
        // in from elsewhere: say so plainly rather than uploading and failing.
        if (!INLINE_IMAGE_TYPES.includes(picked.type)) {
            this.error(PICTURES_ONLY);
            return;
        }

        const file = await compressImage(picked);
        if (file.size > 5 * 1024 * 1024) {
            this.error('Pictures can be up to 5 MB.');
            return;
        }

        const local = URL.createObjectURL(file);
        const sending = this.picture('visitor', local, file.name);
        sending.classList.add('sending');
        const body = new FormData();
        body.append('file', file);

        try {
            const response = await fetch(`${origin}/wc/${widgetKey}/conversations/${this.token}/files`, {
                method: 'POST',
                headers: { Accept: 'application/json' },
                body,
            });
            const data = (await response.json().catch(() => ({}))) as {
                errors?: Record<string, string[]>;
                message?: { id?: number; attachment?: Attachment | null };
            };
            if (!response.ok) {
                sending.remove();
                URL.revokeObjectURL(local);
                const refused = response.status === 422 ? Object.values(data.errors ?? {})[0]?.[0] : undefined;
                this.error(refused ?? 'That picture could not be sent. Please try again.');
                return;
            }
            sending.classList.remove('sending');
            const saved = data.message?.attachment?.url;
            if (saved) {
                sending.href = saved;
                // Show the saved copy from here on: many websites' security
                // policies refuse the browser's own local copy (a blob: address),
                // but allow images from where the chat itself is served.
                const img = sending.querySelector('img');
                if (img) img.src = saved;
            }
            URL.revokeObjectURL(local);
            if (typeof data.message?.id === 'number') this.shown.add(data.message.id);
        } catch {
            sending.remove();
            URL.revokeObjectURL(local);
            this.error('That picture could not be sent. Please try again.');
        }
    }

    /** A line from the conversation, drawn once even when both a reply and a poll carry it. */
    private incoming(message: Message) {
        if (typeof message.id === 'number') {
            if (this.shown.has(message.id)) return;
            this.shown.add(message.id);
            this.lastSeenId = Math.max(this.lastSeenId, message.id);
        }
        const role = message.role === 'visitor' ? 'visitor' : 'bot';
        const file = message.attachment;
        if (file?.image) this.picture(role, file.url, file.name, file.url);
        else if (file) this.fileLink(role, message.body, file.url);
        else this.bubble(role, message.body);
    }

    /**
     * A picture in the conversation. Opening it shows it full size over the
     * page (the visitor never leaves the website, or the chat); a ctrl/cmd or
     * middle click still opens it in a new tab, as for any link. Until the
     * image has actually loaded the bubble shows its name, and it stays that
     * way if the website's security policy will not show it - a link to the
     * file, never a broken-image icon.
     */
    private picture(role: 'bot' | 'visitor', src: string, name: string, href?: string): HTMLAnchorElement {
        const link = document.createElement('a');
        link.className = `msg ${role} picture waiting`;
        link.target = '_blank';
        link.rel = 'noopener noreferrer';
        link.setAttribute('aria-label', `View ${name}`);
        if (href) link.href = href;
        link.addEventListener('click', (e) => {
            const shown = link.querySelector('img');
            if (e.button !== 0 || e.ctrlKey || e.metaKey || e.shiftKey || e.altKey) return;
            if (link.classList.contains('waiting') || !shown) return; // blocked here: let the link open it
            e.preventDefault();
            this.viewPicture(shown.currentSrc || shown.src, name, link.href || shown.src, link);
        });
        const label = document.createElement('span');
        label.className = 'picture-name';
        label.textContent = `📎 ${name}`;
        const img = document.createElement('img');
        img.alt = name;
        img.addEventListener('load', () => {
            link.classList.remove('waiting');
            // Its height is only known now; keep the chat in view.
            this.reveal();
        });
        img.addEventListener('error', () => link.classList.add('waiting'));
        img.src = src;
        link.append(label, img);
        return this.place(link, role);
    }

    /**
     * A picture full size over the website, like any messenger's viewer: the
     * visitor stays on the page with the chat still open behind it. Closes on
     * the button, Esc, or a click outside the picture, and hands focus back to
     * the picture that opened it.
     */
    private viewPicture(src: string, name: string, href: string, opener: HTMLElement) {
        this.el.querySelector('.viewer')?.remove();

        const viewer = document.createElement('div');
        viewer.className = 'viewer';
        viewer.setAttribute('role', 'dialog');
        viewer.setAttribute('aria-modal', 'true');
        viewer.setAttribute('aria-label', name);

        const bar = document.createElement('div');
        bar.className = 'viewer-bar';
        const title = document.createElement('span');
        title.className = 'viewer-name';
        title.textContent = name;
        const newTab = document.createElement('a');
        newTab.className = 'viewer-action';
        newTab.href = href;
        newTab.target = '_blank';
        newTab.rel = 'noopener noreferrer';
        newTab.textContent = 'Open in new tab';
        const close = document.createElement('button');
        close.type = 'button';
        close.className = 'viewer-action viewer-close';
        close.setAttribute('aria-label', 'Close picture');
        close.innerHTML = ICON_CLOSE;
        bar.append(title, newTab, close);

        const stage = document.createElement('div');
        stage.className = 'viewer-stage';
        const img = document.createElement('img');
        img.src = src;
        img.alt = name;
        stage.appendChild(img);
        viewer.append(bar, stage);

        const shut = () => {
            viewer.remove();
            opener.focus({ preventScroll: true });
        };
        close.addEventListener('click', shut);
        // A click on the dark area around the picture closes it; on the picture it does not.
        stage.addEventListener('click', (e) => {
            if (e.target === stage) shut();
        });
        viewer.addEventListener('keydown', (e) => {
            if ((e as KeyboardEvent).key === 'Escape') {
                // Close the picture only, not the chat behind it.
                e.stopPropagation();
                shut();
            }
        });

        this.el.appendChild(viewer);
        close.focus();
    }

    /** A file that is not a picture: its name, downloading it when opened. */
    private fileLink(role: 'bot' | 'visitor', label: string, href?: string): HTMLAnchorElement {
        const link = document.createElement('a');
        link.className = `msg ${role} file-link`;
        link.textContent = label;
        link.rel = 'noopener noreferrer';
        if (href) link.href = href;
        return this.place(link, role);
    }

    /** Send what is in the box: an answer, a question for the assistant, or a message to the team. */
    private submit() {
        const value = this.input.value.trim();
        if (!value || this.busy || !this.lastNode) return;
        this.input.value = '';
        this.sendButton.classList.remove('ready');
        void this.send({ value }, value);
    }

    /**
     * Re-render a conversation the visitor already started: the whole
     * transcript, then whatever the chat is waiting for - the current
     * question's own options, or the message box if a person has it.
     */
    private async restore() {
        this.typing(true);
        try {
            const data = await api<{
                messages?: Message[];
                live?: boolean;
                closed?: boolean;
                node?: ChatNode | null;
            }>(`conversations/${this.token}/poll?since=0&transcript=1`);
            this.typing(false);
            (data.messages ?? []).forEach((m) => this.incoming(m));
            if (data.live && !data.closed) {
                this.live = true;
                this.controls(LIVE_NODE);
            } else if (data.node && !data.closed) {
                this.controls(data.node);
            } else {
                this.ended();
            }
        } catch {
            this.fail();
        }
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
            await this.render(reply);
        } catch {
            this.fail();
        }
    }

    private async send(payload: { option?: string; value?: string }, echo: string) {
        if (this.busy || !this.token) return;
        this.busy = true;
        // Once an answer is given the offered ones go, as in any chat app:
        // the visitor's reply now stands where the buttons were.
        this.clearAnswers();
        this.clearError();
        const echoed = this.bubble('visitor', echo);
        this.typing(true);

        try {
            const reply = await api<Reply>(`conversations/${this.token}/messages`, payload);
            // Awaited, so the composer stays shut while the bot is still typing.
            await this.render(reply);
        } catch (error) {
            const status = (error as { status?: number }).status;
            const payloadErr = (error as { payload?: { errors?: Record<string, string[]> } }).payload;
            this.typing(false);
            if (status === 422 && payloadErr?.errors) {
                const message = Object.values(payloadErr.errors)[0]?.[0] ?? 'Please check that answer.';
                // Refused, so nothing was said: take the reply back out of the
                // transcript, put the question's answers back, and return what
                // was typed to the box to be corrected rather than retyped.
                echoed.remove();
                if (this.lastNode) this.controls(this.lastNode, payload.value ?? '');
                this.error(message);
            } else {
                this.fail();
            }
        } finally {
            this.busy = false;
        }
    }

    /**
     * Replies from the team arrive by polling: this stack has no websocket
     * server, and the product runs on isolated networks where one could not be
     * reached anyway. The widget polls whenever the chat is open and going, so
     * a person can step into a bot conversation at any point - and the server
     * learns the visitor is still here, so it does not email what they can see.
     * Polling stops when the conversation ends or the widget is shut.
     */
    private startPolling() {
        if (this.pollTimer !== null || !this.token) return;
        this.pollTimer = window.setInterval(() => void this.poll(), 5000);
    }

    private stopPolling() {
        if (this.pollTimer !== null) {
            window.clearInterval(this.pollTimer);
            this.pollTimer = null;
        }
    }

    private async poll() {
        // While an answer is on its way, the reply itself brings what comes next.
        if (!this.token || !this.open || this.busy) return;
        try {
            const data = await api<{ messages?: Message[]; live?: boolean; closed?: boolean }>(
                `conversations/${this.token}/poll?since=${this.lastSeenId}`,
            );
            (data.messages ?? []).forEach((m) => this.incoming(m));

            // A person stepped in: the script stands down and the box is theirs.
            if (data.live && !this.live && !data.closed) {
                this.live = true;
                const sub = this.panel?.querySelector('.head-sub');
                if (sub) sub.textContent = 'Connected to our team';
                this.controls(LIVE_NODE);
            }
            if (data.closed) {
                this.stopPolling();
                this.ended();
            }
        } catch {
            /* a blip must not break the visitor's chat */
        }
    }

    private async render(reply: Reply) {
        this.typing(false);
        // A step can ask for a pause before it speaks, so a run of messages
        // arrives the way a person types them rather than all at once.
        for (const message of reply.messages ?? []) {
            const pause = Math.min(10, Math.max(0, Number(message.delay ?? 0))) * 1000;
            if (pause > 0) {
                this.typing(true);
                await new Promise((resume) => window.setTimeout(resume, pause));
                this.typing(false);
            }
            this.incoming({ ...message, role: 'bot' });
        }

        if (reply.booking_url) {
            const link = document.createElement('a');
            link.className = 'cta';
            link.href = reply.booking_url;
            link.target = '_blank';
            link.rel = 'noopener noreferrer';
            link.textContent = 'Choose a time';
            this.botStack().appendChild(link);
        }

        if (reply.live) {
            this.live = true;
            const sub = this.panel?.querySelector('.head-sub');
            if (sub) sub.textContent = reply.agent ? `${reply.agent} is here to help` : 'Connected to our team';
        }

        const node = reply.node;
        if (!node) {
            this.stopPolling();
            this.ended();
            return;
        }

        this.controls(node);
    }

    /**
     * Get ready for the question being asked: its answers as buttons under it
     * in the conversation, and the message box set up for the reply - an email
     * keyboard for an email, and so on. The box stays usable on every step;
     * a typed reply that names one of the answers counts as picking it.
     */
    private controls(node: ChatNode, prefill = '') {
        this.startPolling();
        // A new question (or a person taking over) makes any older error stale;
        // a refusal of this very answer is shown again right after this call.
        this.clearError();
        this.lastNode = node;
        this.clearAnswers();

        const answers = document.createElement('div');
        answers.className = 'choices';
        const offer = (label: string, pick: () => void, quiet = false) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = quiet ? 'choice quiet' : 'choice';
            button.textContent = label;
            button.addEventListener('click', pick);
            answers.appendChild(button);
        };

        if (node.type === 'choice' || node.type === 'consent') {
            (node.options ?? []).forEach((option) => offer(option.label, () => void this.send({ option: option.id }, option.label)));
            if (node.type === 'consent' && !this.panel?.querySelector('.notice')) {
                const note = document.createElement('div');
                note.className = 'note';
                note.textContent = 'Your details are only used to respond to this enquiry.';
                answers.appendChild(note);
            }
        }

        const asks = node.type === 'input' && !node.live;
        if (asks) {
            // Suggested questions (CHAT-045): tappable prompts so a visitor
            // knows what the assistant can answer, sent exactly as if typed.
            (node.suggestions ?? []).slice(0, 4).forEach((question) => offer(question, () => void this.send({ value: question }, question)));
            if (node.optional) offer('Skip this', () => void this.send({ value: '' }, '—'), true);
        }

        if (answers.childElementCount > 0) this.botStack().appendChild(answers);

        const kind = asks ? node.input : 'text';
        this.input.type = kind === 'email' ? 'email' : kind === 'phone' ? 'tel' : 'text';
        this.input.inputMode = kind === 'email' ? 'email' : kind === 'phone' ? 'tel' : kind === 'number' ? 'decimal' : 'text';
        this.input.setAttribute('autocomplete', kind === 'email' ? 'email' : kind === 'phone' ? 'tel' : 'off');
        this.input.setAttribute('aria-label', asks ? node.text : 'Write a message');
        this.setComposer(true, asks ? 'Type your answer…' : 'Write a message…');
        this.input.value = prefill;
        this.sendButton.classList.toggle('ready', prefill.trim() !== '');

        const first = answers.querySelector('.choice') as HTMLElement | null;
        if (first && !asks) first.focus({ preventScroll: true });
        else this.input.focus({ preventScroll: true });

        this.reveal();
    }

    /** The conversation is over: say so in the box, and offer a fresh start. */
    private ended() {
        this.stopPolling();
        this.lastNode = null;
        this.clearAnswers();
        this.setComposer(false, 'This chat has ended');

        if (this.config?.rating !== false && this.token && !this.rated) this.askHowItWent();

        const answers = document.createElement('div');
        answers.className = 'choices';
        const again = document.createElement('button');
        again.type = 'button';
        again.className = 'choice';
        again.textContent = 'Start a new chat';
        again.addEventListener('click', () => {
            this.token = null;
            this.lastSeenId = 0;
            this.live = false;
            this.rated = false;
            this.log.innerHTML = '';
            this.shown.clear();
            this.clearError();
            void this.begin();
        });
        answers.appendChild(again);
        this.botStack().appendChild(answers);
        this.reveal();
    }

    /**
     * One to five, asked once and only once the chat is over. Skipping it is
     * simply not answering: there is no nagging and nothing blocks the visitor.
     */
    private askHowItWent() {
        this.rated = true;
        const box = document.createElement('div');
        box.className = 'rating';
        const question = document.createElement('p');
        question.className = 'rating-q';
        question.textContent = 'How did we do?';
        const stars = document.createElement('div');
        stars.className = 'stars';
        stars.setAttribute('role', 'group');
        stars.setAttribute('aria-label', 'How did we do? One star to five.');

        for (let score = 1; score <= 5; score++) {
            const star = document.createElement('button');
            star.type = 'button';
            star.className = 'star';
            star.textContent = '★';
            star.setAttribute('aria-label', `${score} ${score === 1 ? 'star' : 'stars'}`);
            star.setAttribute('aria-pressed', 'false');
            star.addEventListener('click', () => {
                [...stars.children].forEach((other, index) => other.setAttribute('aria-pressed', index < score ? 'true' : 'false'));
                [...stars.children].forEach((other) => ((other as HTMLButtonElement).disabled = true));
                const thanks = document.createElement('p');
                thanks.className = 'rating-thanks';
                thanks.setAttribute('role', 'status');
                thanks.textContent = 'Thank you.';
                box.appendChild(thanks);
                void api(`conversations/${this.token}/rating`, { rating: score }).catch(() => undefined);
            });
            stars.appendChild(star);
        }

        box.appendChild(question);
        box.appendChild(stars);
        this.botStack().appendChild(box);
    }

    private clearAnswers() {
        this.log.querySelectorAll('.choices').forEach((el) => el.remove());
    }

    /**
     * The bot's current run of messages. Consecutive lines share one avatar,
     * as in any messenger; a visitor reply starts a new run after it.
     */
    private botStack(): HTMLDivElement {
        const last = this.log.lastElementChild;
        if (last?.classList.contains('group')) return last.querySelector('.stack') as HTMLDivElement;

        const group = document.createElement('div');
        group.className = 'group';
        const stack = document.createElement('div');
        stack.className = 'stack';
        group.append(this.avatar(), stack);
        this.log.appendChild(group);
        return stack;
    }

    private bubble(role: 'bot' | 'visitor', body: string): HTMLDivElement {
        const div = document.createElement('div');
        div.className = `msg ${role}`;
        div.textContent = body;
        return this.place(div, role);
    }

    /** Put a line in the conversation: the bot's beside its avatar, the visitor's on the right. */
    private place<T extends HTMLElement>(element: T, role: 'bot' | 'visitor'): T {
        if (role === 'bot') this.botStack().appendChild(element);
        else this.log.appendChild(element);
        this.log.scrollTop = this.log.scrollHeight;
        return element;
    }

    /**
     * Scroll to the newest line - but never past the top of the question
     * being asked, so a long list of answers cannot push it out of view.
     */
    private reveal() {
        const bottom = this.log.scrollHeight - this.log.clientHeight;
        const last = this.log.lastElementChild;
        const lines = last?.classList.contains('group') ? last.querySelectorAll<HTMLElement>('.msg') : null;
        const question = lines && lines.length > 0 ? lines[lines.length - 1] : null;
        this.log.scrollTop = question ? Math.min(bottom, question.offsetTop - 16) : bottom;
    }

    private typing(on: boolean) {
        const current = this.log.querySelector('.typing');
        if (current) {
            const stack = current.parentElement;
            current.remove();
            // A run started only to hold the dots goes with them.
            if (stack && stack.childElementCount === 0) stack.parentElement?.remove();
        }
        if (!on) return;
        const dots = document.createElement('div');
        dots.className = 'typing';
        dots.setAttribute('role', 'status');
        dots.setAttribute('aria-label', 'Typing');
        dots.innerHTML = '<i></i><i></i><i></i>';
        this.botStack().appendChild(dots);
        this.log.scrollTop = this.log.scrollHeight;
    }

    private error(message: string) {
        this.clearError();
        const div = document.createElement('div');
        div.className = 'err';
        div.setAttribute('role', 'alert');
        div.textContent = message;
        this.foot.prepend(div);
    }

    private clearError() {
        this.foot.querySelector('.err')?.remove();
    }

    /** Never break the host site: show a calm fallback instead of an error. */
    private fail() {
        this.stopPolling();
        this.typing(false);
        this.clearAnswers();
        const contact = this.config.fallback_contact;
        this.bubble(
            'bot',
            contact
                ? `Sorry — our chat is temporarily unavailable. Please contact us at ${contact} and we will get back to you.`
                : 'Sorry — our chat is temporarily unavailable. Please use the contact details on this page and we will get back to you.',
        );
        this.lastNode = null;
        this.setComposer(false, 'Chat unavailable right now');
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
        // The visitor key rides along so teaser A/B variants stay sticky (CHAT-041).
        const config = await api<Config>(`config?vid=${encodeURIComponent(visitorId())}`);
        if (!targetingAllows(config.targeting)) return;
        await waitForTrigger(config.targeting);
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
