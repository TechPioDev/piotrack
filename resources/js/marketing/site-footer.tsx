import { NewsletterForm } from '@/marketing/newsletter-form';
import { Link } from '@inertiajs/react';

/**
 * The marketing-site footer (MSITE): brand, product/resource links, and the
 * real newsletter list — shared by the landing page and every subpage.
 */
export function SiteFooter() {
    return (
        <footer className="foot">
            <div className="wrap">
                <div className="foot-grid">
                    <div className="foot-brand">
                        <Link className="brand" href={route('home')} style={{ fontSize: 18 }}>
                            <span className="mark" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="#fff" strokeWidth="2.4" strokeLinecap="round" strokeLinejoin="round">
                                    <path d="M3 17l5-5 4 3 8-9" />
                                    <path d="M15 6h5v5" />
                                </svg>
                            </span>
                            Piotrack
                        </Link>
                        <p>
                            The growth operating system for managed service providers — marketing, sales, attribution and AI visibility, measured to
                            revenue in one place.
                        </p>
                    </div>

                    <nav className="foot-col" aria-label="Product">
                        <h4>Product</h4>
                        <Link href="/features">Features</Link>
                        <Link href="/how-it-works">How it works</Link>
                        <Link href="/results">Results</Link>
                        <Link href={route('login')}>Log in</Link>
                        <Link href={route('register')}>Start free</Link>
                    </nav>

                    <nav className="foot-col" aria-label="Company">
                        <h4>Company</h4>
                        <Link href="/about">About us</Link>
                        <Link href="/contact">Contact</Link>
                        <Link href="/faq">FAQ</Link>
                        <a href="/piotrack-user-guide.pdf" target="_blank" rel="noopener noreferrer">
                            User Guide (PDF)
                        </a>
                    </nav>

                    <div className="foot-col foot-news">
                        <h4>MSP growth insights</h4>
                        <p>Practical notes on pipeline, SEO and AI visibility for MSPs. No spam, unsubscribe anytime.</p>
                        <NewsletterForm />
                    </div>
                </div>

                <div className="foot-bottom">
                    <span className="mono" style={{ fontSize: 12.5 }}>
                        © 2026 Piotrack
                    </span>
                    <span>Built for MSPs. Measured to revenue.</span>
                </div>
            </div>
        </footer>
    );
}
