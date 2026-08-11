import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { AuthLayout } from '@/components/auth/auth-layout';
import { Button } from '@/components/ui/button';
import { login } from '@/routes';
import { store } from '@/routes/register';

type RegisterErrors = Partial<Record<'name' | 'email' | 'password' | 'passwordConfirmation', string>>;

export default function Register() {
    const form = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
    });

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.post(store.url(), {
            onFinish: () => form.reset('password', 'password_confirmation'),
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
                    aria-invalid={Boolean(form.errors.password)}
                    autoComplete="new-password"
                    className="text-field"
                    id="password"
                    onChange={(event) => form.setData('password', event.target.value)}
                    placeholder="At least 8 characters"
                    type="password"
                    value={form.data.password}
                />
                {form.errors.password && <p className="field-error">{form.errors.password}</p>}

                <label className="field-label" htmlFor="password-confirmation">Confirm password</label>
                <input
                    aria-invalid={Boolean(form.errors.password_confirmation)}
                    autoComplete="new-password"
                    className="text-field"
                    id="password-confirmation"
                    onChange={(event) => form.setData('password_confirmation', event.target.value)}
                    placeholder="Repeat your password"
                    type="password"
                    value={form.data.password_confirmation}
                />
                {form.errors.password_confirmation && <p className="field-error">{form.errors.password_confirmation}</p>}

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
