import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { AuthLayout } from '@/components/auth/auth-layout';
import { Button } from '@/components/ui/button';
import { inbox } from '@/routes';
import { send } from '@/routes/verification';

type VerifyEmailProps = {
    email: string;
    status?: string | null;
};

export default function VerifyEmail({ email, status }: VerifyEmailProps) {
    const form = useForm({});

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.post(send.url());
    };

    return (
        <AuthLayout
            eyebrow="Email confirmation"
            intro={`We sent a confirmation link to ${email}. Confirmation is optional, so you can keep using Purplelist now.`}
            title="Check your inbox."
        >
            <Head title="Confirm email" />

            {status && <p className="form-notice" role="status">A fresh confirmation link is on its way.</p>}

            <form className="auth-form" onSubmit={submit}>
                <Button className="auth-submit" disabled={form.processing} type="submit" variant="secondary">
                    {form.processing ? 'Sending…' : 'Resend confirmation email'}
                </Button>
            </form>

            <p className="auth-switch"><Link href={inbox()}>Continue to Inbox</Link></p>
        </AuthLayout>
    );
}
