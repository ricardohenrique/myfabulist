export type PasswordRequirementsConfig = {
    min: number;
    mixedCase: boolean;
    letters: boolean;
    numbers: boolean;
    symbols: boolean;
};

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
    const checks: PasswordRequirement[] = [
        {
            key: 'length',
            label: `At least ${requirements.min} characters`,
            met: Array.from(password).length >= requirements.min,
        },
    ];

    if (requirements.mixedCase) {
        checks.push(
            { key: 'lowercase', label: 'One lowercase letter', met: /\p{Ll}/u.test(password) },
            { key: 'uppercase', label: 'One uppercase letter', met: /\p{Lu}/u.test(password) },
        );
    } else if (requirements.letters) {
        checks.push({ key: 'letter', label: 'One letter', met: /\p{L}/u.test(password) });
    }

    if (requirements.numbers) {
        checks.push({ key: 'number', label: 'One number', met: /\p{N}/u.test(password) });
    }

    if (requirements.symbols) {
        checks.push({ key: 'symbol', label: 'One symbol or space', met: /[\p{Z}\p{S}\p{P}]/u.test(password) });
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
