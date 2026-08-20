import { Link } from '@inertiajs/react';
import { Icon } from '@/components/ui/icon';
import { index as notifications } from '@/routes/notifications';

type NotificationCenterProps = {
    active: boolean;
    unreadCount: number;
    onNavigate: () => void;
};

export function NotificationCenter({ active, unreadCount, onNavigate }: NotificationCenterProps) {
    const label = unreadCount > 0 ? `Notifications, ${unreadCount} unread` : 'Notifications';

    return (
        <Link
            aria-current={active ? 'page' : undefined}
            aria-label={label}
            className={`icon-button notification-trigger ${active ? 'is-active' : ''}`}
            href={notifications()}
            onClick={onNavigate}
        >
            <Icon name="bell" size={18} />
            {unreadCount > 0 && <span className="notification-badge">{unreadCount}</span>}
        </Link>
    );
}
