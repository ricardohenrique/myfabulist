import { Head, Link } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import { AuthLayout } from '@/components/auth/auth-layout';
import { Button } from '@/components/ui/button';
import { login } from '@/routes';

type RegisterErrors = Partial<Record<'name' | 'email' | 'password' | 'passwordConfirmation', string>>;

export default function Register() {
    const [name, setName] = useState('');
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [passwordConfirmation, setPasswordConfirmation] = useState('');
    const [errors, setErrors] = useState<RegisterErrors>({});
    const [submitting, setSubmitting] = useState(false);
    const [message, setMessage] = useState('');

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        const nextErrors: RegisterErrors = {};

        if (name.trim().length < 2) nextErrors.name = 'Tell us what we should call you.';
        if (!email.includes('@')) nextErrors.email = 'Enter a valid email address.';
        if (password.length < 8) nextErrors.password = 'Use at least 8 characters.';
        if (password !== passwordConfirmation) nextErrors.passwordConfirmation = 'The passwords do not match.';

        setErrors(nextErrors);
        setMessage('');

        if (Object.keys(nextErrors).length > 0) return;

        setSubmitting(true);
        window.setTimeout(() => {
            setSubmitting(false);
            setMessage('Static preview complete — registration is connected in Phase 2.');
        }, 650);
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
                    aria-invalid={Boolean(errors.name)}
                    autoComplete="name"
                    className="text-field"
                    id="name"
                    onChange={(event) => setName(event.target.value)}
                    placeholder="Your name"
                    value={name}
                />
                {errors.name && <p className="field-error">{errors.name}</p>}

                <label className="field-label" htmlFor="email">Email address</label>
                <input
                    aria-invalid={Boolean(errors.email)}
                    autoComplete="email"
                    className="text-field"
                    id="email"
                    onChange={(event) => setEmail(event.target.value)}
                    placeholder="you@example.com"
                    type="email"
                    value={email}
                />
                {errors.email && <p className="field-error">{errors.email}</p>}

                <label className="field-label" htmlFor="password">Password</label>
                <input
                    aria-invalid={Boolean(errors.password)}
                    autoComplete="new-password"
                    className="text-field"
                    id="password"
                    onChange={(event) => setPassword(event.target.value)}
                    placeholder="At least 8 characters"
                    type="password"
                    value={password}
                />
                {errors.password && <p className="field-error">{errors.password}</p>}

                <label className="field-label" htmlFor="password-confirmation">Confirm password</label>
                <input
                    aria-invalid={Boolean(errors.passwordConfirmation)}
                    autoComplete="new-password"
                    className="text-field"
                    id="password-confirmation"
                    onChange={(event) => setPasswordConfirmation(event.target.value)}
                    placeholder="Repeat your password"
                    type="password"
                    value={passwordConfirmation}
                />
                {errors.passwordConfirmation && <p className="field-error">{errors.passwordConfirmation}</p>}

                <Button className="auth-submit" disabled={submitting} type="submit" variant="primary">
                    {submitting ? 'Creating account…' : 'Create my account'}
                </Button>

                {message && <p className="form-notice" role="status">{message}</p>}
            </form>

            <p className="auth-switch">
                Already have an account? <Link href={login()}>Sign in</Link>
            </p>
        </AuthLayout>
    );
}
