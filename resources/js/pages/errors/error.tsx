import { Head, Link } from '@inertiajs/react';

/**
 * The shared Inertia error page. Reached when the server returns 403/404/500/503
 * (see bootstrap/app.php), so it stands alone rather than inside the app layout
 * — a 500 may mean the layout's own data is unavailable. Rendering a real page
 * here is what stops those statuses surfacing as Inertia's blank white modal.
 */

const COPY: Record<number, { title: string; body: string }> = {
    403: { title: 'Not authorized', body: 'You do not have access to this page. If you think this is a mistake, contact your administrator.' },
    404: { title: 'Page not found', body: 'The page you were looking for does not exist or may have moved.' },
    500: { title: 'Something went wrong', body: 'An unexpected error occurred on our end. Please try again in a moment.' },
    503: { title: 'Down for maintenance', body: 'The app is briefly unavailable while we make some changes. Please check back shortly.' },
};

export default function ErrorPage({ status }: { status: number }) {
    const { title, body } = COPY[status] ?? { title: 'Something went wrong', body: 'An unexpected error occurred.' };

    return (
        <>
            <Head title={title} />
            <div className="bg-background flex min-h-screen flex-col items-center justify-center px-6 text-center">
                <div className="text-brand-strong font-mono text-5xl font-bold tabular-nums">{status}</div>
                <h1 className="text-foreground mt-3 text-2xl font-semibold tracking-tight">{title}</h1>
                <p className="text-muted-foreground mt-2 max-w-md text-sm">{body}</p>
                <div className="mt-6 flex gap-3">
                    <button
                        type="button"
                        onClick={() => window.history.back()}
                        className="border-border hover:border-brand hover:text-brand-strong rounded-lg border px-4 py-2 text-sm font-semibold transition-colors"
                    >
                        Go back
                    </button>
                    <Link
                        href="/dashboard"
                        className="bg-brand text-brand-foreground rounded-lg px-4 py-2 text-sm font-semibold transition-opacity hover:opacity-90"
                    >
                        Back to dashboard
                    </Link>
                </div>
            </div>
        </>
    );
}
