import '../css/app.css';

import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';
import { route as routeFn } from 'ziggy-js';
import { ConnectionNotice } from './components/connection-notice';
import { ErrorBoundary } from './components/error-boundary';
import { initializeTheme } from './hooks/use-appearance';

declare global {
    const route: typeof routeFn;
}

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) => resolvePageComponent(`./pages/${name}.tsx`, import.meta.glob('./pages/**/*.tsx')),
    setup({ el, App, props }) {
        const root = createRoot(el);

        root.render(
            <ErrorBoundary>
                <App {...props} />
                <ConnectionNotice />
            </ErrorBoundary>,
        );
    },
    progress: {
        // The brand token, so the bar belongs to the product in both themes.
        color: 'var(--brand)',
        // Fast visits stay flicker-free; anything slower shows it is working.
        delay: 150,
    },
});

// This will set light / dark mode on load...
initializeTheme();
