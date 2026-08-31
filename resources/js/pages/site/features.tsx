import MarketingLayout from '@/marketing/marketing-layout';
import { Link } from '@inertiajs/react';

const CLUSTERS = [
    {
        tag: 'Command center',
        title: 'One dashboard, measured to revenue',
        body: 'The Growth Command Center rolls your whole funnel into one screen: pipeline value, bookings, campaign performance, search rankings, and AI visibility — with a growth score that tells you exactly where to act next.',
    },
    {
        tag: 'CRM & pipeline',
        title: 'An MSP-shaped CRM',
        body: 'Companies, contacts, leads, and deals with lifecycle stages built for IT services sales. Filter, sort, save views, act in bulk, and import or export everything as CSV — your data is never locked in.',
    },
    {
        tag: 'Visitor intelligence',
        title: 'Know who is on your website',
        body: 'A one-line tracking snippet records visits, sessions, and intent — scoring visitors by the pages they read. When someone submits a form or books a meeting, their anonymous history links to the new lead automatically.',
    },
    {
        tag: 'Marketing automation',
        title: 'Campaigns, email, landing pages, forms',
        body: 'Build landing pages and lead-capture forms, run email campaigns with open and click tracking, and keep every touch attributed back to its source — first-touch UTMs are captured once and never overwritten.',
    },
    {
        tag: 'Funnels',
        title: 'Funnels built from real assets',
        body: 'Attach the actual pages, forms, campaigns, and booking links that make up each stage. Conversion is computed cumulatively from real records — a funnel in Piotrack can never show you a number it cannot back up.',
    },
    {
        tag: 'SEO & rankings',
        title: 'Daily rank tracking with head-to-head',
        body: 'Track your keywords daily, compare positions against named competitors keyword by keyword, and get alerted the day a competitor outranks you or a ranking drops.',
    },
    {
        tag: 'AI visibility',
        title: 'Are AI assistants recommending you?',
        body: 'Buyers now ask ChatGPT and Gemini for MSP recommendations. Piotrack checks whether you appear in those answers and stores the answer excerpts as evidence — live results when engine keys are connected, clearly labeled simulation when they are not.',
    },
    {
        tag: 'Chat & booking',
        title: 'Capture and book while you sleep',
        body: 'A visual conversation builder qualifies website visitors and hands off to a real booking flow with availability, so a good-fit prospect can go from first visit to a meeting on your calendar without waiting for office hours.',
    },
    {
        tag: 'Alerts',
        title: 'The platform watches for you',
        body: 'Daily sweeps flag qualified-lead promotions, new bookings, usage limits, ranking drops, and AI-visibility swings — deduplicated so you get one useful notification, not fifty.',
    },
    {
        tag: 'Teams & security',
        title: 'Roles, audit log, tenant isolation',
        body: 'Role-based permissions on every endpoint, a full audit log of who did what, and strict per-organization data isolation verified by automated tests on every module.',
    },
];

export default function Features() {
    return (
        <MarketingLayout title="MSP Marketing Software Features" path="/features">
            <section className="sub-hero">
                <div className="wrap">
                    <span className="eyebrow">Features</span>
                    <h1>Every growth tool an MSP needs. One platform, one number that matters.</h1>
                    <p className="lead">
                        Piotrack replaces the duct-taped stack of CRM, email tool, form builder, rank tracker, and spreadsheets with one system where
                        every activity is measured to closed revenue.
                    </p>
                </div>
            </section>

            <section className="sub-section">
                <div className="wrap">
                    <div className="sub-grid">
                        {CLUSTERS.map((cluster) => (
                            <div className="sub-card" key={cluster.tag}>
                                <span className="tag">{cluster.tag}</span>
                                <h3>{cluster.title}</h3>
                                <p>{cluster.body}</p>
                            </div>
                        ))}
                    </div>

                    <div className="sub-cta">
                        <Link className="btn btn-primary" href={route('register')}>
                            Start your 14-day free trial
                        </Link>
                        <Link className="btn btn-ghost" href="/how-it-works">
                            See how it works
                        </Link>
                    </div>
                </div>
            </section>
        </MarketingLayout>
    );
}
