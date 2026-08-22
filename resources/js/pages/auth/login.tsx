import { Head, Link, useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { AuthLayout } from '@/components/auth/auth-layout';
import { Button } from '@/components/ui/button';
import { trackAnalyticsEvent } from '@/lib/analytics';
import { privacy, register, terms } from '@/routes';
import { google } from '@/routes/auth';
import { store } from '@/routes/login';
import { request as requestPasswordReset } from '@/routes/password';

type LoginErrors = {
    email?: string;
    password?: string;
};

type LoginPageProps = {
    flash?: {
        error?: string | null;
    };
};

type LoginProps = {
    status?: string | null;
};

function GoogleLogo() {
    return (
        <svg aria-hidden="true" height="18" viewBox="0 0 18 18" width="18">
            <path d="M17.64 9.205c0-.639-.057-1.252-.164-1.841H9v3.482h4.844a4.14 4.14 0 0 1-1.797 2.716v2.258h2.909c1.702-1.567 2.684-3.875 2.684-6.615Z" fill="#4285F4" />
            <path d="M9 18c2.43 0 4.468-.806 5.956-2.18l-2.91-2.258c-.805.54-1.835.86-3.046.86-2.344 0-4.328-1.585-5.037-3.714H.956v2.332A9 9 0 0 0 9 18Z" fill="#34A853" />
            <path d="M3.963 10.708A5.416 5.416 0 0 1 3.681 9c0-.592.102-1.168.282-1.708V4.96H.956A9 9 0 0 0 0 9c0 1.452.347 2.827.956 4.04l3.007-2.332Z" fill="#FBBC05" />
            <path d="M9 3.578c1.321 0 2.507.454 3.441 1.346l2.581-2.582C13.464.892 11.426 0 9 0A9 9 0 0 0 .956 4.96l3.007 2.332C4.672 5.163 6.656 3.578 9 3.578Z" fill="#EA4335" />
        </svg>
    );
}

export default function Login({ status }: LoginProps) {
    const { flash } = usePage<LoginPageProps>().props;
    const form = useForm({
        email: '',
        password: '',
        remember: true,
    });

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.post(store.url(), {
            onSuccess: () => trackAnalyticsEvent('login', { method: 'password' }),
            onFinish: () => form.reset('password'),
        });
    };

    return (
        <AuthLayout
            eyebrow="Welcome back"
            intro="Sign in and pick up exactly where you left off."
            title="A clearer day starts here."
        >
            <Head title="Log in" />

            {status && <p className="form-notice" role="status">{status}</p>}
            {flash?.error && <p className="auth-error" role="alert">{flash.error}</p>}

            <form className="auth-form" noValidate onSubmit={submit}>
                <label className="field-label" htmlFor="email">Email address</label>
                <input
                    aria-describedby={form.errors.email ? 'email-error' : undefined}
                    aria-invalid={Boolean(form.errors.email)}
                    autoComplete="email"
                    className="text-field"
                    id="email"
                    onChange={(event) => form.setData('email', event.target.value)}
                    placeholder="you@example.com"
                    type="email"
                    value={form.data.email}
                />
                {form.errors.email && <p className="field-error" id="email-error">{form.errors.email}</p>}

                <div className="field-label-row">
                    <label className="field-label" htmlFor="password">Password</label>
                    <Link className="auth-muted-link" href={requestPasswordReset()}>Forgot password?</Link>
                </div>
                <input
                    aria-describedby={form.errors.password ? 'password-error' : undefined}
                    aria-invalid={Boolean(form.errors.password)}
                    autoComplete="current-password"
                    className="text-field"
                    id="password"
                    onChange={(event) => form.setData('password', event.target.value)}
                    placeholder="Enter your password"
                    type="password"
                    value={form.data.password}
                />
                {form.errors.password && <p className="field-error" id="password-error">{form.errors.password}</p>}

                <label className="check-field">
                    <input checked={form.data.remember} onChange={(event) => form.setData('remember', event.target.checked)} type="checkbox" />
                    <span>Keep me signed in</span>
                </label>

                <Button className="auth-submit" disabled={form.processing} type="submit" variant="primary">
                    {form.processing ? 'Signing in…' : 'Sign in'}
                </Button>
            </form>

            <div className="auth-divider"><span>or</span></div>

            <a className="auth-google-button" href={google.url()} rel="nofollow">
                <GoogleLogo />
                <span>Continue with Google</span>
            </a>

            <p className="auth-switch">
                New to Purplelist? <Link href={register()}>Create an account</Link>
            </p>

            <nav aria-label="Legal" className="auth-legal-links">
                <Link href={privacy()}>Privacy</Link>
                <span aria-hidden="true">·</span>
                <Link href={terms()}>Terms</Link>
            </nav>
        </AuthLayout>
    );
}
