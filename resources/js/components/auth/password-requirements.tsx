import type { PasswordRequirementsConfig } from '@/types';

type PasswordRequirement = {
    key: string;
    label: string;
    met: boolean;
};

type PasswordRequirementsProps = {
    id: string;
    password: string;
    requirements: PasswordRequirementsConfig;
};

type PasswordMatchProps = {
    confirmation: string;
    id: string;
    password: string;
};

export function PasswordRequirements({ id, password, requirements }: PasswordRequirementsProps) {
    const checks: PasswordRequirement[] = [];

    if (requirements.letters) {
        checks.push({ key: 'letter', label: 'One letter', met: /[a-zA-Z]/.test(password) });
    }

    if (requirements.numbers) {
        checks.push({ key: 'number', label: 'One number', met: /[0-9]/.test(password) });
    }

    return (
        <div className="password-requirements" id={id}>
            <p className="password-requirements__title">Password must include:</p>
            <ul className="password-requirements__list">
                {checks.map((check) => (
                    <li
                        aria-label={`${check.label}: ${check.met ? 'complete' : 'not complete'}`}
                        className={check.met ? 'password-requirement is-met' : 'password-requirement'}
                        key={check.key}
                    >
                        {check.label}
                    </li>
                ))}
            </ul>
        </div>
    );
}

export function PasswordMatch({ confirmation, id, password }: PasswordMatchProps) {
    const passwordsMatch = confirmation.length > 0 && password === confirmation;

    return (
        <p
            aria-live="polite"
            className={passwordsMatch ? 'password-match is-met' : 'password-match'}
            id={id}
        >
            {passwordsMatch ? 'Passwords match' : 'Passwords must match'}
        </p>
    );
}
