import { Head, Link } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import { AuthLayout } from '@/components/auth/auth-layout';
import { Button } from '@/components/ui/button';
import { register } from '@/routes';

type LoginErrors = {
    email?: string;
    password?: string;
};

export default function Login() {
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [remember, setRemember] = useState(false);
    const [errors, setErrors] = useState<LoginErrors>({});
    const [submitting, setSubmitting] = useState(false);
    const [message, setMessage] = useState('');

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        const nextErrors: LoginErrors = {};

        if (!email.includes('@')) {
            nextErrors.email = 'Enter a valid email address.';
        }

        if (password.length < 8) {
            nextErrors.password = 'Your password must contain at least 8 characters.';
        }

        setErrors(nextErrors);
        setMessage('');

        if (Object.keys(nextErrors).length > 0) {
            return;
        }

        setSubmitting(true);
        window.setTimeout(() => {
            setSubmitting(false);
            setMessage('Static preview complete — account submission is connected in Phase 2.');
        }, 650);
    };

    return (
        <AuthLayout
            eyebrow="Welcome back"
            intro="Sign in and pick up exactly where you left off."
            title="A clearer day starts here."
        >
            <Head title="Log in" />

            <form className="auth-form" noValidate onSubmit={submit}>
                <label className="field-label" htmlFor="email">Email address</label>
                <input
                    aria-describedby={errors.email ? 'email-error' : undefined}
                    aria-invalid={Boolean(errors.email)}
                    autoComplete="email"
                    className="text-field"
                    id="email"
                    onChange={(event) => setEmail(event.target.value)}
                    placeholder="you@example.com"
                    type="email"
                    value={email}
                />
                {errors.email && <p className="field-error" id="email-error">{errors.email}</p>}

                <label className="field-label" htmlFor="password">Password</label>
                <input
                    aria-describedby={errors.password ? 'password-error' : undefined}
                    aria-invalid={Boolean(errors.password)}
                    autoComplete="current-password"
                    className="text-field"
                    id="password"
                    onChange={(event) => setPassword(event.target.value)}
                    placeholder="Enter your password"
                    type="password"
                    value={password}
                />
                {errors.password && <p className="field-error" id="password-error">{errors.password}</p>}

                <label className="check-field">
                    <input checked={remember} onChange={(event) => setRemember(event.target.checked)} type="checkbox" />
                    <span>Keep me signed in</span>
                </label>

                <Button className="auth-submit" disabled={submitting} type="submit" variant="primary">
                    {submitting ? 'Signing in…' : 'Sign in'}
                </Button>

                {message && <p className="form-notice" role="status">{message}</p>}
            </form>

            <p className="auth-switch">
                New to My Fabulist? <Link href={register()}>Create an account</Link>
            </p>
        </AuthLayout>
    );
}
