/**
 * The page-title heading. Used exactly once per page (57 pages), so it renders
 * a real h1 at the system's 24px title size — the Phase 1 audit found these
 * pages exposing an h2 as their top heading, which flattened the document
 * outline for assistive tech and made titles inconsistent with PageHeader.
 */
export default function Heading({ title, description }: { title: string; description?: string }) {
    return (
        <div className="mb-8 space-y-0.5">
            <h1 className="text-2xl font-semibold tracking-tight text-balance">{title}</h1>
            {description && <p className="text-muted-foreground text-sm">{description}</p>}
        </div>
    );
}
