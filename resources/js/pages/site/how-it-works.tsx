import { CtaBand } from '@/marketing/cta-band';
import { Float, HeroScene, PanelRow } from '@/marketing/hero-banner';
import MarketingLayout from '@/marketing/marketing-layout';

const STEPS = [
    {
        title: 'Install a one-line snippet',
        body: 'Copy one script tag onto your website. From that moment Piotrack records every visit and session first-party — no third-party pixels, no cookie soup.',
    },
    {
        title: 'Capture and identify visitors',
        body: 'Landing pages, forms, and the chat builder turn anonymous traffic into named leads. The first-touch source is stamped once and never overwritten, so you always know what started the relationship.',
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
        body: 'When a deal closes, Piotrack traces it back through the funnel to the campaign, page, or keyword that started it. Marketing spend on one side, attributed MRR on the other.',
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
                <div className="wrap sub-hero-grid">
                    <div>
                        <span className="eyebrow-chip">How it works</span>
                        <h1>
                            From anonymous visitor to <span className="accent">attributed revenue.</span>
                        </h1>
                        <p className="lead">
                            Piotrack follows the whole journey a real MSP client takes — and measures every step of it, so you stop guessing which
                            marketing works.
                        </p>
                        <div className="sub-chips">
                            <span>TRACK</span>
                            <span>CAPTURE</span>
                            <span>QUALIFY</span>
                            <span>BOOK</span>
                            <span>CLOSE</span>
                            <span>ATTRIBUTE</span>
                        </div>
                    </div>
                    <HeroScene
                        label="piotrack · one journey"
                        floats={<Float pos="f1" icon="trend" text="Attributed to SEO" sub="First touch · organic search" />}
                    >
                        <div className="rows">
                            <PanelRow icon="radar" color="teal" text="Visitor identified" sub="Reading your pricing page" amount="intent 42" />
                            <PanelRow icon="users" color="coral" text="Lead captured" sub="Assessment form submitted" />
                            <PanelRow icon="calendar" color="amber" text="Meeting booked" sub="Tuesday · 10:00 AM" />
                            <PanelRow icon="receipt" color="navy" text="Closed won" sub="Managed services contract" amount="+$4.5K MRR" />
                        </div>
                    </HeroScene>
                </div>
            </section>

            <section className="sub-section">
                <div className="wrap">
                    <div className="timeline">
                        {STEPS.map((step, index) => (
                            <div className="tstep reveal" key={step.title}>
                                <div className="n">{String(index + 1).padStart(2, '0')}</div>
                                <div>
                                    <h3>{step.title}</h3>
                                    <p>{step.body}</p>
                                </div>
                            </div>
                        ))}
                    </div>
                </div>
            </section>

            <CtaBand
                title="Watch it run on your own traffic."
                body="The snippet takes two minutes to install, and the trial is 14 days on the Growth plan — no credit card."
                primaryLabel="Start free"
                ghostLabel="What you can prove"
                ghostHref="/results"
            />
        </MarketingLayout>
    );
}
