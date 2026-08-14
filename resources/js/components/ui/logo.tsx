type LogoProps = {
    size?: number;
    className?: string;
};

export function Logo({ size = 48, className }: LogoProps) {
    return (
        <svg
            aria-hidden="true"
            className={className}
            height={size}
            viewBox="0 0 166 166"
            width={size}
            xmlns="http://www.w3.org/2000/svg"
        >
            <rect fill="#f7f4fc" height="166" rx="34" width="166" />
            <path d="M39 29c0-6.627 5.373-12 12-12h64c6.627 0 12 5.373 12 12v116l-44-18-44 18V29Z" fill="#8b6fd6" />
            <path d="m58 76 17 18 34-39" fill="none" stroke="#f7f4fc" strokeLinecap="round" strokeLinejoin="round" strokeWidth="14" />
        </svg>
    );
}
