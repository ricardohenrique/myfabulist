import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { AuthLayout } from '@/components/auth/auth-layout';
import {
    PasswordMatch,
    PasswordRequirements,
} from '@/components/auth/password-requirements';
import { Button } from '@/components/ui/button';
import { login } from '@/routes';
import { update } from '@/routes/password';
import type { PasswordRequirementsConfig } from '@/types';

type ResetPasswordProps = {
    email: string;
    passwordRequirements: PasswordRequirementsConfig;
    token: string;
};

export default function ResetPassword({ email, passwordRequirements, token }: ResetPasswordProps) {
    const form = useForm({
        token,
        email,
        password: '',
        password_confirmation: '',
    });

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.post(update.url());
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
                    aria-describedby={`password-requirements${form.errors.password ? ' password-error' : ''}`}
                    aria-invalid={Boolean(form.errors.password)}
                    autoComplete="new-password"
                    autoFocus
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
                {form.errors.password && <p className="field-error" id="password-error" role="alert">{form.errors.password}</p>}

                <PasswordRequirements
                    id="password-requirements"
                    password={form.data.password}
                    requirements={passwordRequirements}
                />

                <label className="field-label" htmlFor="password-confirmation">Confirm new password</label>
                <input
                    aria-describedby="password-match"
                    aria-invalid={Boolean(form.errors.password_confirmation)}
                    autoComplete="new-password"
                    className="text-field"
                    id="password-confirmation"
                    onChange={(event) => {
                        form.setData('password_confirmation', event.target.value);
                        form.clearErrors('password_confirmation', 'password');
                    }}
                    type="password"
                    value={form.data.password_confirmation}
                />
                {form.errors.password_confirmation && <p className="field-error" role="alert">{form.errors.password_confirmation}</p>}
                <PasswordMatch
                    confirmation={form.data.password_confirmation}
                    id="password-match"
                    password={form.data.password}
                />

                <Button className="auth-submit" disabled={form.processing} type="submit" variant="primary">
                    {form.processing ? 'Resetting…' : 'Reset password'}
                </Button>
            </form>

            <p className="auth-switch"><Link href={login()}>Back to sign in</Link></p>
        </AuthLayout>
    );
}
