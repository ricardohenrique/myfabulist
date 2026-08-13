import { useEffect, useRef } from 'react';
import { Button } from '@/components/ui/button';
import { Icon } from '@/components/ui/icon';
import type { PendingInvitationSummary } from '@/types';

type NotificationCenterProps = {
    open: boolean;
    pendingCount: number;
    invitations: PendingInvitationSummary[] | undefined;
    respondingIds: number[];
    onToggle: () => void;
    onClose: () => void;
    onAccept: (invitationId: number) => void;
    onDecline: (invitationId: number) => void;
};

const relativeTime = new Intl.RelativeTimeFormat(undefined, { numeric: 'auto' });

function formatInvitedAt(value: string): string {
    const invitedAt = new Date(value);
    const elapsedSeconds = Math.round((invitedAt.getTime() - Date.now()) / 1_000);

    if (Math.abs(elapsedSeconds) < 60) return 'just now';
    if (Math.abs(elapsedSeconds) < 3_600) return relativeTime.format(Math.round(elapsedSeconds / 60), 'minute');
    if (Math.abs(elapsedSeconds) < 86_400) return relativeTime.format(Math.round(elapsedSeconds / 3_600), 'hour');
    if (Math.abs(elapsedSeconds) < 604_800) return relativeTime.format(Math.round(elapsedSeconds / 86_400), 'day');

    return new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' }).format(invitedAt);
}

type InvitationRowProps = {
    invitation: PendingInvitationSummary;
    responding: boolean;
    onAccept: (invitationId: number) => void;
    onDecline: (invitationId: number) => void;
};

function InvitationRow({ invitation, responding, onAccept, onDecline }: InvitationRowProps) {
    const inviterName = invitation.invitedBy?.name ?? 'Someone';

    return (
        <li className="notification-row">
            <span className="notification-row__avatar">
                {invitation.invitedBy?.avatarUrl
                    ? <img alt="" src={invitation.invitedBy.avatarUrl} />
                    : <Icon name="user" size={15} />}
            </span>
            <div className="notification-row__copy">
                <p><strong>{inviterName}</strong> invited you to <strong>{invitation.list.name}</strong></p>
                {invitation.invitedAt && <small>{formatInvitedAt(invitation.invitedAt)}</small>}
            </div>
            <div className="notification-row__actions">
                <Button disabled={responding} onClick={() => onDecline(invitation.id)} size="sm" variant="ghost">
                    Decline
                </Button>
                <Button disabled={responding} onClick={() => onAccept(invitation.id)} size="sm" variant="primary">
                    Accept
                </Button>
            </div>
        </li>
    );
}

export function NotificationCenter({
    open,
    pendingCount,
    invitations,
    respondingIds,
    onToggle,
    onClose,
    onAccept,
    onDecline,
}: NotificationCenterProps) {
    const panelRef = useRef<HTMLDivElement>(null);
    const triggerRef = useRef<HTMLButtonElement>(null);

    // Focuses the panel on open. Deliberately has no cleanup that restores
    // focus on every close — an outside click (e.g. into the quick-add task
    // input) must not yank focus back to the bell, and a `Link` navigation
    // while the panel happens to be open must not refocus an already
    // detached node. Only the keyboard (Escape) path restores focus, and it
    // does so explicitly below, to `triggerRef` rather than whatever
    // `document.activeElement` happened to capture at open time (Safari
    // doesn't focus a clicked `<button>` the way Chrome/Firefox do, so that
    // capture is unreliable).
    useEffect(() => {
        if (!open) {
            return;
        }

        panelRef.current?.focus();
    }, [open]);

    useEffect(() => {
        if (!open) {
            return;
        }

        const handleKeyDown = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                onClose();
                triggerRef.current?.focus();
            }
        };

        document.addEventListener('keydown', handleKeyDown);
        return () => document.removeEventListener('keydown', handleKeyDown);
    }, [onClose, open]);

    useEffect(() => {
        if (!open) {
            return;
        }

        const handleOutsideClick = (event: MouseEvent) => {
            const target = event.target as Node;

            if (panelRef.current?.contains(target) || triggerRef.current?.contains(target)) {
                return;
            }

            onClose();
        };

        document.addEventListener('mousedown', handleOutsideClick);
        return () => document.removeEventListener('mousedown', handleOutsideClick);
    }, [onClose, open]);

    const label = pendingCount > 0 ? `Notifications, ${pendingCount} pending` : 'Notifications';

    return (
        <div className="notification-center">
            <button
                aria-expanded={open}
                aria-haspopup="dialog"
                aria-label={label}
                className="icon-button"
                onClick={onToggle}
                ref={triggerRef}
                type="button"
            >
                <Icon name="bell" size={18} />
                {pendingCount > 0 && <span className="notification-badge">{pendingCount}</span>}
            </button>
            {open && (
                <div
                    aria-label="Notifications"
                    className="notification-panel"
                    ref={panelRef}
                    role="dialog"
                    tabIndex={-1}
                >
                    <div className="notification-panel__heading">
                        <h2>Notifications</h2>
                    </div>
                    <div aria-busy={invitations === undefined} aria-live="polite" className="notification-panel__body">
                        {invitations === undefined ? (
                            <p className="notification-panel__state">Loading…</p>
                        ) : invitations.length === 0 ? (
                            <p className="notification-panel__state">You’re all caught up.</p>
                        ) : (
                            <ul className="notification-list">
                                {invitations.map((invitation) => (
                                    <InvitationRow
                                        invitation={invitation}
                                        key={invitation.id}
                                        onAccept={onAccept}
                                        onDecline={onDecline}
                                        responding={respondingIds.includes(invitation.id)}
                                    />
                                ))}
                            </ul>
                        )}
                    </div>
                </div>
            )}
        </div>
    );
}
