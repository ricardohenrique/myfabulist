import { useEffect, useRef, useState, type FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Dialog } from '@/components/ui/dialog';
import { Icon } from '@/components/ui/icon';
import type { CurrentListDetails } from '@/types';

type ShareDialogProps = {
    open: boolean;
    list: CurrentListDetails | null;
    viewerId: number;
    onClose: () => void;
    onInvite: (email: string) => void;
    inviteProcessing: boolean;
    inviteError: string;
    onRevokeMember: (userId: number) => void;
    onRevokeInvitation: (userId: number) => void;
    revokingIds: number[];
};

// Adapted from `notification-center.tsx`'s own `formatInvitedAt` — kept as a
// private copy here rather than a shared import, since that component is
// deliberately untouched by this step.
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

function ShareAvatar({ avatarUrl }: { avatarUrl: string | null }) {
    return (
        <span className="share-row__avatar">
            {avatarUrl ? <img alt="" src={avatarUrl} /> : <Icon name="user" size={15} />}
        </span>
    );
}

export function ShareDialog({
    open,
    list,
    viewerId,
    onClose,
    onInvite,
    inviteProcessing,
    inviteError,
    onRevokeMember,
    onRevokeInvitation,
    revokingIds,
}: ShareDialogProps) {
    const [email, setEmail] = useState('');
    const wasProcessing = useRef(false);

    // Clears the input once a send genuinely succeeded (a processing
    // true -> false transition with no error left behind) — no separate
    // "invite succeeded" callback is needed since this is derivable purely
    // from the two props the parent already passes.
    useEffect(() => {
        if (wasProcessing.current && !inviteProcessing && !inviteError) {
            setEmail('');
        }
        wasProcessing.current = inviteProcessing;
    }, [inviteProcessing, inviteError]);

    useEffect(() => {
        if (!open) {
            setEmail('');
        }
    }, [open]);

    if (!list) {
        return null;
    }

    const submitInvite = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        const trimmed = email.trim();

        if (!trimmed) {
            return;
        }

        onInvite(trimmed);
    };

    return (
        <Dialog
            description={`People you invite can see and edit every task in “${list.name}”.`}
            onClose={onClose}
            open={open}
            title={`Share “${list.name}”`}
        >
            <section aria-label="Members" className="share-section">
                <h3 className="share-section__heading">Members</h3>
                <ul className="share-list">
                    {list.members.map((member) => (
                        <li className="share-row" key={member.id}>
                            <ShareAvatar avatarUrl={member.avatarUrl} />
                            <div className="share-row__copy">
                                <p>
                                    <span>{member.name}{member.userId === viewerId ? ' (you)' : ''}</span>
                                    {member.isOwner && <span className="share-row__badge">Owner</span>}
                                </p>
                                {list.canManageSharing && member.email && <small>{member.email}</small>}
                            </div>
                            {list.canManageSharing && !member.isOwner && (
                                <Button
                                    disabled={revokingIds.includes(member.userId)}
                                    onClick={() => onRevokeMember(member.userId)}
                                    size="sm"
                                    variant="ghost"
                                >
                                    Remove
                                </Button>
                            )}
                        </li>
                    ))}
                </ul>
            </section>

            {/*
              * Pending invitations are owner-only, both server-side
              * (`WorkspacePresenter::currentList()` sends an empty array to
              * anyone else) and here: hiding the whole section rather than
              * rendering it empty for a non-owner, since an empty list would
              * itself imply "nobody's been invited" — also not something a
              * non-owner should be told either way (Plan 1, Step 10 code
              * review).
              */}
            {list.canManageSharing && (
                <section aria-label="Pending invitations" className="share-section">
                    <h3 className="share-section__heading">Pending invitations</h3>
                    {list.pendingInvitations.length === 0 ? (
                        <p className="share-section__empty">No invitations are waiting on a response.</p>
                    ) : (
                        <ul className="share-list">
                            {list.pendingInvitations.map((invitation) => (
                                <li className="share-row" key={invitation.id}>
                                    <ShareAvatar avatarUrl={invitation.avatarUrl} />
                                    <div className="share-row__copy">
                                        <p><span>{invitation.name}</span></p>
                                        <small>
                                            {invitation.email ? `${invitation.email} · ` : ''}
                                            {invitation.invitedAt ? `Invited ${formatInvitedAt(invitation.invitedAt)}` : 'Invited'}
                                        </small>
                                    </div>
                                    <Button
                                        disabled={revokingIds.includes(invitation.userId)}
                                        onClick={() => onRevokeInvitation(invitation.userId)}
                                        size="sm"
                                        variant="ghost"
                                    >
                                        Revoke
                                    </Button>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>
            )}

            {list.canManageSharing && (
                <form className="dialog-form share-invite-form" onSubmit={submitInvite}>
                    <label className="field-label" htmlFor="share-invite-email">Invite by email</label>
                    <input
                        aria-describedby={inviteError ? 'share-invite-error' : undefined}
                        aria-invalid={Boolean(inviteError)}
                        className="text-field"
                        id="share-invite-email"
                        onChange={(event) => setEmail(event.target.value)}
                        placeholder="name@example.com"
                        type="email"
                        value={email}
                    />
                    {inviteError && <p className="field-error" id="share-invite-error" role="alert">{inviteError}</p>}
                    <div className="dialog-actions">
                        <Button disabled={inviteProcessing || !email.trim()} type="submit" variant="primary">
                            {inviteProcessing ? 'Sending…' : 'Send invitation'}
                        </Button>
                    </div>
                </form>
            )}
        </Dialog>
    );
}
