import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { AuthLayout } from '@/components/auth/auth-layout';
import { Button } from '@/components/ui/button';
import { login } from '@/routes';
import { update } from '@/routes/password';

type ResetPasswordProps = {
    email: string;
    token: string;
};

export default function ResetPassword({ email, token }: ResetPasswordProps) {
    const form = useForm({
        token,
        email,
        password: '',
        password_confirmation: '',
    });

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.post(update.url(), {
            onFinish: () => form.reset('password', 'password_confirmation'),
        });
    };

    return (
        <AuthLayout
            eyebrow="Account recovery"
            intro="Choose a new password for your Purplelist account."
            title="Start with a fresh key."
        >
            <Head title="Reset password" />

            <form className="auth-form" noValidate onSubmit={submit}>
                <label className="field-label" htmlFor="email">Email address</label>
                <input
                    aria-invalid={Boolean(form.errors.email)}
                    autoComplete="email"
                    className="text-field"
                    id="email"
                    onChange={(event) => form.setData('email', event.target.value)}
                    type="email"
                    value={form.data.email}
                />
                {form.errors.email && <p className="field-error">{form.errors.email}</p>}

                <label className="field-label" htmlFor="password">New password</label>
                <input
                    aria-invalid={Boolean(form.errors.password)}
                    autoComplete="new-password"
                    autoFocus
                    className="text-field"
                    id="password"
                    onChange={(event) => form.setData('password', event.target.value)}
                    placeholder="At least 8 characters"
                    type="password"
                    value={form.data.password}
                />
                {form.errors.password && <p className="field-error">{form.errors.password}</p>}

                <label className="field-label" htmlFor="password-confirmation">Confirm new password</label>
                <input
                    autoComplete="new-password"
                    className="text-field"
                    id="password-confirmation"
                    onChange={(event) => form.setData('password_confirmation', event.target.value)}
                    type="password"
                    value={form.data.password_confirmation}
                />

                <Button className="auth-submit" disabled={form.processing} type="submit" variant="primary">
                    {form.processing ? 'Resetting…' : 'Reset password'}
                </Button>
            </form>

            <p className="auth-switch"><Link href={login()}>Back to sign in</Link></p>
        </AuthLayout>
    );
}
