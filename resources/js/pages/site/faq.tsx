import { CtaBand } from '@/marketing/cta-band';
import MarketingLayout from '@/marketing/marketing-layout';

interface FaqProps {
    /** Q&A pairs from MarketingSiteController::faqItems() — the same source that renders the FAQPage JSON-LD. */
    items: { q: string; a: string }[];
}

export default function Faq({ items }: FaqProps) {
    return (
        <MarketingLayout title="Piotrack FAQ" path="/faq">
            <section className="sub-hero">
                <div className="wrap">
                    <span className="eyebrow-chip">FAQ</span>
                    <h1>
                        Frequently asked <span className="accent">questions.</span>
                    </h1>
                    <p className="lead">
                        Pricing, the free trial, tracking, AI visibility, and how Piotrack fits alongside the PSA and RMM you already run.
                    </p>
                </div>
            </section>

            <section className="sub-section">
                <div className="wrap">
                    <div className="faq-list reveal">
                        {items.map((item, index) => (
                            <details key={item.q} open={index === 0}>
                                <summary>{item.q}</summary>
                                <p>{item.a}</p>
                            </details>
                        ))}
                    </div>
                </div>
            </section>

            <CtaBand
                title="Question answered? Prove it to yourself."
                body="Fourteen days on the Growth plan, no credit card — or send us the question the FAQ missed."
                primaryLabel="Start your free trial"
                ghostLabel="Ask us directly"
                ghostHref="/contact"
            />
        </MarketingLayout>
    );
}
