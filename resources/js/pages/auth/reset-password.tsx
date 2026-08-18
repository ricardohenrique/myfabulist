import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { AuthLayout } from '@/components/auth/auth-layout';
import { Button } from '@/components/ui/button';
import { login } from '@/routes';
import { update } from '@/routes/password';

type PasswordRequirements = {
    min: number;
    mixedCase: boolean;
    letters: boolean;
    numbers: boolean;
    symbols: boolean;
    uncompromised: boolean;
};

type ResetPasswordProps = {
    email: string;
    passwordRequirements: PasswordRequirements;
    token: string;
};

type PasswordCheck = {
    key: string;
    label: string;
    met: boolean;
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

    const password = form.data.password;
    const confirmation = form.data.password_confirmation;
    const passwordChecks: PasswordCheck[] = [
        {
            key: 'length',
            label: `At least ${passwordRequirements.min} characters`,
            met: Array.from(password).length >= passwordRequirements.min,
        },
    ];

    if (passwordRequirements.mixedCase) {
        passwordChecks.push(
            { key: 'lowercase', label: 'One lowercase letter', met: /\p{Ll}/u.test(password) },
            { key: 'uppercase', label: 'One uppercase letter', met: /\p{Lu}/u.test(password) },
        );
    } else if (passwordRequirements.letters) {
        passwordChecks.push({ key: 'letter', label: 'One letter', met: /\p{L}/u.test(password) });
    }

    if (passwordRequirements.numbers) {
        passwordChecks.push({ key: 'number', label: 'One number', met: /\p{N}/u.test(password) });
    }

    if (passwordRequirements.symbols) {
        passwordChecks.push({ key: 'symbol', label: 'One symbol or space', met: /[\p{Z}\p{S}\p{P}]/u.test(password) });
    }

    const passwordsMatch = confirmation.length > 0 && password === confirmation;

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
                    placeholder={`At least ${passwordRequirements.min} characters`}
                    type="password"
                    value={form.data.password}
                />
                {form.errors.password && <p className="field-error" id="password-error" role="alert">{form.errors.password}</p>}

                <div className="password-requirements" id="password-requirements">
                    <p className="password-requirements__title">Password must include:</p>
                    <ul className="password-requirements__list">
                        {passwordChecks.map((check) => (
                            <li
                                aria-label={`${check.label}: ${check.met ? 'complete' : 'not complete'}`}
                                className={check.met ? 'password-requirement is-met' : 'password-requirement'}
                                key={check.key}
                            >
                                {check.label}
                            </li>
                        ))}
                    </ul>
                    {passwordRequirements.uncompromised && (
                        <p className="password-requirements__breach-note">
                            Known data breaches are checked securely when you submit.
                        </p>
                    )}
                </div>

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
                <p
                    aria-live="polite"
                    className={passwordsMatch ? 'password-match is-met' : 'password-match'}
                    id="password-match"
                >
                    {passwordsMatch ? 'Passwords match' : 'Passwords must match'}
                </p>

                <Button className="auth-submit" disabled={form.processing} type="submit" variant="primary">
                    {form.processing ? 'Resetting…' : 'Reset password'}
                </Button>
            </form>

            <p className="auth-switch"><Link href={login()}>Back to sign in</Link></p>
        </AuthLayout>
    );
}
