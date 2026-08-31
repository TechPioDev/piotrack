import MarketingLayout from '@/marketing/marketing-layout';
import { Link } from '@inertiajs/react';

interface FaqProps {
    /** Q&A pairs from MarketingSiteController::faqItems() — the same source that renders the FAQPage JSON-LD. */
    items: { q: string; a: string }[];
}

export default function Faq({ items }: FaqProps) {
    return (
        <MarketingLayout title="Piotrack FAQ" path="/faq">
            <section className="sub-hero">
                <div className="wrap">
                    <span className="eyebrow">FAQ</span>
                    <h1>Frequently asked questions.</h1>
                    <p className="lead">
                        Pricing, the free trial, tracking, AI visibility, and how Piotrack fits alongside the PSA and RMM you already run.
                    </p>
                </div>
            </section>

            <section className="sub-section">
                <div className="wrap">
                    <div className="faq-list">
                        {items.map((item, index) => (
                            <details key={item.q} open={index === 0}>
                                <summary>{item.q}</summary>
                                <p>{item.a}</p>
                            </details>
                        ))}
                    </div>

                    <div className="sub-cta">
                        <Link className="btn btn-primary" href={route('register')}>
                            Start your free trial
                        </Link>
                        <Link className="btn btn-ghost" href="/contact">
                            Still have a question?
                        </Link>
                    </div>
                </div>
            </section>
        </MarketingLayout>
    );
}
