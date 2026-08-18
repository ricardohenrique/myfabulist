import { router, type InertiaForm } from '@inertiajs/react';
import { useEffect, useState, type FormEvent } from 'react';
import { WorkspaceBackgroundSection } from '@/components/settings/workspace-background-section';
import { Button } from '@/components/ui/button';
import { Dialog } from '@/components/ui/dialog';
import { send as sendEmailVerification } from '@/routes/verification';
import type { UserSummary, WorkspaceBackground, WorkspaceBackgroundOptionSummary } from '@/types';

export type ProfileFormData = {
    name: string;
    email: string;
};

export type PasswordFormData = {
    current_password: string;
    password: string;
    password_confirmation: string;
};

type ProfileTabId = 'profile' | 'background';

type ProfileTab = {
    id: ProfileTabId;
    label: string;
};

// A small, ordered tab config rather than hard-coded markup — a third
// personalization tab (more are coming) means adding one entry here and one
// branch in `renderPanel`, not restructuring the dialog again.
const PROFILE_TABS: readonly ProfileTab[] = [
    { id: 'profile', label: 'Profile' },
    { id: 'background', label: 'Workspace background' },
];

type ProfileSettingsDialogProps = {
    open: boolean;
    onClose: () => void;
    successMessage: string;
    profileForm: InertiaForm<ProfileFormData>;
    passwordForm: InertiaForm<PasswordFormData>;
    onSaveProfile: (event: FormEvent<HTMLFormElement>) => void;
    onSavePassword: (event: FormEvent<HTMLFormElement>) => void;
    currentBackground: WorkspaceBackground | null;
    backgroundOptions: WorkspaceBackgroundOptionSummary[];
    hasPassword: UserSummary['hasPassword'];
    email: UserSummary['email'];
    emailVerified: UserSummary['emailVerified'];
};

export function ProfileSettingsDialog({
    open,
    onClose,
    successMessage,
    profileForm,
    passwordForm,
    onSaveProfile,
    onSavePassword,
    currentBackground,
    backgroundOptions,
    hasPassword,
    email,
    emailVerified,
}: ProfileSettingsDialogProps) {
    const [activeTab, setActiveTab] = useState<ProfileTabId>('profile');
    const [verificationSending, setVerificationSending] = useState(false);
    const [verificationSent, setVerificationSent] = useState(false);

    // Every time the dialog opens, land back on the first tab — a stale
    // "Workspace background" tab left active from a previous visit would be
    // a surprising starting point next time the user opens their settings.
    useEffect(() => {
        if (open) {
            setActiveTab('profile');
        }
    }, [open]);

    useEffect(() => {
        setVerificationSent(false);
    }, [email, emailVerified]);

    const resendVerification = () => {
        router.post(sendEmailVerification.url(), {}, {
            preserveScroll: true,
            onStart: () => setVerificationSending(true),
            onSuccess: () => setVerificationSent(true),
            onFinish: () => setVerificationSending(false),
        });
    };

    return (
        <Dialog
            description="Update your account details without leaving your tasks."
            onClose={onClose}
            open={open}
            panelClassName="dialog-panel--profile"
            title="Profile settings"
        >
            {successMessage && <p className="profile-modal__success" role="status">{successMessage}</p>}

            <div className="profile-modal__layout">
                <div aria-label="Profile settings tabs" className="profile-modal__tablist" role="tablist">
                    {PROFILE_TABS.map((tab) => (
                        <button
                            aria-controls={`profile-tabpanel-${tab.id}`}
                            aria-selected={activeTab === tab.id}
                            className={`profile-modal__tab ${activeTab === tab.id ? 'is-active' : ''}`}
                            id={`profile-tab-${tab.id}`}
                            key={tab.id}
                            onClick={() => setActiveTab(tab.id)}
                            role="tab"
                            type="button"
                        >
                            {tab.label}
                        </button>
                    ))}
                </div>

                <div className="profile-modal__panels">
                    {/* Both panels stay mounted at all times — only `hidden` toggles
                        with the active tab — so switching tabs never unmounts (and
                        therefore never resets) the workspace-background section's
                        own in-progress `useForm` state. */}
                    <div
                        aria-labelledby="profile-tab-profile"
                        className="profile-modal__panel"
                        hidden={activeTab !== 'profile'}
                        id="profile-tabpanel-profile"
                        role="tabpanel"
                    >
                        <section aria-labelledby="profile-details-heading" className="profile-modal__section">
                            <div className="profile-modal__section-heading">
                                <h3 id="profile-details-heading">Personal details</h3>
                                <p>Change the name and email used for your account.</p>
                            </div>

                            <form className="profile-modal__form" noValidate onSubmit={onSaveProfile}>
                                <label className="field-label" htmlFor="profile-name">Name</label>
                                <input
                                    aria-invalid={Boolean(profileForm.errors.name)}
                                    autoComplete="name"
                                    className="text-field"
                                    id="profile-name"
                                    onChange={(event) => profileForm.setData('name', event.target.value)}
                                    value={profileForm.data.name}
                                />
                                {profileForm.errors.name && <p className="field-error">{profileForm.errors.name}</p>}

                                <label className="field-label" htmlFor="profile-email">Email address</label>
                                <input
                                    aria-invalid={Boolean(profileForm.errors.email)}
                                    autoComplete="email"
                                    className="text-field"
                                    id="profile-email"
                                    onChange={(event) => profileForm.setData('email', event.target.value)}
                                    type="email"
                                    value={profileForm.data.email}
                                />
                                {profileForm.errors.email && <p className="field-error">{profileForm.errors.email}</p>}

                                <div className="profile-email-status">
                                    <span className={emailVerified ? 'is-verified' : ''}>
                                        {emailVerified ? 'Email confirmed' : verificationSent ? 'Confirmation sent' : 'Email not confirmed'}
                                    </span>
                                    {!emailVerified && (
                                        <button disabled={verificationSending} onClick={resendVerification} type="button">
                                            {verificationSending ? 'Sending…' : 'Resend email'}
                                        </button>
                                    )}
                                </div>

                                <div className="profile-modal__actions">
                                    <Button disabled={profileForm.processing} type="submit" variant="primary">
                                        {profileForm.processing ? 'Saving…' : 'Save profile'}
                                    </Button>
                                </div>
                            </form>
                        </section>

                        <section aria-labelledby="profile-password-heading" className="profile-modal__section">
                            <div className="profile-modal__section-heading">
                                <h3 id="profile-password-heading">{hasPassword ? 'Change password' : 'Set a password'}</h3>
                                <p>
                                    {hasPassword
                                        ? 'Confirm your current password before choosing a new one.'
                                        : 'Add a password if you also want to sign in with your email address.'}
                                </p>
                            </div>

                            <form className="profile-modal__form" noValidate onSubmit={onSavePassword}>
                                {hasPassword && (
                                    <>
                                        <label className="field-label" htmlFor="current-password">Current password</label>
                                        <input
                                            aria-invalid={Boolean(passwordForm.errors.current_password)}
                                            autoComplete="current-password"
                                            className="text-field"
                                            id="current-password"
                                            onChange={(event) => passwordForm.setData('current_password', event.target.value)}
                                            type="password"
                                            value={passwordForm.data.current_password}
                                        />
                                        {passwordForm.errors.current_password && <p className="field-error">{passwordForm.errors.current_password}</p>}
                                    </>
                                )}

                                <label className="field-label" htmlFor="new-password">New password</label>
                                <input
                                    aria-invalid={Boolean(passwordForm.errors.password)}
                                    autoComplete="new-password"
                                    className="text-field"
                                    id="new-password"
                                    onChange={(event) => passwordForm.setData('password', event.target.value)}
                                    placeholder="At least 8 characters"
                                    type="password"
                                    value={passwordForm.data.password}
                                />
                                {passwordForm.errors.password && <p className="field-error">{passwordForm.errors.password}</p>}

                                <label className="field-label" htmlFor="new-password-confirmation">Confirm new password</label>
                                <input
                                    autoComplete="new-password"
                                    className="text-field"
                                    id="new-password-confirmation"
                                    onChange={(event) => passwordForm.setData('password_confirmation', event.target.value)}
                                    type="password"
                                    value={passwordForm.data.password_confirmation}
                                />

                                <div className="profile-modal__actions">
                                    <Button disabled={passwordForm.processing} type="submit" variant="primary">
                                        {passwordForm.processing ? 'Saving…' : hasPassword ? 'Update password' : 'Set password'}
                                    </Button>
                                </div>
                            </form>
                        </section>
                    </div>

                    <div
                        aria-labelledby="profile-tab-background"
                        className="profile-modal__panel"
                        hidden={activeTab !== 'background'}
                        id="profile-tabpanel-background"
                        role="tabpanel"
                    >
                        <WorkspaceBackgroundSection
                            currentBackground={currentBackground}
                            onSaved={onClose}
                            options={backgroundOptions}
                        />
                    </div>
                </div>
            </div>
        </Dialog>
    );
}
