import { CtaBand } from '@/marketing/cta-band';
import { Float, HeroScene, PanelRow } from '@/marketing/hero-banner';
import MarketingLayout from '@/marketing/marketing-layout';

interface FaqProps {
    /** Q&A pairs from MarketingSiteController::faqItems() — the same source that renders the FAQPage JSON-LD. */
    items: { q: string; a: string }[];
}

export default function Faq({ items }: FaqProps) {
    return (
        <MarketingLayout title="Piotrack FAQ" path="/faq">
            <section className="sub-hero">
                <div className="wrap sub-hero-grid">
                    <div>
                        <span className="eyebrow-chip">FAQ</span>
                        <h1>
                            Frequently asked <span className="accent">questions.</span>
                        </h1>
                        <p className="lead">
                            Pricing, the free trial, tracking, AI visibility, and how Piotrack fits alongside the PSA and RMM you already run.
                        </p>
                        <div className="sub-chips">
                            <span>PRICING</span>
                            <span>TRIAL</span>
                            <span>TRACKING</span>
                            <span>SECURITY</span>
                        </div>
                    </div>
                    <HeroScene
                        label="piotrack · quick answers"
                        floats={<Float pos="f1" icon="chat" text="Still stuck?" sub="Ask us on the contact page" />}
                    >
                        <div className="rows">
                            <PanelRow icon="calendar" color="teal" text="Is there a free trial?" amount="14 days" />
                            <PanelRow icon="receipt" color="coral" text="What does it cost?" amount="from $49/mo" />
                            <PanelRow icon="shield" color="amber" text="Is my data isolated?" amount="per-tenant" />
                            <PanelRow icon="users" color="navy" text="Does it replace my PSA?" amount="no — beside it" />
                        </div>
                    </HeroScene>
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
