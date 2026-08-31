import { Link } from '@inertiajs/react';

interface CtaBandProps {
    title: string;
    body: string;
    primaryLabel?: string;
    ghostLabel?: string;
    ghostHref?: string;
}

/**
 * The dark gradient call-to-action band that closes every marketing page —
 * same treatment as the landing page's final section.
 */
export function CtaBand({ title, body, primaryLabel = 'Start your 14-day free trial', ghostLabel, ghostHref }: CtaBandProps) {
    return (
        <section className="sub-section reveal">
            <div className="wrap">
                <div className="cta-band">
                    <h2>{title}</h2>
                    <p>{body}</p>
                    <div style={{ display: 'flex', gap: 14, justifyContent: 'center', flexWrap: 'wrap' }}>
                        <Link className="btn btn-primary" href={route('register')}>
                            {primaryLabel}
                        </Link>
                        {ghostLabel && ghostHref && (
                            <Link className="btn btn-ghost" href={ghostHref}>
                                {ghostLabel}
                            </Link>
                        )}
                    </div>
                </div>
            </div>
        </section>
    );
}
