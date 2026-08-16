import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { AuthLayout } from '@/components/auth/auth-layout';
import { Button } from '@/components/ui/button';
import { register } from '@/routes';
import { store } from '@/routes/login';

type LoginErrors = {
    email?: string;
    password?: string;
};

export default function Login() {
    const form = useForm({
        email: '',
        password: '',
        remember: false,
    });

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.post(store.url(), {
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

                <label className="field-label" htmlFor="password">Password</label>
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

            <p className="auth-switch">
                New to Purplelist? <Link href={register()}>Create an account</Link>
            </p>
        </AuthLayout>
    );
}
