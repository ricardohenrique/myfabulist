import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { Logo } from '@/components/ui/logo';
import { show as prototype } from '@/routes/prototype';

type AuthLayoutProps = {
    eyebrow: string;
    title: string;
    intro: string;
    children: ReactNode;
};

export function AuthLayout({ eyebrow, title, intro, children }: AuthLayoutProps) {
    return (
        <main className="auth-shell">
            <section className="auth-story" aria-label="My Fabulist introduction">
                <div className="auth-story__wash" aria-hidden="true" />
                <Link className="auth-brand" href={prototype('inbox')}>
                    <Logo size={50} />
                    <span>My Fabulist</span>
                </Link>

                <div className="auth-story__content">
                    <p className="auth-kicker">Capture now. Organize when it makes sense.</p>
                    <h2>Make space for what matters today.</h2>
                    <p>
                        A calm home for the small things, the important things, and everything you want to remember.
                    </p>

                    <div className="auth-list-preview" aria-hidden="true">
                        <div className="auth-list-preview__heading">
                            <span>Today</span>
                            <span>3 tasks</span>
                        </div>
                        {['Plan the week', 'Send the final notes', 'Take a proper break'].map((task, index) => (
                            <div className="auth-list-preview__task" key={task}>
                                <span className={index === 0 ? 'is-done' : ''}>
                                    {index === 0 && '✓'}
                                </span>
                                <p>{task}</p>
                            </div>
                        ))}
                    </div>
                </div>

                <p className="auth-story__footer">Simple lists. Clear days. A little more breathing room.</p>
            </section>

            <section className="auth-form-side">
                <div className="auth-form-card">
                    <div className="auth-form-card__brand">
                        <Logo size={48} />
                        <span>My Fabulist</span>
                    </div>
                    <p className="auth-form-card__eyebrow">{eyebrow}</p>
                    <h1>{title}</h1>
                    <p className="auth-form-card__intro">{intro}</p>
                    {children}
                </div>
            </section>
        </main>
    );
}
