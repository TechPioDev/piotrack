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
                <div className="wrap">
                    <span className="eyebrow">Contact</span>
                    <h1>Talk to the team building Piotrack.</h1>
                    <p className="lead">
                        Questions about the platform, plans, a demo, or whether Piotrack fits your MSP — send a message. We read every one and reply
                        by email.
                    </p>
                </div>
            </section>

            <section className="sub-section">
                <div className="wrap">
                    {submitted && flash?.status ? (
                        <div className="sub-card" style={{ maxWidth: 560 }}>
                            <span className="tag">Message sent</span>
                            <h3>{flash.status}</h3>
                            <p>
                                In the meantime, the <Link href="/faq">FAQ</Link> answers the most common questions about pricing, the trial, and how
                                the platform works.
                            </p>
                        </div>
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
            </section>
        </MarketingLayout>
    );
}
