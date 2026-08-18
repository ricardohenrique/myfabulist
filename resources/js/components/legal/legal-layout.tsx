import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { Logo } from '@/components/ui/logo';
import { home, login, privacy, terms } from '@/routes';

type LegalLayoutProps = {
    children: ReactNode;
    description: string;
    eyebrow: string;
    title: string;
};

export function LegalLayout({ children, description, eyebrow, title }: LegalLayoutProps) {
    return (
        <main className="legal-shell">
            <header className="legal-header">
                <Link className="legal-brand" href={home()}>
                    <Logo size={38} />
                    <span>Purplelist</span>
                </Link>
                <nav aria-label="Legal and account navigation" className="legal-nav">
                    <Link href={privacy()}>Privacy</Link>
                    <Link href={terms()}>Terms</Link>
                    <Link className="legal-nav__login" href={login()}>Sign in</Link>
                </nav>
            </header>

            <article className="legal-document">
                <header className="legal-document__heading">
                    <p className="legal-eyebrow">{eyebrow}</p>
                    <h1>{title}</h1>
                    <p>{description}</p>
                    <p className="legal-updated">Last updated: 18 August 2026</p>
                </header>
                <div className="legal-content">{children}</div>
            </article>

            <footer className="legal-footer">
                <span>© {new Date().getFullYear()} Purplelist</span>
                <span>Simple lists. Clear days.</span>
            </footer>
        </main>
    );
}
