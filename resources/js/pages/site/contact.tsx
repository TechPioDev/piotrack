import { Float, HeroScene, PanelRow } from '@/marketing/hero-banner';
import { Icon } from '@/marketing/icons';
import MarketingLayout from '@/marketing/marketing-layout';
import { type SharedData } from '@/types';
import { Link, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';

export default function Contact() {
    const { flash } = usePage<SharedData>().props;
    const form = useForm({ name: '', email: '', company: '', message: '', website: '' });
    const [submitted, setSubmitted] = useState(false);

    return (
        <MarketingLayout title="Contact Piotrack" path="/contact">
            <section className="sub-hero">
                <div className="wrap sub-hero-grid">
                    <div>
                        <span className="eyebrow-chip">Contact</span>
                        <h1>
                            Talk to the team <span className="accent">building Piotrack.</span>
                        </h1>
                        <p className="lead">
                            Questions about the platform, plans, a demo, or whether Piotrack fits your MSP — send a message. We read every one and
                            reply by email.
                        </p>
                        <div className="sub-chips">
                            <span>REAL HUMANS</span>
                            <span>REPLY BY EMAIL</span>
                            <span>DEMOS WELCOME</span>
                        </div>
                    </div>
                    <HeroScene label="piotrack · inbox" floats={<Float pos="f2" icon="reply" text="We reply by email" sub="Usually the same day" />}>
                        <div className="rows">
                            <PanelRow icon="chat" color="teal" text="Demo request" sub="12-person MSP · Texas" amount="new" />
                            <PanelRow icon="mail" color="coral" text="Pricing question" sub="Growth vs. Professional" amount="replied" />
                            <PanelRow icon="calendar" color="amber" text="Walkthrough booked" sub="Live on their own use case" dim />
                        </div>
                    </HeroScene>
                </div>
            </section>

            <section className="sub-section">
                <div className="wrap">
                    <div className="contact-grid">
                        <div className="contact-card reveal">
                            {submitted && flash?.status ? (
                                <>
                                    <div className="ico c-teal" style={{ marginBottom: 18 }}>
                                        <Icon name="reply" />
                                    </div>
                                    <h3 style={{ fontSize: 22 }}>{flash.status}</h3>
                                    <p style={{ marginTop: 10 }}>
                                        In the meantime, the <Link href="/faq">FAQ</Link> answers the most common questions about pricing, the trial,
                                        and how the platform works.
                                    </p>
                                </>
                            ) : (
                                <form
                                    className="contact-form"
                                    onSubmit={(e) => {
                                        e.preventDefault();
                                        form.post(route('contact.submit'), {
                                            preserveScroll: true,
                                            onSuccess: () => setSubmitted(true),
                                        });
                                    }}
                                >
                                    {/* Honeypot — humans never see it. */}
                                    <input
                                        type="text"
                                        name="website"
                                        value={form.data.website}
                                        onChange={(e) => form.setData('website', e.target.value)}
                                        tabIndex={-1}
                                        autoComplete="off"
                                        aria-hidden="true"
                                        style={{ position: 'absolute', left: -9999, width: 1, height: 1 }}
                                    />
                                    <label>
                                        Your name
                                        <input
                                            type="text"
                                            required
                                            maxLength={120}
                                            autoComplete="name"
                                            value={form.data.name}
                                            onChange={(e) => form.setData('name', e.target.value)}
                                        />
                                        {form.errors.name && <span className="news-error">{form.errors.name}</span>}
                                    </label>
                                    <label>
                                        Work email
                                        <input
                                            type="email"
                                            required
                                            maxLength={255}
                                            autoComplete="email"
                                            placeholder="you@yourmsp.com"
                                            value={form.data.email}
                                            onChange={(e) => form.setData('email', e.target.value)}
                                        />
                                        {form.errors.email && <span className="news-error">{form.errors.email}</span>}
                                    </label>
                                    <label>
                                        Company <span style={{ fontWeight: 400, opacity: 0.7 }}>(optional)</span>
                                        <input
                                            type="text"
                                            maxLength={160}
                                            autoComplete="organization"
                                            value={form.data.company}
                                            onChange={(e) => form.setData('company', e.target.value)}
                                        />
                                    </label>
                                    <label>
                                        How can we help?
                                        <textarea
                                            required
                                            maxLength={5000}
                                            value={form.data.message}
                                            onChange={(e) => form.setData('message', e.target.value)}
                                        />
                                        {form.errors.message && <span className="news-error">{form.errors.message}</span>}
                                    </label>
                                    <div>
                                        <button className="btn btn-primary" type="submit" disabled={form.processing}>
                                            {form.processing ? 'Sending…' : 'Send message'}
                                        </button>
                                    </div>
                                </form>
                            )}
                        </div>

                        <div className="aside-card reveal">
                            <div className="aside-item">
                                <div className="mini-ico">
                                    <Icon name="reply" />
                                </div>
                                <div>
                                    <h4>What happens next</h4>
                                    <p>Your message lands with the product team directly — we reply by email, usually the same day.</p>
                                </div>
                            </div>
                            <div className="aside-item">
                                <div className="mini-ico">
                                    <Icon name="calendar" />
                                </div>
                                <div>
                                    <h4>Want a walkthrough?</h4>
                                    <p>Say so in the message — we will set up a live demo on your own use case.</p>
                                </div>
                            </div>
                            <div className="aside-item">
                                <div className="mini-ico">
                                    <Icon name="sparkle" />
                                </div>
                                <div>
                                    <h4>Quick answers</h4>
                                    <p>
                                        Pricing, trial, and tracking questions are covered in the <Link href="/faq">FAQ</Link>.
                                    </p>
                                </div>
                            </div>
                            <div className="aside-item">
                                <div className="mini-ico">
                                    <Icon name="guide" />
                                </div>
                                <div>
                                    <h4>User guide</h4>
                                    <p>
                                        The full manual is available as a{' '}
                                        <a href="/piotrack-user-guide.pdf" target="_blank" rel="noopener noreferrer">
                                            PDF download
                                        </a>
                                        .
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </MarketingLayout>
    );
}
