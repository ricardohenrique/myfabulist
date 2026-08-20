import { useMemo, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Icon } from '@/components/ui/icon';
import type { NotificationItem } from '@/types';

type NotificationCenterViewProps = {
    items: NotificationItem[];
    respondingInvitationIds: number[];
    onAccept: (invitationId: number) => void;
    onDecline: (invitationId: number) => void;
    onOpen: (notificationId: string) => void;
    onToggleRead: (notification: NotificationItem) => void;
};

const relativeTime = new Intl.RelativeTimeFormat(undefined, { numeric: 'auto' });

function formatCreatedAt(value: string): string {
    const createdAt = new Date(value);
    const elapsedSeconds = Math.round((createdAt.getTime() - Date.now()) / 1_000);

    if (Math.abs(elapsedSeconds) < 60) return 'just now';
    if (Math.abs(elapsedSeconds) < 3_600) return relativeTime.format(Math.round(elapsedSeconds / 60), 'minute');
    if (Math.abs(elapsedSeconds) < 86_400) return relativeTime.format(Math.round(elapsedSeconds / 3_600), 'hour');
    if (Math.abs(elapsedSeconds) < 604_800) return relativeTime.format(Math.round(elapsedSeconds / 86_400), 'day');

    return new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' }).format(createdAt);
}

function InvitationActions({
    item,
    responding,
    onAccept,
    onDecline,
}: {
    item: NotificationItem;
    responding: boolean;
    onAccept: (invitationId: number) => void;
    onDecline: (invitationId: number) => void;
}) {
    const invitation = item.invitation;

    if (!invitation || invitation.id === null) return null;

    const disabled = responding || !invitation.canRespond;

    return (
        <div className="notification-item__actions">
            <Button
                disabled={disabled}
                onClick={() => onDecline(invitation.id!)}
                size="sm"
                variant={invitation.status === 'declined' ? 'primary' : 'ghost'}
            >
                {invitation.status === 'declined' ? 'Declined' : 'Decline'}
            </Button>
            <Button
                disabled={disabled}
                onClick={() => onAccept(invitation.id!)}
                size="sm"
                variant={invitation.status === 'accepted' ? 'primary' : 'secondary'}
            >
                {invitation.status === 'accepted' ? 'Accepted' : 'Accept'}
            </Button>
            {['revoked', 'unavailable'].includes(invitation.status) && (
                <span className="notification-item__expired">No longer available</span>
            )}
        </div>
    );
}

export function NotificationCenterView({
    items,
    respondingInvitationIds,
    onAccept,
    onDecline,
    onOpen,
    onToggleRead,
}: NotificationCenterViewProps) {
    const [filter, setFilter] = useState<'all' | 'unread'>('all');
    const visibleItems = useMemo(
        () => filter === 'unread' ? items.filter((item) => item.readAt === null) : items,
        [filter, items],
    );

    return (
        <div className="notification-canvas">
            <section aria-labelledby="notification-view-title" className="notification-view">
                <div className="notification-view__heading">
                    <div>
                        <span>Activity</span>
                        <h2 id="notification-view-title">Notifications</h2>
                    </div>
                    <div aria-label="Notification filters" className="notification-filter" role="group">
                        <button aria-pressed={filter === 'all'} onClick={() => setFilter('all')} type="button">All</button>
                        <button aria-pressed={filter === 'unread'} onClick={() => setFilter('unread')} type="button">Unread</button>
                    </div>
                </div>

                {visibleItems.length === 0 ? (
                    <div className="notification-view__empty">
                        <div><Icon name="bell" size={28} /></div>
                        <h3>{filter === 'unread' ? 'No unread notifications.' : 'You’re all caught up.'}</h3>
                        <p>{filter === 'unread' ? 'New collaboration updates will appear here.' : 'Invitations and shared-list comments will appear here.'}</p>
                    </div>
                ) : (
                    <ul className="notification-items">
                        {visibleItems.map((item) => {
                            const invitationId = item.invitation?.id;
                            const responding = invitationId !== null
                                && invitationId !== undefined
                                && respondingInvitationIds.includes(invitationId);

                            return (
                                <li className={`notification-item ${item.readAt === null ? 'is-unread' : ''}`} key={item.id}>
                                    <button
                                        aria-label={`Open notification from ${item.actor.name}`}
                                        className="notification-item__open-surface"
                                        onClick={() => onOpen(item.id)}
                                        type="button"
                                    />

                                    <div className="notification-item__main">
                                        <span className={`notification-item__avatar notification-item__avatar--${item.type}`}>
                                            {item.actor.avatarUrl
                                                ? <img alt="" src={item.actor.avatarUrl} />
                                                : <Icon name={item.type === 'task_comment' ? 'comment' : 'user'} size={17} />}
                                        </span>
                                        <span className="notification-item__copy">
                                            <span className="notification-item__message">
                                                {item.type === 'task_comment' ? (
                                                    <><strong>{item.actor.name}</strong> commented on <strong>{item.task?.title}</strong> in {item.list.name}</>
                                                ) : (
                                                    <><strong>{item.actor.name}</strong> invited you to <strong>{item.list.name}</strong></>
                                                )}
                                            </span>
                                            {item.body && <span className="notification-item__body">“{item.body}”</span>}
                                            <small>{formatCreatedAt(item.createdAt)}</small>
                                        </span>
                                    </div>

                                    {item.type === 'list_invitation' && (
                                        <InvitationActions
                                            item={item}
                                            onAccept={onAccept}
                                            onDecline={onDecline}
                                            responding={responding}
                                        />
                                    )}

                                    <button
                                        aria-label={item.readAt === null ? 'Mark as read' : 'Mark as unread'}
                                        className={`notification-read-toggle ${item.readAt === null ? 'is-unread' : ''}`}
                                        onClick={() => onToggleRead(item)}
                                        title={item.readAt === null ? 'Mark as read' : 'Mark as unread'}
                                        type="button"
                                    />
                                </li>
                            );
                        })}
                    </ul>
                )}
            </section>
        </div>
    );
}
