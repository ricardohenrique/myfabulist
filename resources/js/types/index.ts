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
};

export type NavigationFolder = {
    id: number;
    name: string;
    lists: NavigationList[];
};

export type DueDateStatus = 'overdue' | 'today' | 'upcoming' | null;

export type TaskSummary = {
    id: number;
    title: string;
    note: string | null;
    dueDate: string | null;
    dueDateLabel: string | null;
    dueDateStatus: DueDateStatus;
    isStarred: boolean;
    completedAt: string | null;
    taskListId: number;
    taskListName: string;
};

export type WorkspaceData = {
    view: WorkspaceView;
    inbox: NavigationList;
    starredCount: number;
    folders: NavigationFolder[];
    ungroupedLists: NavigationList[];
    currentList: NavigationList | null;
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
    | 'folder'
    | 'grip'
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
