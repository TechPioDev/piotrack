import MarketingLayout from '@/marketing/marketing-layout';
import { Link } from '@inertiajs/react';

const STEPS = [
    {
        title: 'Install a one-line snippet',
        body: 'Copy one script tag onto your website. From that moment Piotrack records every visit and session first-party — no third-party pixels, no cookie soup.',
    },
    {
        title: 'Capture and identify visitors',
        body: 'Landing pages, forms, and the chat builder turn anonymous traffic into named leads. The first-touch source is stamped once and never overwritten, so you always know what actually started the relationship.',
    },
    {
        title: 'Qualify with intent, not gut feel',
        body: 'Visitors are scored by the pages they read — pricing and service pages weigh more than the blog. Lifecycle stages move leads forward, and daily sweeps flag the ones ready for sales.',
    },
    {
        title: 'Book meetings automatically',
        body: 'Qualified prospects pick a slot from your real availability. No email ping-pong, no lost momentum — a first visit can become a booked meeting in the same session.',
    },
    {
        title: 'Close deals in an MSP-shaped pipeline',
        body: 'Deals carry monthly recurring revenue, not just one-off amounts. Your team works stages built for how IT services actually sell: long cycles, trust-driven buying, contract renewals.',
    },
    {
        title: 'Attribute revenue back to the source',
        body: 'When a deal closes, Piotrack traces it back through the funnel to the campaign, page, or keyword that started it. You see marketing spend on one side and attributed MRR on the other.',
    },
    {
        title: 'Watch your visibility — including AI',
        body: 'Daily rank tracking, competitor head-to-head, and AI-visibility checks tell you whether buyers can still find you tomorrow — with alerts the day something moves against you.',
    },
];

export default function HowItWorks() {
    return (
        <MarketingLayout title="How Piotrack Works" path="/how-it-works">
            <section className="sub-hero">
                <div className="wrap">
                    <span className="eyebrow">How it works</span>
                    <h1>From anonymous visitor to attributed revenue, in seven steps.</h1>
                    <p className="lead">
                        Piotrack follows the whole journey a real MSP client takes — and measures every step of it, so you stop guessing which
                        marketing works.
                    </p>
                </div>
            </section>

            <section className="sub-section">
                <div className="wrap">
                    <ol className="step-list">
                        {STEPS.map((step) => (
                            <li key={step.title}>
                                <div>
                                    <h3>{step.title}</h3>
                                    <p>{step.body}</p>
                                </div>
                            </li>
                        ))}
                    </ol>

                    <div className="sub-cta">
                        <Link className="btn btn-primary" href={route('register')}>
                            Start free — no credit card
                        </Link>
                        <Link className="btn btn-ghost" href="/results">
                            What you can prove
                        </Link>
                    </div>
                </div>
            </section>
        </MarketingLayout>
    );
}
