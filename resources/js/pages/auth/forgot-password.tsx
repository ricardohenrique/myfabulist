import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { AuthLayout } from '@/components/auth/auth-layout';
import { Button } from '@/components/ui/button';
import { login } from '@/routes';
import { email as sendResetLink } from '@/routes/password';

type ForgotPasswordProps = {
    status?: string | null;
};

export default function ForgotPassword({ status }: ForgotPasswordProps) {
    const form = useForm({ email: '' });

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.post(sendResetLink.url());
    };

    return (
        <AuthLayout
            eyebrow="Account recovery"
            intro="Enter the email address for your account and we’ll send you a secure reset link."
            title="Find your way back."
        >
            <Head title="Forgot password" />

            {status && <p className="form-notice" role="status">{status}</p>}

            <form className="auth-form" noValidate onSubmit={submit}>
                <label className="field-label" htmlFor="email">Email address</label>
                <input
                    aria-describedby={form.errors.email ? 'email-error' : undefined}
                    aria-invalid={Boolean(form.errors.email)}
                    autoComplete="email"
                    autoFocus
                    className="text-field"
                    id="email"
                    onChange={(event) => form.setData('email', event.target.value)}
                    placeholder="you@example.com"
                    type="email"
                    value={form.data.email}
                />
                {form.errors.email && <p className="field-error" id="email-error">{form.errors.email}</p>}

                <Button className="auth-submit" disabled={form.processing} type="submit" variant="primary">
                    {form.processing ? 'Sending…' : 'Send reset link'}
                </Button>
            </form>

            <p className="auth-switch">
                Remembered it? <Link href={login()}>Back to sign in</Link>
            </p>
        </AuthLayout>
    );
}
