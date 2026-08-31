import { CtaBand } from '@/marketing/cta-band';
import { Float, HeroScene, PanelRow } from '@/marketing/hero-banner';
import { Icon, type IconName } from '@/marketing/icons';
import MarketingLayout from '@/marketing/marketing-layout';

const PRINCIPLES: { icon: IconName; color: string; title: string; body: string }[] = [
    {
        icon: 'shield',
        color: 'c-teal',
        title: 'No number without a record behind it',
        body: 'Simulated data is labeled simulated. Conversion math cannot exceed reality. AI-visibility claims ship with the stored answer as evidence. If the platform cannot prove it, the platform does not show it.',
    },
    {
        icon: 'users',
        color: 'c-coral',
        title: 'Not a generic CRM with an MSP landing page',
        body: 'Lifecycle stages, MRR-carrying deals, long trust-driven sales cycles, PSA-adjacent positioning — the product is shaped around how managed services are actually bought and sold in the US market.',
    },
    {
        icon: 'gauge',
        color: 'c-navy',
        title: 'The whole funnel, not another point tool',
        body: 'Visitor tracking, CRM, campaigns, funnels, SEO and AI visibility, booking, and attribution live in one workspace — so "which marketing made us money" is one query, not a quarterly archaeology project.',
    },
    {
        icon: 'receipt',
        color: 'c-amber',
        title: 'In and out as CSV, always',
        body: 'Import your existing CRM, export everything whenever you like. Vendor lock-in is not a retention strategy we are willing to use.',
    },
];

export default function About() {
    return (
        <MarketingLayout title="About Piotrack" path="/about">
            <section className="sub-hero">
                <div className="wrap sub-hero-grid">
                    <div>
                        <span className="eyebrow-chip">About us</span>
                        <h1>
                            The growth operating system <span className="accent">for MSPs.</span>
                        </h1>
                        <p className="lead">
                            Piotrack exists because MSP marketing runs on tools built for someone else — generic CRMs, e-commerce email platforms,
                            agency dashboards full of numbers nobody trusts.
                        </p>
                        <div className="sub-chips">
                            <span>HONESTY FIRST</span>
                            <span>MSP-ONLY</span>
                            <span>NO LOCK-IN</span>
                        </div>
                    </div>
                    <HeroScene label="piotrack · principles" floats={<Float pos="f1" icon="trend" text="Measured to revenue" sub="Not to clicks" />}>
                        <div className="rows">
                            <PanelRow icon="shield" color="teal" text="Every number → a real record" sub="Traceable by design" />
                            <PanelRow icon="sparkle" color="coral" text="Simulated data says simulated" sub="Right in the interface" />
                            <PanelRow icon="receipt" color="amber" text="CSV in, CSV out" sub="Your data is never locked in" />
                            <PanelRow icon="users" color="navy" text="Built only for MSPs" sub="MRR deals, long trust cycles" />
                        </div>
                    </HeroScene>
                </div>
            </section>

            <section className="sub-section">
                <div className="wrap">
                    <div className="honesty reveal">
                        <h2>Why we built it</h2>
                        <p>
                            Managed service providers sell trust on long cycles, price in monthly recurring revenue, and win or lose on whether buyers
                            can find them — increasingly by asking an AI assistant, not just Google. None of the tools MSPs inherited were built for
                            that reality. So growth becomes a duct-taped stack: a CRM here, a form builder there, a rank tracker somewhere else, and a
                            spreadsheet trying to hold it together.
                        </p>
                        <p>
                            Piotrack puts the whole journey — anonymous visitor to attributed MRR — in one system, and holds every number in it to a
                            single rule: <strong>it must be traceable to something real.</strong>
                        </p>
                    </div>
                </div>
            </section>

            <section className="sub-section">
                <div className="wrap">
                    <div className="sec-head center reveal">
                        <span className="eyebrow">Principles</span>
                        <h2>What we believe</h2>
                        <p>Four commitments the product is built to enforce — not just a values page.</p>
                    </div>
                    <div className="sub-grid">
                        {PRINCIPLES.map((principle) => (
                            <div className="card reveal" key={principle.title}>
                                <div className={`ico ${principle.color}`}>
                                    <Icon name={principle.icon} />
                                </div>
                                <h3>{principle.title}</h3>
                                <p>{principle.body}</p>
                            </div>
                        ))}
                    </div>
                </div>
            </section>

            <CtaBand
                title="Judge us by the product."
                body="Fourteen days on the Growth plan, no credit card — every claim on this page is testable in your own workspace."
                primaryLabel="Try Piotrack free"
                ghostLabel="Get in touch"
                ghostHref="/contact"
            />
        </MarketingLayout>
    );
}
