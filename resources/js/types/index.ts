export type WorkspaceView = 'inbox' | 'list' | 'starred';

export type UserSummary = {
    id: number;
    name: string;
    email: string;
    avatarUrl: string | null;
};

export type NavigationList = {
    id: number;
    name: string;
    folderId: number | null;
    isDefault: boolean;
    activeTaskCount: number;
    isShared: boolean;
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

export type CurrentListDetails = NavigationList & {
    members: ListMemberSummary[];
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
    flash: {
        success: string | null;
        error: string | null;
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
