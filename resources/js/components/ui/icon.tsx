import type { IconName } from '@/types';
import type { ReactNode } from 'react';

type IconProps = {
    name: IconName;
    size?: number;
    className?: string;
    fill?: boolean;
};

const paths: Record<IconName, ReactNode> = {
    bell: <path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4" />,
    calendar: <><path d="M3 5h18v16H3zM16 3v4M8 3v4M3 10h18" /></>,
    check: <path d="m5 12 4 4L19 6" />,
    'chevron-down': <path d="m6 9 6 6 6-6" />,
    'chevron-left': <path d="m15 18-6-6 6-6" />,
    'chevron-right': <path d="m9 18 6-6-6-6" />,
    circle: <circle cx="12" cy="12" r="9" />,
    close: <path d="M18 6 6 18M6 6l12 12" />,
    comment: <><path d="M4 5h16v11H8l-4 4z" /><path d="M8 9h8M8 12h5" /></>,
    folder: <path d="M3 6h7l2 2h9v11H3z" />,
    inbox: <><path d="M4 5h16l2 9v5H2v-5z" /><path d="M2 14h5l2 3h6l2-3h5" /></>,
    list: <><path d="M9 6h12M9 12h12M9 18h12" /><path d="M4 6h.01M4 12h.01M4 18h.01" /></>,
    menu: <path d="M4 7h16M4 12h16M4 17h16" />,
    more: <><circle cx="5" cy="12" r="1" /><circle cx="12" cy="12" r="1" /><circle cx="19" cy="12" r="1" /></>,
    note: <><path d="M5 3h14v18H5z" /><path d="M8 8h8M8 12h8M8 16h5" /></>,
    pause: <><path d="M9 6v12M15 6v12" /></>,
    play: <path d="m8 5 11 7-11 7z" />,
    plus: <path d="M12 5v14M5 12h14" />,
    search: <><circle cx="11" cy="11" r="7" /><path d="m20 20-4-4" /></>,
    star: <path d="m12 3 2.8 5.7 6.2.9-4.5 4.4 1.1 6.2-5.6-3-5.6 3 1.1-6.2L3 9.6l6.2-.9z" />,
    trash: <><path d="M4 7h16M9 7V4h6v3M7 7l1 14h8l1-14" /><path d="M10 11v6M14 11v6" /></>,
    user: <><circle cx="12" cy="8" r="4" /><path d="M4 21a8 8 0 0 1 16 0" /></>,
    volume: <><path d="M5 10H2v4h3l5 4V6z" /><path d="M14 9a4 4 0 0 1 0 6M17 6a8 8 0 0 1 0 12" /></>,
    'volume-off': <><path d="M5 10H2v4h3l5 4V6z" /><path d="m15 10 5 5M20 10l-5 5" /></>,
};

export function Icon({ name, size = 20, className, fill = false }: IconProps) {
    return (
        <svg
            aria-hidden="true"
            className={className}
            fill={fill ? 'currentColor' : 'none'}
            height={size}
            viewBox="0 0 24 24"
            width={size}
            xmlns="http://www.w3.org/2000/svg"
            stroke="currentColor"
            strokeLinecap="round"
            strokeLinejoin="round"
            strokeWidth={fill ? 1.4 : 1.7}
        >
            {paths[name]}
        </svg>
    );
}
