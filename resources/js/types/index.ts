export type WorkspaceView = 'inbox' | 'list' | 'starred';

export type OnboardingUseCase =
    | 'personal_tasks'
    | 'work'
    | 'school_studies'
    | 'household_family'
    | 'projects'
    | 'everything';

export type OnboardingUseCaseOption = {
    value: OnboardingUseCase;
    label: string;
};

export type WorkspaceBackgroundType = 'flat_color' | 'image' | 'gradient';

// Which keys are present depends on `type` (color for flat_color, url for
// image, from/to for gradient) — kept as a loose string map rather than a
// discriminated union so a future 4th type never requires widening this.
export type WorkspaceBackgroundConfig = Record<string, string>;

export type WorkspaceBackground = {
    optionKey: string;
    type: WorkspaceBackgroundType;
    config: WorkspaceBackgroundConfig;
    isCustomized: boolean;
};

export type WorkspaceBackgroundOptionSummary = {
    key: string;
    type: WorkspaceBackgroundType;
    label: string;
    defaultConfig: WorkspaceBackgroundConfig | null;
    isDefault: boolean;
};

export type UserSummary = {
    id: number;
    name: string;
    email: string;
    avatarUrl: string | null;
    hasPassword: boolean;
    emailVerified: boolean;
    workspaceBackground: WorkspaceBackground | null;
};

export type NavigationList = {
    id: number;
    name: string;
    folderId: number | null;
    isDefault: boolean;
    activeTaskCount: number;
    isShared: boolean;
    isOwner: boolean;
};

export type NavigationFolder = {
    id: number;
    name: string;
    lists: NavigationList[];
};

export type ListMemberSummary = {
    id: number;
    userId: number;
    name: string;
    avatarUrl: string | null;
    email: string | null;
    isOwner: boolean;
};

// Distinct from `PendingInvitationSummary` below, which is shaped for the
// invitee's own notification-center view ("what have I been invited to").
// This is the owner-facing shape used by the share dialog's roster
// ("who have I invited to this list").
export type PendingListInvitationSummary = {
    id: number;
    userId: number;
    name: string;
    avatarUrl: string | null;
    email: string | null;
    invitedAt: string | null;
};

export type CurrentListDetails = NavigationList & {
    members: ListMemberSummary[];
    pendingInvitations: PendingListInvitationSummary[];
    isOwner: boolean;
    canManageSharing: boolean;
};

export type PendingInvitationSummary = {
    id: number;
    list: { id: number; name: string };
    invitedBy: { id: number; name: string; avatarUrl: string | null } | null;
    invitedAt: string | null;
};

export type DueDateStatus = 'overdue' | 'today' | 'upcoming' | null;

export type TaskCommentSummary = {
    id: number;
    body: string;
    author: {
        id: number;
        name: string;
        avatarUrl: string | null;
    };
    createdAt: string;
};

export type SubtaskSummary = {
    id: number;
    title: string;
    isCompleted: boolean;
    createdAt: string;
};

export type TaskSummary = {
    id: number;
    title: string;
    note: string | null;
    dueDate: string | null;
    dueDateLabel: string | null;
    dueDateStatus: DueDateStatus;
    isStarred: boolean;
    completedAt: string | null;
    createdAt: string;
    taskListId: number;
    taskListName: string;
    subtasks: SubtaskSummary[];
    comments: TaskCommentSummary[];
};

export type WorkspaceData = {
    view: WorkspaceView;
    inbox: NavigationList;
    starredCount: number;
    folders: NavigationFolder[];
    ungroupedLists: NavigationList[];
    currentList: CurrentListDetails | null;
    heading: string;
    eyebrow: string;
    canAddTask: boolean;
    tasks: TaskSummary[];
    completedCount: number;
};

export type SharedPageProps = {
    appName: string;
    auth: {
        user: UserSummary | null;
    };
    onboarding: {
        pending: boolean;
        useCaseOptions: OnboardingUseCaseOption[];
    };
    flash: {
        success: string | null;
        error: string | null;
        analyticsEvent: {
            name: 'login' | 'sign_up';
            method: 'google';
        } | null;
    };
    errors: Record<string, string>;
    notifications: {
        pendingInvitationCount: number;
        // Absent unless explicitly requested via Inertia::optional() — a
        // partial reload naming 'notifications' (the parent prop key, not
        // the leaf 'notifications.invitations' path) — see
        // `app-shell.tsx`'s `openNotifications` (Step 9).
        invitations?: PendingInvitationSummary[];
    };
    sharingDialog?: CurrentListDetails | null;
    workspaceBackgroundOptions: WorkspaceBackgroundOptionSummary[];
};

export type IconName =
    | 'bell'
    | 'calendar'
    | 'check'
    | 'chevron-down'
    | 'chevron-left'
    | 'chevron-right'
    | 'circle'
    | 'close'
    | 'comment'
    | 'folder'
    | 'inbox'
    | 'list'
    | 'menu'
    | 'more'
    | 'note'
    | 'plus'
    | 'search'
    | 'star'
    | 'trash'
    | 'user';
