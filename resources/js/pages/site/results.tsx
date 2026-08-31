import MarketingLayout from '@/marketing/marketing-layout';
import { Link } from '@inertiajs/react';

const PROOFS = [
    {
        tag: 'Attribution',
        title: 'Attributed MRR, not vanity clicks',
        body: 'Every closed deal traces back to the campaign, page, or keyword that started it. Your marketing report ends in dollars of monthly recurring revenue — the only number an MSP owner actually runs the business on.',
    },
    {
        tag: 'Funnels',
        title: 'Conversion that cannot lie',
        body: 'Funnel stages are built from real assets and real records, and conversion is computed cumulatively — the math structurally cannot show more than 100% or count the same lead twice.',
    },
    {
        tag: 'Rankings',
        title: 'A daily ranking history you own',
        body: 'Positions are recorded every day, per keyword, next to your named competitors. When you claim "we moved from page three to page one," the chart is right there.',
    },
    {
        tag: 'AI visibility',
        title: 'Evidence, not vibes',
        body: 'When Piotrack says an AI assistant recommended you, it stores the actual answer excerpt as evidence you can read. When engines are not connected, the module says "simulated" — in the interface, in plain sight.',
    },
    {
        tag: 'Alerts',
        title: 'You hear about changes the same day',
        body: 'Ranking drops, AI-visibility swings, competitor overtakes, new bookings, and qualified-lead promotions land as one deduplicated daily digest — so results never degrade silently.',
    },
    {
        tag: 'Audit trail',
        title: 'Every number is traceable',
        body: 'Behind each metric is a record you can click into: the deal, the visit, the ranking snapshot, the stored answer. Nothing on a Piotrack dashboard is an estimate dressed up as a fact.',
    },
];

export default function Results() {
    return (
        <MarketingLayout title="MSP Marketing Results You Can Prove" path="/results">
            <section className="sub-hero">
                <div className="wrap">
                    <span className="eyebrow">Results</span>
                    <h1>We will not show you numbers we cannot prove. Neither should your marketing.</h1>
                    <p className="lead">
                        Most marketing tools report activity — sends, clicks, impressions. Piotrack is built around a stricter standard: every result
                        on your dashboard is traceable to a real record.
                    </p>
                </div>
            </section>

            <section className="sub-section">
                <div className="wrap">
                    <h2>What Piotrack proves for you</h2>
                    <div className="sub-grid">
                        {PROOFS.map((proof) => (
                            <div className="sub-card" key={proof.tag}>
                                <span className="tag">{proof.tag}</span>
                                <h3>{proof.title}</h3>
                                <p>{proof.body}</p>
                            </div>
                        ))}
                    </div>
                </div>
            </section>

            <section className="sub-section">
                <div className="wrap prose">
                    <h2>Where are the client testimonials?</h2>
                    <p>
                        Coming — from real clients, with real numbers, when they exist. Piotrack is a young product, and we hold our own marketing to
                        the same standard the product enforces: no invented case studies, no stock-photo customers, no percentages nobody can trace.
                        As founding customers publish results, they will appear here with the evidence behind them.
                    </p>
                    <p>
                        Until then, the honest offer is simple: try the platform on your own pipeline for 14 days and let your own dashboard make the
                        case.
                    </p>

                    <div className="sub-cta">
                        <Link className="btn btn-primary" href={route('register')}>
                            Become a founding customer
                        </Link>
                        <Link className="btn btn-ghost" href="/contact">
                            Talk to us first
                        </Link>
                    </div>
                </div>
            </section>
        </MarketingLayout>
    );
}
