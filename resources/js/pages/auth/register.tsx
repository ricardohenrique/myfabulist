import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { AuthLayout } from '@/components/auth/auth-layout';
import {
    PasswordMatch,
    PasswordRequirements,
} from '@/components/auth/password-requirements';
import { Button } from '@/components/ui/button';
import { trackAnalyticsEvent } from '@/lib/analytics';
import { login } from '@/routes';
import { store } from '@/routes/register';
import type { PasswordRequirementsConfig } from '@/types';

type RegisterProps = {
    passwordRequirements: PasswordRequirementsConfig;
};

export default function Register({ passwordRequirements }: RegisterProps) {
    const form = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
    });

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.post(store.url(), {
            onSuccess: () => trackAnalyticsEvent('sign_up', { method: 'password' }),
        });
    };

    return (
        <AuthLayout
            eyebrow="Start fresh"
            intro="Your Inbox will be waiting the moment your account is ready."
            title="Bring a little calm to your plans."
        >
            <Head title="Create account" />

            <form className="auth-form" noValidate onSubmit={submit}>
                <label className="field-label" htmlFor="name">Name</label>
                <input
                    aria-invalid={Boolean(form.errors.name)}
                    autoComplete="name"
                    className="text-field"
                    id="name"
                    onChange={(event) => form.setData('name', event.target.value)}
                    placeholder="Your name"
                    value={form.data.name}
                />
                {form.errors.name && <p className="field-error">{form.errors.name}</p>}

                <label className="field-label" htmlFor="email">Email address</label>
                <input
                    aria-invalid={Boolean(form.errors.email)}
                    autoComplete="email"
                    className="text-field"
                    id="email"
                    onChange={(event) => form.setData('email', event.target.value)}
                    placeholder="you@example.com"
                    type="email"
                    value={form.data.email}
                />
                {form.errors.email && <p className="field-error">{form.errors.email}</p>}

                <label className="field-label" htmlFor="password">Password</label>
                <input
                    aria-describedby={`registration-password-requirements${form.errors.password ? ' registration-password-error' : ''}`}
                    aria-invalid={Boolean(form.errors.password)}
                    autoComplete="new-password"
                    className="text-field"
                    id="password"
                    onChange={(event) => {
                        form.setData('password', event.target.value);
                        form.clearErrors('password');
                    }}
                    placeholder="One letter and one number"
                    type="password"
                    value={form.data.password}
                />
                {form.errors.password && (
                    <p className="field-error" id="registration-password-error" role="alert">
                        {form.errors.password}
                    </p>
                )}
                <PasswordRequirements
                    id="registration-password-requirements"
                    password={form.data.password}
                    requirements={passwordRequirements}
                />

                <label className="field-label" htmlFor="password-confirmation">Confirm password</label>
                <input
                    aria-describedby="registration-password-match"
                    aria-invalid={Boolean(form.errors.password_confirmation)}
                    autoComplete="new-password"
                    className="text-field"
                    id="password-confirmation"
                    onChange={(event) => {
                        form.setData('password_confirmation', event.target.value);
                        form.clearErrors('password_confirmation', 'password');
                    }}
                    placeholder="Repeat your password"
                    type="password"
                    value={form.data.password_confirmation}
                />
                {form.errors.password_confirmation && <p className="field-error">{form.errors.password_confirmation}</p>}
                <PasswordMatch
                    confirmation={form.data.password_confirmation}
                    id="registration-password-match"
                    password={form.data.password}
                />

                <Button className="auth-submit" disabled={form.processing} type="submit" variant="primary">
                    {form.processing ? 'Creating account…' : 'Create my account'}
                </Button>
            </form>

            <p className="auth-switch">
                Already have an account? <Link href={login()}>Sign in</Link>
            </p>
        </AuthLayout>
    );
}
