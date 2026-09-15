import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import { FlashMessage } from '@/components/flash-message';
import { ImpersonationBanner } from '@/components/impersonation-banner';
import { type BreadcrumbItem } from '@/types';

export default function AppSidebarLayout({ children, breadcrumbs = [] }: { children: React.ReactNode; breadcrumbs?: BreadcrumbItem[] }) {
    return (
        <AppShell variant="sidebar">
            {/* Keyboard users skip the 60-odd sidebar links in one keystroke. */}
            <a
                href="#main-content"
                className="bg-background text-foreground focus-visible:ring-ring fixed top-2 left-2 z-50 -translate-y-20 rounded-md border px-3 py-2 text-sm font-medium shadow transition-transform focus:translate-y-0 focus-visible:ring-2 motion-reduce:transition-none"
            >
                Skip to content
            </a>
            <AppSidebar />
            <AppContent variant="sidebar" id="main-content" tabIndex={-1} className="focus:outline-none">
                {/* Above everything, including the header: a borrowed session must never scroll out of sight. */}
                <ImpersonationBanner />
                <AppSidebarHeader breadcrumbs={breadcrumbs} />
                <FlashMessage />
                {children}
            </AppContent>
        </AppShell>
    );
}
