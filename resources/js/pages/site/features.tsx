import { CtaBand } from '@/marketing/cta-band';
import { Float, HeroScene, Kpi, PanelChart } from '@/marketing/hero-banner';
import { Icon, type IconName } from '@/marketing/icons';
import MarketingLayout from '@/marketing/marketing-layout';

const CLUSTERS: { icon: IconName; color: string; title: string; body: string }[] = [
    {
        icon: 'gauge',
        color: 'c-teal',
        title: 'Growth Command Center',
        body: 'Your whole funnel on one screen: pipeline value, bookings, campaign performance, search rankings, and AI visibility — with a growth score that tells you exactly where to act next.',
    },
    {
        icon: 'users',
        color: 'c-coral',
        title: 'An MSP-shaped CRM',
        body: 'Companies, contacts, leads, and deals with lifecycle stages built for IT services sales. Filter, sort, save views, act in bulk — and import or export everything as CSV.',
    },
    {
        icon: 'radar',
        color: 'c-navy',
        title: 'Visitor intelligence',
        body: 'A one-line snippet records visits, sessions, and intent — scoring visitors by the pages they read. When someone converts, their anonymous history links to the new lead automatically.',
    },
    {
        icon: 'mail',
        color: 'c-amber',
        title: 'Campaigns, email, pages, forms',
        body: 'Landing pages, lead-capture forms, and email campaigns with open and click tracking — every touch attributed to its source, with first-touch UTMs captured once and never overwritten.',
    },
    {
        icon: 'funnel',
        color: 'c-cyan',
        title: 'Funnels built from real assets',
        body: 'Attach the actual pages, forms, campaigns, and booking links behind each stage. Conversion is computed cumulatively from real records — never a number the platform cannot back up.',
    },
    {
        icon: 'trend',
        color: 'c-teal',
        title: 'Daily rank tracking',
        body: 'Track your keywords every day, compare positions against named competitors keyword by keyword, and get alerted the day a competitor outranks you or a ranking drops.',
    },
    {
        icon: 'sparkle',
        color: 'c-pink',
        title: 'AI visibility',
        body: 'Buyers now ask ChatGPT and Gemini for MSP recommendations. Piotrack checks whether you appear in those answers and stores the excerpts as evidence — live or clearly labeled simulation.',
    },
    {
        icon: 'chat',
        color: 'c-coral',
        title: 'Chat & booking',
        body: 'A visual conversation builder qualifies visitors and hands off to a real booking flow with availability — first visit to booked meeting without waiting for office hours.',
    },
    {
        icon: 'bell',
        color: 'c-amber',
        title: 'Alerts that watch for you',
        body: 'Daily sweeps flag qualified-lead promotions, new bookings, usage limits, ranking drops, and AI-visibility swings — deduplicated into one useful digest, not fifty pings.',
    },
    {
        icon: 'shield',
        color: 'c-navy',
        title: 'Teams, roles & isolation',
        body: 'Role-based permissions on every endpoint, a full audit log of who did what, and strict per-organization data isolation verified by automated tests on every module.',
    },
];

export default function Features() {
    return (
        <MarketingLayout title="MSP Marketing Software Features" path="/features">
            <section className="sub-hero">
                <div className="wrap sub-hero-grid">
                    <div>
                        <span className="eyebrow-chip">Features</span>
                        <h1>
                            Every growth tool an MSP needs. <span className="accent">One platform.</span>
                        </h1>
                        <p className="lead">
                            Piotrack replaces the duct-taped stack of CRM, email tool, form builder, rank tracker, and spreadsheets with one system
                            where every activity is measured to closed revenue.
                        </p>
                        <div className="sub-chips">
                            <span>CRM</span>
                            <span>AUTOMATION</span>
                            <span>SEO + AI</span>
                            <span>BOOKING</span>
                            <span>ATTRIBUTION</span>
                        </div>
                    </div>
                    <HeroScene
                        label="piotrack · command center"
                        floats={
                            <>
                                <Float pos="f1" icon="sparkle" text="AI visible" sub="ChatGPT recommends you" />
                                <Float pos="f2" icon="receipt" text="+$4,500 MRR" sub="Closed won · attributed" />
                            </>
                        }
                    >
                        <div className="kpi-row">
                            <Kpi label="Pipeline" value="$486K" tone="teal" />
                            <Kpi label="Bookings" value="24" />
                            <Kpi label="ROAS" value="4.57x" tone="coral" />
                        </div>
                        <PanelChart gradientId="featAreaFill" />
                    </HeroScene>
                </div>
            </section>

            <section className="sub-section reveal">
                <div className="wrap">
                    <div className="sub-band">
                        <div>
                            <div className="b-num">10 modules</div>
                            <div className="b-cap">in one workspace</div>
                        </div>
                        <div>
                            <div className="b-num">1 dashboard</div>
                            <div className="b-cap">measured to revenue</div>
                        </div>
                        <div>
                            <div className="b-num">14 days</div>
                            <div className="b-cap">free trial, no credit card</div>
                        </div>
                        <div>
                            <div className="b-num">$49/mo</div>
                            <div className="b-cap">starting price</div>
                        </div>
                    </div>
                </div>
            </section>

            <section className="sub-section">
                <div className="wrap">
                    <div className="sec-head center reveal">
                        <span className="eyebrow">The platform</span>
                        <h2>Built end to end for MSP growth</h2>
                        <p>Ten modules that share one data model — so nothing has to be exported, reconciled, or guessed.</p>
                    </div>
                    <div className="sub-grid">
                        {CLUSTERS.map((cluster) => (
                            <div className="card reveal" key={cluster.title}>
                                <div className={`ico ${cluster.color}`}>
                                    <Icon name={cluster.icon} />
                                </div>
                                <h3>{cluster.title}</h3>
                                <p>{cluster.body}</p>
                            </div>
                        ))}
                    </div>
                </div>
            </section>

            <CtaBand
                title="See all of it on your own pipeline."
                body="Every module above is included in the 14-day Growth trial — no credit card, no sales call required."
                ghostLabel="How it works"
                ghostHref="/how-it-works"
            />
        </MarketingLayout>
    );
}
