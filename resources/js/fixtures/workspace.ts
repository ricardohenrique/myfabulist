import type {
    NavigationFolder,
    NavigationList,
    TaskSummary,
    WorkspaceFixture,
    WorkspaceView,
} from '@/types';

const inbox: NavigationList = {
    id: 1,
    name: 'Inbox',
    folderId: null,
    isDefault: true,
    activeTaskCount: 4,
};

const folders: NavigationFolder[] = [
    {
        id: 10,
        name: 'Work',
        lists: [
            {
                id: 11,
                name: 'Website launch',
                folderId: 10,
                isDefault: false,
                activeTaskCount: 4,
            },
            {
                id: 12,
                name: 'Content calendar',
                folderId: 10,
                isDefault: false,
                activeTaskCount: 8,
            },
            {
                id: 13,
                name: 'Ideas to explore later',
                folderId: 10,
                isDefault: false,
                activeTaskCount: 2,
            },
        ],
    },
    {
        id: 20,
        name: 'Personal',
        lists: [
            {
                id: 21,
                name: 'Home',
                folderId: 20,
                isDefault: false,
                activeTaskCount: 3,
            },
            {
                id: 22,
                name: 'Reading list',
                folderId: 20,
                isDefault: false,
                activeTaskCount: 12,
            },
        ],
    },
];

const ungroupedLists: NavigationList[] = [
    {
        id: 30,
        name: 'Groceries',
        folderId: null,
        isDefault: false,
        activeTaskCount: 5,
    },
];

const inboxTasks: TaskSummary[] = [
    {
        id: 101,
        title: 'Review the launch checklist with the team',
        note: 'Focus on copy, final QA, and the production handoff.',
        dueDate: '2026-08-11',
        dueDateLabel: 'Today',
        dueDateStatus: 'today',
        isStarred: true,
        completedAt: null,
        taskListId: 1,
        taskListName: 'Inbox',
    },
    {
        id: 102,
        title: 'Book a table for Friday evening',
        note: null,
        dueDate: '2026-08-14',
        dueDateLabel: 'Fri',
        dueDateStatus: 'upcoming',
        isStarred: false,
        completedAt: null,
        taskListId: 1,
        taskListName: 'Inbox',
    },
    {
        id: 103,
        title: 'Send the project notes to Ricardo',
        note: 'Include the updated component inventory and the open questions.',
        dueDate: '2026-08-10',
        dueDateLabel: 'Yesterday',
        dueDateStatus: 'overdue',
        isStarred: false,
        completedAt: null,
        taskListId: 1,
        taskListName: 'Inbox',
    },
    {
        id: 104,
        title: 'Pick up coffee beans',
        note: null,
        dueDate: null,
        dueDateLabel: null,
        dueDateStatus: null,
        isStarred: false,
        completedAt: null,
        taskListId: 1,
        taskListName: 'Inbox',
    },
    {
        id: 105,
        title: 'Reply to the venue',
        note: null,
        dueDate: null,
        dueDateLabel: null,
        dueDateStatus: null,
        isStarred: false,
        completedAt: '2026-08-11T08:30:00Z',
        taskListId: 1,
        taskListName: 'Inbox',
    },
    {
        id: 106,
        title: 'Archive last month’s receipts',
        note: 'The digital copies are already in the shared folder.',
        dueDate: null,
        dueDateLabel: null,
        dueDateStatus: null,
        isStarred: false,
        completedAt: '2026-08-10T17:20:00Z',
        taskListId: 1,
        taskListName: 'Inbox',
    },
];

const projectTasks: TaskSummary[] = [
    {
        id: 201,
        title: 'Finalize responsive navigation states',
        note: 'Check narrow tablet widths as well as the smallest phone layout.',
        dueDate: '2026-08-11',
        dueDateLabel: 'Today',
        dueDateStatus: 'today',
        isStarred: true,
        completedAt: null,
        taskListId: 11,
        taskListName: 'Website launch',
    },
    {
        id: 202,
        title: 'Polish registration and login copy',
        note: 'Keep it warm and concise. The product should feel helpful, not corporate.',
        dueDate: '2026-08-12',
        dueDateLabel: 'Tomorrow',
        dueDateStatus: 'upcoming',
        isStarred: false,
        completedAt: null,
        taskListId: 11,
        taskListName: 'Website launch',
    },
    {
        id: 203,
        title: 'Prepare the release notes',
        note: null,
        dueDate: null,
        dueDateLabel: null,
        dueDateStatus: null,
        isStarred: false,
        completedAt: null,
        taskListId: 11,
        taskListName: 'Website launch',
    },
    {
        id: 204,
        title: 'Verify empty and error states',
        note: null,
        dueDate: null,
        dueDateLabel: null,
        dueDateStatus: null,
        isStarred: true,
        completedAt: null,
        taskListId: 11,
        taskListName: 'Website launch',
    },
    {
        id: 205,
        title: 'Approve the favicon direction',
        note: null,
        dueDate: null,
        dueDateLabel: null,
        dueDateStatus: null,
        isStarred: false,
        completedAt: '2026-08-11T10:15:00Z',
        taskListId: 11,
        taskListName: 'Website launch',
    },
];

const allTasks = [...inboxTasks, ...projectTasks];

export function workspaceFixture(view: WorkspaceView): WorkspaceFixture {
    const currentList = view === 'list' ? folders[0].lists[0] : inbox;
    let tasks = view === 'list' ? projectTasks : inboxTasks;
    let heading = currentList.name;
    let eyebrow = currentList.isDefault ? 'Quick capture' : 'Work';

    if (view === 'starred') {
        tasks = allTasks.filter((task) => task.isStarred && !task.completedAt);
        heading = 'Starred';
        eyebrow = 'Important tasks';
    }

    if (view === 'empty') {
        tasks = [];
        heading = 'Groceries';
        eyebrow = 'Ungrouped list';
    }

    if (view === 'complete') {
        tasks = projectTasks.map((task, index) => ({
            ...task,
            completedAt: task.completedAt ?? `2026-08-11T0${index + 7}:00:00Z`,
        }));
        heading = 'Website launch';
        eyebrow = 'Work';
    }

    return {
        user: {
            id: 1,
            name: 'Ricardo Mota',
            email: 'ricardo@example.com',
            avatarUrl: null,
        },
        inbox,
        starredCount: allTasks.filter((task) => task.isStarred && !task.completedAt).length,
        folders,
        ungroupedLists,
        currentList: view === 'starred' ? null : currentList,
        heading,
        eyebrow,
        tasks: structuredClone(tasks),
    };
}
