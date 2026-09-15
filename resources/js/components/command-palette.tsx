import { Dialog, DialogContent, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { usePermissions } from '@/hooks/use-permissions';
import { navigablePages, searchPages } from '@/lib/navigation';
import { cn } from '@/lib/utils';
import { router } from '@inertiajs/react';
import { CornerDownLeft, Search } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

type ResultItem = { title: string; subtitle: string | null; url: string };
type ResultGroup = { type: string; label: string; items: ResultItem[] };

const RECENT_KEY = 'piotrack:recent-searches';

function loadRecent(): string[] {
    try {
        return JSON.parse(localStorage.getItem(RECENT_KEY) ?? '[]');
    } catch {
        return [];
    }
}

/** "⌘K" on Apple devices, "Ctrl K" everywhere else — the keys the person actually presses. */
export function shortcutLabel(userAgent: string = typeof navigator === 'undefined' ? '' : navigator.userAgent): string {
    return /Mac|iPhone|iPad/.test(userAgent) ? '⌘K' : 'Ctrl K';
}

/**
 * Global search command palette (SRCH). Open with ⌘K / Ctrl-K or the header
 * button. Pages are matched instantly on the client from the same navigation
 * the sidebar uses (so they respect the same permissions); records come from
 * the tenant-scoped, permission-filtered search endpoint. Arrow keys move
 * through every result and Enter opens the highlighted one.
 */
export function CommandPalette() {
    const { can } = usePermissions();
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const [groups, setGroups] = useState<ResultGroup[]>([]);
    const [recent, setRecent] = useState<string[]>([]);
    const [active, setActive] = useState(0);
    const debounce = useRef<ReturnType<typeof setTimeout> | null>(null);

    const pages = searchPages(query, navigablePages(can));

    // One flat list in display order, so the keyboard walks exactly what is shown.
    const results = [
        ...pages.map(({ section, item }) => ({ key: `page:${item.url}`, title: item.title, subtitle: section as string | null, url: item.url })),
        ...groups.flatMap((group) => group.items.map((item, i) => ({ key: `${group.type}:${i}`, ...item }))),
    ];

    useEffect(() => {
        const onKey = (e: KeyboardEvent) => {
            if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
                e.preventDefault();
                setOpen((o) => !o);
            }
        };
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    }, []);

    useEffect(() => {
        if (open) {
            setRecent(loadRecent());
        } else {
            setQuery('');
            setGroups([]);
        }
    }, [open]);

    useEffect(() => {
        setActive(0);
        if (debounce.current) clearTimeout(debounce.current);
        if (query.trim() === '') {
            setGroups([]);
            return;
        }
        debounce.current = setTimeout(() => {
            fetch(`${route('search')}?q=${encodeURIComponent(query)}`, {
                headers: { Accept: 'application/json' },
            })
                .then((r) => r.json())
                .then((data) => setGroups(data.groups ?? []))
                .catch(() => setGroups([]));
        }, 200);
    }, [query]);

    const go = (url: string) => {
        const trimmed = query.trim();
        if (trimmed) {
            const next = [trimmed, ...loadRecent().filter((q) => q !== trimmed)].slice(0, 5);
            localStorage.setItem(RECENT_KEY, JSON.stringify(next));
        }
        setOpen(false);
        router.visit(url);
    };

    const onInputKey = (e: React.KeyboardEvent<HTMLInputElement>) => {
        if (results.length === 0) return;
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            setActive((i) => (i + 1) % results.length);
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            setActive((i) => (i - 1 + results.length) % results.length);
        } else if (e.key === 'Enter') {
            e.preventDefault();
            go(results[Math.min(active, results.length - 1)].url);
        }
    };

    const renderRow = (row: (typeof results)[number], index: number) => (
        <button
            key={row.key}
            id={`palette-option-${index}`}
            role="option"
            aria-selected={index === active}
            onMouseMove={() => setActive(index)}
            onClick={() => go(row.url)}
            className={cn(
                'flex w-full items-center justify-between gap-3 rounded px-2 py-1.5 text-left text-sm',
                index === active ? 'bg-muted text-foreground' : 'hover:bg-muted',
            )}
        >
            <span className="truncate">{row.title}</span>
            <span className="text-muted-foreground flex shrink-0 items-center gap-1.5 text-xs">
                {row.subtitle}
                {index === active && <CornerDownLeft className="size-3" aria-hidden />}
            </span>
        </button>
    );

    let offset = 0;

    return (
        <>
            <button
                type="button"
                onClick={() => setOpen(true)}
                className="text-muted-foreground hover:bg-muted flex items-center gap-2 rounded-md border px-2 py-1.5 text-sm"
                aria-label="Search pages and records"
            >
                <Search className="size-4" />
                <span className="hidden sm:inline">Search…</span>
                <kbd className="bg-muted ml-2 hidden rounded px-1 text-xs sm:inline">{shortcutLabel()}</kbd>
            </button>

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent className="top-24 max-w-lg translate-y-0 p-0">
                    <DialogTitle className="sr-only">Search</DialogTitle>
                    <div className="border-b p-3">
                        <Input
                            autoFocus
                            value={query}
                            onChange={(e) => setQuery(e.target.value)}
                            onKeyDown={onInputKey}
                            placeholder="Go to a page, or find a contact, deal, campaign…"
                            className="border-0 focus-visible:ring-0"
                            role="combobox"
                            aria-expanded={results.length > 0}
                            aria-controls="palette-results"
                            aria-activedescendant={results.length > 0 ? `palette-option-${active}` : undefined}
                        />
                    </div>
                    <div id="palette-results" role="listbox" aria-label="Results" className="max-h-80 overflow-y-auto p-2">
                        {query.trim() === '' ? (
                            recent.length > 0 ? (
                                <div>
                                    <p className="text-muted-foreground px-2 py-1 text-xs">Recent</p>
                                    {recent.map((r) => (
                                        <button
                                            key={r}
                                            onClick={() => setQuery(r)}
                                            className="hover:bg-muted block w-full rounded px-2 py-1.5 text-left text-sm"
                                        >
                                            {r}
                                        </button>
                                    ))}
                                </div>
                            ) : (
                                <p className="text-muted-foreground px-2 py-6 text-center text-sm">
                                    Type a page name like “keywords”, or a contact or deal.
                                </p>
                            )
                        ) : results.length === 0 ? (
                            <p className="text-muted-foreground px-2 py-6 text-center text-sm">Nothing matches “{query.trim()}”.</p>
                        ) : (
                            <>
                                {pages.length > 0 && (
                                    <div className="mb-2">
                                        <p className="text-muted-foreground px-2 py-1 text-xs">Pages</p>
                                        {results.slice(0, pages.length).map((row, i) => renderRow(row, i))}
                                    </div>
                                )}
                                {groups.map((group) => {
                                    const start = pages.length + offset;
                                    offset += group.items.length;

                                    return (
                                        <div key={group.type} className="mb-2">
                                            <p className="text-muted-foreground px-2 py-1 text-xs">{group.label}</p>
                                            {results.slice(start, start + group.items.length).map((row, i) => renderRow(row, start + i))}
                                        </div>
                                    );
                                })}
                            </>
                        )}
                    </div>
                </DialogContent>
            </Dialog>
        </>
    );
}
