import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import AuthLayout from '@/layouts/auth-layout';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

type AcceptProps = {
    token: string;
    valid: boolean;
    organizationName: string | null;
    email: string | null;
    emailMatches: boolean;
    authenticated: boolean;
};

export default function AcceptInvitation({ token, valid, organizationName, email, emailMatches, authenticated }: AcceptProps) {
    const [accepting, setAccepting] = useState(false);

    if (!valid) {
        return (
            <AuthLayout title="Invitation not found" description="This invitation is invalid, has expired, or was already used.">
                <Head title="Invitation" />
                <TextLink href={route('dashboard')}>Go to dashboard</TextLink>
            </AuthLayout>
        );
    }

    return (
        <AuthLayout title={`Join ${organizationName}`} description={`You've been invited to join ${organizationName} on Piotrack.`}>
            <Head title="Accept invitation" />

            {!authenticated ? (
                <div className="space-y-4 text-sm">
                    <p className="text-muted-foreground">
                        Sign in as <strong>{email}</strong> to accept this invitation. If you don't have an account yet, create one with that email
                        address.
                    </p>
                    <div className="flex gap-3">
                        <Button asChild>
                            <a href={route('login')}>Log in</a>
                        </Button>
                        <Button variant="outline" asChild>
                            <a href={route('register')}>Create account</a>
                        </Button>
                    </div>
                </div>
            ) : !emailMatches ? (
                <div className="space-y-4 text-sm">
                    <p className="text-muted-foreground">
                        This invitation was sent to <strong>{email}</strong>, which doesn't match the account you're signed in with. Log in with the
                        invited email to accept.
                    </p>
                    <TextLink href={route('dashboard')}>Back to dashboard</TextLink>
                </div>
            ) : (
                <div className="space-y-4">
                    <Button
                        disabled={accepting}
                        onClick={() =>
                            router.post(
                                route('invitations.accept', token),
                                {},
                                { onStart: () => setAccepting(true), onFinish: () => setAccepting(false) },
                            )
                        }
                        className="w-full"
                    >
                        {accepting ? 'Joining…' : 'Accept invitation'}
                    </Button>
                    <div className="text-center">
                        <TextLink href={route('dashboard')}>Not now</TextLink>
                    </div>
                </div>
            )}
        </AuthLayout>
    );
}
