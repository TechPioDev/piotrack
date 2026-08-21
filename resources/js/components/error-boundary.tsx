import { Component, type ErrorInfo, type ReactNode } from 'react';

/**
 * A last-resort boundary around the whole app. When a page component throws
 * during render, React otherwise unmounts the tree and leaves a blank white
 * page with no clue as to why. This catches that, logs it, and shows the error
 * on screen so a failure is diagnosable instead of silent.
 */
export class ErrorBoundary extends Component<{ children: ReactNode }, { error: Error | null }> {
    state: { error: Error | null } = { error: null };

    static getDerivedStateFromError(error: Error) {
        return { error };
    }

    componentDidCatch(error: Error, info: ErrorInfo) {
        console.error('Piotrack render error:', error, info.componentStack);
    }

    render() {
        const { error } = this.state;
        if (!error) {
            return this.props.children;
        }

        return (
            <div style={{ minHeight: '100vh', padding: '2rem', fontFamily: 'ui-monospace, monospace', color: '#0b1a23', background: '#fff' }}>
                <h1 style={{ fontSize: '1.25rem', fontWeight: 700, marginBottom: '0.5rem' }}>Something went wrong rendering this page</h1>
                <p style={{ color: '#6b7280', marginBottom: '1rem' }}>
                    The error below is what stopped the page from loading. Reloading may help; if it persists, share this text.
                </p>
                <div style={{ fontWeight: 700, color: '#b91c1c' }}>{error.message}</div>
                <pre style={{ marginTop: '0.75rem', fontSize: '0.75rem', whiteSpace: 'pre-wrap', overflowX: 'auto', color: '#334155' }}>
                    {error.stack}
                </pre>
                <button
                    type="button"
                    onClick={() => window.location.reload()}
                    style={{
                        marginTop: '1rem',
                        padding: '0.5rem 1rem',
                        borderRadius: '0.5rem',
                        border: 'none',
                        background: '#0bb39e',
                        color: '#fff',
                        fontWeight: 700,
                        cursor: 'pointer',
                    }}
                >
                    Reload
                </button>
            </div>
        );
    }
}
