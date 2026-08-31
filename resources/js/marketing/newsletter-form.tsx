import { type SharedData } from '@/types';
import { useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';

export function NewsletterForm() {
    const { flash } = usePage<SharedData>().props;
    const form = useForm({ email: '', website: '' });
    const [submitted, setSubmitted] = useState(false);

    if (submitted && flash?.status) {
        return <p className="news-done">{flash.status}</p>;
    }

    return (
        <form
            className="news-form"
            onSubmit={(e) => {
                e.preventDefault();
                form.post(route('newsletter.subscribe'), {
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
            <div className="news-row">
                <input
                    type="email"
                    required
                    placeholder="you@yourmsp.com"
                    aria-label="Email address for the newsletter"
                    value={form.data.email}
                    onChange={(e) => form.setData('email', e.target.value)}
                />
                <button type="submit" disabled={form.processing}>
                    {form.processing ? 'Subscribing…' : 'Subscribe'}
                </button>
            </div>
            {form.errors.email && <p className="news-error">{form.errors.email}</p>}
        </form>
    );
}
