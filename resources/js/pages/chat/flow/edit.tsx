import { ConversationBuilder, type Validation } from '@/components/chat-flow/conversation-builder';
import type { Template } from '@/components/chat-flow/template-gallery';
import AppLayout from '@/layouts/app-layout';
import type { Flow } from '@/lib/flow-tree';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';

type WidgetRef = { id: number; name: string; status: string };

/** The conversation builder page: the builder itself lives in components/chat-flow. */
export default function FlowBuilderPage({
    widget,
    widgets,
    flow,
    validation,
    templates,
    assignees,
}: {
    widget: WidgetRef;
    widgets?: WidgetRef[];
    flow: Flow;
    validation: Validation;
    templates: Template[];
    assignees: { id: number; name: string }[];
}) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Website Chat', href: '/chat' },
        { title: 'Widgets', href: '/chat/widgets' },
        { title: widget.name, href: `/chat/widgets/${widget.id}/flow` },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`${widget.name} — conversation`} />
            <ConversationBuilder widget={widget} widgets={widgets} flow={flow} validation={validation} templates={templates} assignees={assignees} />
        </AppLayout>
    );
}
