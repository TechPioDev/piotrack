import MarketingLayout from '@/marketing/marketing-layout';
import { Link } from '@inertiajs/react';

const PRINCIPLES = [
    {
        tag: 'Honesty first',
        title: 'No number without a record behind it',
        body: 'Simulated data is labeled simulated. Conversion math cannot exceed reality. AI-visibility claims ship with the stored answer as evidence. If the platform cannot prove it, the platform does not show it.',
    },
    {
        tag: 'Built for MSPs',
        title: 'Not a generic CRM with an MSP landing page',
        body: 'Lifecycle stages, MRR-carrying deals, long trust-driven sales cycles, PSA-adjacent positioning — the product is shaped around how managed services are actually bought and sold in the US market.',
    },
    {
        tag: 'One system',
        title: 'The whole funnel, not another point tool',
        body: 'Visitor tracking, CRM, campaigns, funnels, SEO and AI visibility, booking, and attribution live in one tenant-isolated workspace — so "which marketing made us money" is finally one query, not a quarterly archaeology project.',
    },
    {
        tag: 'Your data',
        title: 'In and out as CSV, always',
        body: 'Import your existing CRM, export everything whenever you like. Vendor lock-in is not a retention strategy we are willing to use.',
    },
];

export default function About() {
    return (
        <MarketingLayout title="About Piotrack" path="/about">
            <section className="sub-hero">
                <div className="wrap">
                    <span className="eyebrow">About us</span>
                    <h1>The growth operating system for managed service providers.</h1>
                    <p className="lead">
                        Piotrack exists because MSP marketing runs on tools built for someone else — generic CRMs, e-commerce email platforms, agency
                        dashboards full of numbers nobody trusts.
                    </p>
                </div>
            </section>

            <section className="sub-section">
                <div className="wrap prose">
                    <h2>Why we built it</h2>
                    <p>
                        Managed service providers sell trust on long cycles, price in monthly recurring revenue, and win or lose on whether buyers can
                        find them — increasingly by asking an AI assistant, not just Google. None of the tools MSPs inherited were built for that
                        reality. So growth becomes a duct-taped stack: a CRM here, a form builder there, a rank tracker somewhere else, and a
                        spreadsheet trying to hold it together.
                    </p>
                    <p>
                        Piotrack puts the whole journey — anonymous visitor to attributed MRR — in one system, and holds every number in it to a
                        single rule: <strong>it must be traceable to something real.</strong>
                    </p>
                </div>
            </section>

            <section className="sub-section">
                <div className="wrap">
                    <h2>What we believe</h2>
                    <div className="sub-grid">
                        {PRINCIPLES.map((principle) => (
                            <div className="sub-card" key={principle.tag}>
                                <span className="tag">{principle.tag}</span>
                                <h3>{principle.title}</h3>
                                <p>{principle.body}</p>
                            </div>
                        ))}
                    </div>

                    <div className="sub-cta">
                        <Link className="btn btn-primary" href={route('register')}>
                            Try Piotrack free for 14 days
                        </Link>
                        <Link className="btn btn-ghost" href="/contact">
                            Get in touch
                        </Link>
                    </div>
                </div>
            </section>
        </MarketingLayout>
    );
}
