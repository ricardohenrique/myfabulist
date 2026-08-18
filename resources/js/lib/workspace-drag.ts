export type FolderDragData = {
    kind: 'folder';
    folderId: number;
};

export type ListDragData = {
    kind: 'list';
    listId: number;
    folderId: number | null;
    name: string;
};

export type TaskDragData = {
    kind: 'task';
    taskId: number;
    taskListId: number;
    title: string;
    canMoveAcrossLists: boolean;
};

export type ListContainerDragData = {
    kind: 'list-container';
    folderId: number | null;
};

export type WorkspaceDragData = FolderDragData | ListDragData | TaskDragData | ListContainerDragData;

export function workspaceDragData(entity: { data?: Record<string, unknown> } | null | undefined): WorkspaceDragData | null {
    const data = entity?.data;

    if (!data || typeof data.kind !== 'string') {
        return null;
    }

    return data as WorkspaceDragData;
}
