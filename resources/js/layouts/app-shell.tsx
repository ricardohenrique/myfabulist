import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState, type FormEvent } from 'react';
import { Sidebar } from '@/components/navigation/sidebar';
import { TaskDetails } from '@/components/tasks/task-details';
import { TaskRow } from '@/components/tasks/task-row';
import { Button } from '@/components/ui/button';
import { Dialog } from '@/components/ui/dialog';
import { Icon } from '@/components/ui/icon';
import * as folderRoutes from '@/routes/folders';
import * as listRoutes from '@/routes/lists';
import { store as storeTask } from '@/routes/lists/tasks';
import { show as prototypeRoute } from '@/routes/prototype';
import * as taskRoutes from '@/routes/tasks';
import type {
    NavigationFolder,
    NavigationList,
    SharedPageProps,
    TaskSummary,
    UserSummary,
    WorkspaceData,
    WorkspaceView,
} from '@/types';

type Direction = 'up' | 'down';
type EntityDialog =
    | { kind: 'folder'; mode: 'create'; item?: undefined }
    | { kind: 'folder'; mode: 'edit'; item: NavigationFolder }
    | { kind: 'list'; mode: 'create'; item?: undefined }
    | { kind: 'list'; mode: 'edit'; item: NavigationList };
type DeleteDialog =
    | { kind: 'task'; item: TaskSummary }
    | { kind: 'folder'; item: NavigationFolder }
    | { kind: 'list'; item: NavigationList };
type UndoState = {
    message: string;
    execute: () => void;
};

type AppShellProps = {
    workspace: WorkspaceData;
    user: UserSummary;
    prototype?: boolean;
};

function swappedIds(items: { id: number }[], itemId: number, direction: Direction): number[] | null {
    const currentIndex = items.findIndex((item) => item.id === itemId);
    const targetIndex = direction === 'up' ? currentIndex - 1 : currentIndex + 1;

    if (currentIndex < 0 || targetIndex < 0 || targetIndex >= items.length) {
        return null;
    }

    const ids = items.map((item) => item.id);
    [ids[currentIndex], ids[targetIndex]] = [ids[targetIndex], ids[currentIndex]];

    return ids;
}

export function AppShell({ workspace, user, prototype = false }: AppShellProps) {
    const page = usePage<SharedPageProps>();
    const [previewTasks, setPreviewTasks] = useState(workspace.tasks);
    const [selectedTaskId, setSelectedTaskId] = useState<number | null>(() =>
        window.matchMedia('(min-width: 981px)').matches ? (workspace.tasks[0]?.id ?? null) : null,
    );
    const [completedOpen, setCompletedOpen] = useState(true);
    const [mobileNavOpen, setMobileNavOpen] = useState(false);
    const [entityDialog, setEntityDialog] = useState<EntityDialog | null>(null);
    const [entityName, setEntityName] = useState('');
    const [entityFolderId, setEntityFolderId] = useState<number | null>(null);
    const [entityError, setEntityError] = useState('');
    const [deleteDialog, setDeleteDialog] = useState<DeleteDialog | null>(null);
    const [folderDeleteStrategy, setFolderDeleteStrategy] = useState<'detach' | 'delete'>('detach');
    const [taskErrors, setTaskErrors] = useState<Record<string, string>>({});
    const [pendingTaskIds, setPendingTaskIds] = useState<number[]>([]);
    const [entityProcessing, setEntityProcessing] = useState(false);
    const [notice, setNotice] = useState('');
    const [undo, setUndo] = useState<UndoState | null>(null);
    const inputRef = useRef<HTMLInputElement>(null);
    const quickAdd = useForm({ title: '' });

    const tasks = prototype ? previewTasks : workspace.tasks;
    const activeTasks = tasks.filter((task) => !task.completedAt);
    const completedTasks = tasks.filter((task) => task.completedAt);
    const selectedTask = tasks.find((task) => task.id === selectedTaskId) ?? null;
    const destinationLists = useMemo(
        () => [workspace.inbox, ...workspace.folders.flatMap((folder) => folder.lists), ...workspace.ungroupedLists],
        [workspace],
    );

    useEffect(() => {
        if (prototype) {
            setPreviewTasks(workspace.tasks);
        }
    }, [prototype, workspace]);

    useEffect(() => {
        if (selectedTaskId !== null && !tasks.some((task) => task.id === selectedTaskId)) {
            setSelectedTaskId(null);
        }
    }, [selectedTaskId, tasks]);

    useEffect(() => {
        if (page.props.flash?.success) {
            setNotice(page.props.flash.success);
        } else if (page.props.flash?.error) {
            setNotice(page.props.flash.error);
        } else if (page.props.errors?.domain) {
            setNotice(page.props.errors.domain);
        }
    }, [page.props.errors, page.props.flash]);

    const setTaskPending = (taskId: number, pending: boolean) => {
        setPendingTaskIds((current) => pending
            ? [...new Set([...current, taskId])]
            : current.filter((id) => id !== taskId));
    };

    const mutationOptions = (taskId?: number) => ({
        preserveScroll: true,
        onFinish: () => {
            if (taskId !== undefined) setTaskPending(taskId, false);
        },
        onError: (errors: Record<string, string>) => setNotice(Object.values(errors)[0] ?? 'That change could not be saved.'),
    });

    const addTask = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        const title = quickAdd.data.title.trim();

        if (!title) {
            quickAdd.setError('title', 'Give your task a short title first.');
            inputRef.current?.focus();
            return;
        }

        if (prototype) {
            setPreviewTasks((current) => [...current, {
                id: Date.now(),
                title,
                note: null,
                dueDate: null,
                dueDateLabel: null,
                dueDateStatus: null,
                isStarred: false,
                completedAt: null,
                taskListId: workspace.currentList?.id ?? workspace.inbox.id,
                taskListName: workspace.currentList?.name ?? workspace.inbox.name,
            }]);
            quickAdd.reset('title');
            quickAdd.clearErrors();
            setNotice('Task added to this static preview.');
            inputRef.current?.focus();
            return;
        }

        if (!workspace.currentList) return;

        quickAdd.transform(() => ({ title }));
        quickAdd.post(storeTask.url(workspace.currentList.id), {
            preserveScroll: true,
            onSuccess: () => {
                quickAdd.reset('title');
                quickAdd.clearErrors();
                inputRef.current?.focus();
            },
            onError: () => inputRef.current?.focus(),
        });
    };

    const toggleComplete = (taskId: number) => {
        const task = tasks.find((item) => item.id === taskId);
        if (!task) return;

        if (prototype) {
            setPreviewTasks((current) => current.map((item) => item.id === taskId
                ? { ...item, completedAt: item.completedAt ? null : new Date().toISOString() }
                : item));
            setNotice('Task status changed in this static preview.');
            return;
        }

        setTaskPending(taskId, true);
        const completing = task.completedAt === null;
        const route = completing ? taskRoutes.complete(taskId) : taskRoutes.restore(taskId);

        router.post(route, {}, {
            ...mutationOptions(taskId),
            onSuccess: () => setUndo({
                message: completing ? `“${task.title}” completed.` : `“${task.title}” restored.`,
                execute: () => router.post(completing ? taskRoutes.restore(taskId) : taskRoutes.complete(taskId), {}, { preserveScroll: true }),
            }),
        });
    };

    const toggleStar = (taskId: number) => {
        const task = tasks.find((item) => item.id === taskId);
        if (!task) return;

        if (prototype) {
            setPreviewTasks((current) => current.map((item) => item.id === taskId ? { ...item, isStarred: !item.isStarred } : item));
            setNotice('Importance updated in this static preview.');
            return;
        }

        const nextStarred = !task.isStarred;
        setTaskPending(taskId, true);
        router.put(taskRoutes.star(taskId), { is_starred: nextStarred }, {
            ...mutationOptions(taskId),
            onSuccess: () => setUndo({
                message: nextStarred ? `“${task.title}” starred.` : `“${task.title}” unstarred.`,
                execute: () => router.put(taskRoutes.star(taskId), { is_starred: !nextStarred }, { preserveScroll: true }),
            }),
        });
    };

    const saveTask = (draft: TaskSummary) => {
        if (prototype) {
            setPreviewTasks((current) => current.map((task) => task.id === draft.id ? draft : task));
            setSelectedTaskId(null);
            setNotice('Task details saved to this static preview.');
            return;
        }

        const original = tasks.find((task) => task.id === draft.id);
        setTaskErrors({});
        setTaskPending(draft.id, true);
        router.put(taskRoutes.update(draft.id), {
            title: draft.title,
            note: draft.note,
            due_date: draft.dueDate,
            is_starred: draft.isStarred,
            task_list_id: draft.taskListId,
        }, {
            preserveScroll: true,
            onSuccess: () => {
                setSelectedTaskId(null);
                if (original && original.taskListId !== draft.taskListId) {
                    setUndo({
                        message: `“${draft.title}” moved to ${draft.taskListName}.`,
                        execute: () => router.post(taskRoutes.move(draft.id), { task_list_id: original.taskListId }, { preserveScroll: true }),
                    });
                }
            },
            onError: (errors) => setTaskErrors(errors),
            onFinish: () => setTaskPending(draft.id, false),
        });
    };

    const confirmDelete = () => {
        if (!deleteDialog) return;

        if (prototype) {
            if (deleteDialog.kind === 'task') {
                setPreviewTasks((current) => current.filter((task) => task.id !== deleteDialog.item.id));
                setSelectedTaskId(null);
            }
            setNotice(`${deleteDialog.kind} removed from this static preview.`);
            setDeleteDialog(null);
            return;
        }

        setEntityProcessing(true);
        const options = {
            preserveScroll: true,
            onSuccess: () => {
                setDeleteDialog(null);
                setSelectedTaskId(null);
            },
            onError: (errors: Record<string, string>) => setEntityError(Object.values(errors)[0] ?? 'This item could not be deleted.'),
            onFinish: () => setEntityProcessing(false),
        };

        if (deleteDialog.kind === 'task') {
            router.delete(taskRoutes.destroy(deleteDialog.item.id), options);
        } else if (deleteDialog.kind === 'list') {
            router.delete(listRoutes.destroy(deleteDialog.item.id), options);
        } else {
            router.delete(folderRoutes.destroy(deleteDialog.item.id), {
                ...options,
                data: deleteDialog.item.lists.length > 0 ? { lists: folderDeleteStrategy } : {},
            });
        }
    };

    const openCreate = (kind: 'folder' | 'list') => {
        setEntityDialog({ kind, mode: 'create' });
        setEntityName('');
        setEntityFolderId(null);
        setEntityError('');
    };

    const openEditFolder = (folder: NavigationFolder) => {
        setEntityDialog({ kind: 'folder', mode: 'edit', item: folder });
        setEntityName(folder.name);
        setEntityFolderId(null);
        setEntityError('');
    };

    const openEditList = (list: NavigationList) => {
        setEntityDialog({ kind: 'list', mode: 'edit', item: list });
        setEntityName(list.name);
        setEntityFolderId(list.folderId);
        setEntityError('');
    };

    const saveEntity = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        if (!entityDialog || !entityName.trim()) return;

        if (prototype) {
            setNotice(`${entityDialog.kind === 'folder' ? 'Folder' : 'List'} “${entityName.trim()}” saved to this static preview.`);
            setEntityDialog(null);
            return;
        }

        setEntityProcessing(true);
        setEntityError('');
        const data = entityDialog.kind === 'folder'
            ? { name: entityName.trim() }
            : { name: entityName.trim(), folder_id: entityFolderId };
        const options = {
            preserveScroll: true,
            onSuccess: () => setEntityDialog(null),
            onError: (errors: Record<string, string>) => setEntityError(Object.values(errors)[0] ?? 'This item could not be saved.'),
            onFinish: () => setEntityProcessing(false),
        };

        if (entityDialog.kind === 'folder') {
            if (entityDialog.mode === 'create') router.post(folderRoutes.store(), data, options);
            else router.put(folderRoutes.update(entityDialog.item.id), data, options);
        } else if (entityDialog.mode === 'create') {
            router.post(listRoutes.store(), data, options);
        } else {
            router.put(listRoutes.update(entityDialog.item.id), data, options);
        }
    };

    const reorderTask = (task: TaskSummary, direction: Direction) => {
        if (prototype || !workspace.currentList) {
            const ids = swappedIds(activeTasks, task.id, direction);
            if (!ids) return;
            setPreviewTasks((current) => [
                ...ids.map((id) => current.find((item) => item.id === id)).filter((item): item is TaskSummary => Boolean(item)),
                ...current.filter((item) => item.completedAt),
            ]);
            return;
        }

        const taskIds = swappedIds(activeTasks, task.id, direction);
        if (!taskIds) return;
        router.put(listRoutes.taskOrder(workspace.currentList.id), { task_ids: taskIds }, { preserveScroll: true });
    };

    const reorderFolder = (folder: NavigationFolder, direction: Direction) => {
        const folderIds = swappedIds(workspace.folders, folder.id, direction);
        if (!folderIds) return;
        if (prototype) return setNotice('Folder order changed in this static preview.');
        router.put(folderRoutes.order(), { folder_ids: folderIds }, { preserveScroll: true });
    };

    const reorderList = (list: NavigationList, siblings: NavigationList[], direction: Direction) => {
        const taskListIds = swappedIds(siblings, list.id, direction);
        if (!taskListIds) return;
        if (prototype) return setNotice('List order changed in this static preview.');
        router.put(listRoutes.order(), { folder_id: list.folderId, task_list_ids: taskListIds }, { preserveScroll: true });
    };

    return (
        <div className="app-frame">
            <Head title={`${workspace.heading} · My Fabulist`} />
            <Sidebar
                activeView={workspace.view}
                currentListId={workspace.currentList?.id ?? null}
                folders={workspace.folders}
                inbox={workspace.inbox}
                mobileOpen={mobileNavOpen}
                onCloseMobile={() => setMobileNavOpen(false)}
                onDeleteFolder={(folder) => { setEntityError(''); setDeleteDialog({ kind: 'folder', item: folder }); }}
                onDeleteList={(list) => { setEntityError(''); setDeleteDialog({ kind: 'list', item: list }); }}
                onEditFolder={openEditFolder}
                onEditList={openEditList}
                onOpenCreate={openCreate}
                onReorderFolder={reorderFolder}
                onReorderList={reorderList}
                prototype={prototype}
                starredCount={workspace.starredCount}
                ungroupedLists={workspace.ungroupedLists}
                user={user}
            />

            <main className="workspace">
                <header className="workspace-header">
                    <button aria-label="Open navigation" className="mobile-menu-button" onClick={() => setMobileNavOpen(true)} type="button">
                        <Icon name="menu" />
                    </button>
                    <div className="workspace-heading">
                        <span>{workspace.eyebrow}</span>
                        <h1>{workspace.heading}</h1>
                    </div>
                    <div className="workspace-actions">
                        <button aria-label="Search is not available yet" disabled type="button"><Icon name="search" size={19} /><span>Search</span></button>
                        <button aria-label="More list options are available in the sidebar" disabled type="button"><Icon name="more" size={19} /><span>More</span></button>
                    </div>
                </header>

                <div className="task-canvas">
                    <div className="task-column">
                        {workspace.canAddTask && (
                            <form className="task-composer" onSubmit={addTask}>
                                <Icon name="plus" size={21} />
                                <input
                                    aria-describedby={quickAdd.errors.title ? 'composer-error' : undefined}
                                    aria-invalid={Boolean(quickAdd.errors.title)}
                                    disabled={quickAdd.processing}
                                    onChange={(event) => { quickAdd.setData('title', event.target.value); quickAdd.clearErrors('title'); }}
                                    placeholder={`Add a to-do in “${workspace.heading}”…`}
                                    ref={inputRef}
                                    value={quickAdd.data.title}
                                />
                                <button aria-label="Add task" disabled={quickAdd.processing} type="submit"><span>{quickAdd.processing ? 'Adding…' : 'Enter'}</span><Icon name="chevron-right" size={18} /></button>
                            </form>
                        )}
                        {quickAdd.errors.title && <p className="composer-error" id="composer-error">{quickAdd.errors.title}</p>}

                        <section aria-label="Active tasks" className="task-list-section">
                            {activeTasks.length > 0 ? (
                                <div className="task-list">
                                    {activeTasks.map((task, index) => (
                                        <TaskRow
                                            key={task.id}
                                            moveDownDisabled={!workspace.currentList || index === activeTasks.length - 1}
                                            moveUpDisabled={!workspace.currentList || index === 0}
                                            onDelete={(item) => setDeleteDialog({ kind: 'task', item })}
                                            onMoveDown={(item) => reorderTask(item, 'down')}
                                            onMoveUp={(item) => reorderTask(item, 'up')}
                                            onSelect={(selected) => { setTaskErrors({}); setSelectedTaskId(selected.id); }}
                                            onToggleComplete={toggleComplete}
                                            onToggleStar={toggleStar}
                                            pending={pendingTaskIds.includes(task.id)}
                                            task={task}
                                        />
                                    ))}
                                </div>
                            ) : (
                                <div className="empty-state">
                                    <div className="empty-state__icon"><Icon name="check" size={34} /></div>
                                    <h2>{tasks.length === 0 ? (workspace.view === 'inbox' ? 'Your Inbox is clear.' : 'Nothing here yet.') : 'Everything is done.'}</h2>
                                    <p>{tasks.length === 0 ? (workspace.canAddTask ? 'Add your first task above whenever you’re ready.' : 'Star a task and it will appear here.') : 'That deserves a moment. Completed tasks are waiting below.'}</p>
                                </div>
                            )}
                        </section>

                        {completedTasks.length > 0 && (
                            <section className="completed-section">
                                <button aria-expanded={completedOpen} className="completed-toggle" onClick={() => setCompletedOpen((open) => !open)} type="button">
                                    <Icon className={completedOpen ? '' : 'is-collapsed'} name="chevron-down" size={17} />
                                    Completed <span>{workspace.completedCount || completedTasks.length}</span>
                                </button>
                                {completedOpen && (
                                    <div className="task-list completed-list">
                                        {completedTasks.map((task) => (
                                            <TaskRow
                                                completed
                                                key={task.id}
                                                onDelete={(item) => setDeleteDialog({ kind: 'task', item })}
                                                onSelect={(selected) => { setTaskErrors({}); setSelectedTaskId(selected.id); }}
                                                onToggleComplete={toggleComplete}
                                                onToggleStar={toggleStar}
                                                pending={pendingTaskIds.includes(task.id)}
                                                task={task}
                                            />
                                        ))}
                                    </div>
                                )}
                            </section>
                        )}
                    </div>
                </div>
            </main>

            {selectedTask && (
                <TaskDetails
                    errors={taskErrors}
                    lists={destinationLists}
                    onClose={() => setSelectedTaskId(null)}
                    onDelete={() => setDeleteDialog({ kind: 'task', item: selectedTask })}
                    onSave={saveTask}
                    processing={pendingTaskIds.includes(selectedTask.id)}
                    task={selectedTask}
                />
            )}

            {(undo || notice) && (
                <div className="undo-bar" role="status">
                    <span>{undo?.message ?? notice}</span>
                    <button onClick={() => { setUndo(null); setNotice(''); }} type="button">Dismiss</button>
                    {undo && <button onClick={() => { undo.execute(); setUndo(null); }} type="button">Undo</button>}
                </div>
            )}

            <Dialog
                description={entityDialog?.kind === 'list' ? 'Lists can stay ungrouped or live inside a folder.' : 'Folders keep related lists together.'}
                onClose={() => setEntityDialog(null)}
                open={entityDialog !== null}
                title={`${entityDialog?.mode === 'edit' ? 'Edit' : 'Create'} ${entityDialog?.kind ?? 'item'}`}
            >
                <form className="dialog-form" onSubmit={saveEntity}>
                    <label className="field-label" htmlFor="entity-name">Name</label>
                    <input autoFocus aria-invalid={Boolean(entityError)} className="text-field" id="entity-name" onChange={(event) => setEntityName(event.target.value)} placeholder={entityDialog?.kind === 'folder' ? 'e.g. Work' : 'e.g. Website launch'} value={entityName} />
                    {entityDialog?.kind === 'list' && (
                        <>
                            <label className="field-label" htmlFor="entity-folder">Folder</label>
                            <select className="text-field" id="entity-folder" onChange={(event) => setEntityFolderId(event.target.value ? Number(event.target.value) : null)} value={entityFolderId ?? ''}>
                                <option value="">No folder</option>
                                {workspace.folders.map((folder) => <option key={folder.id} value={folder.id}>{folder.name}</option>)}
                            </select>
                        </>
                    )}
                    {entityError && <p className="field-error" role="alert">{entityError}</p>}
                    <div className="dialog-actions">
                        <Button disabled={entityProcessing} onClick={() => setEntityDialog(null)} variant="ghost">Cancel</Button>
                        <Button disabled={entityProcessing || !entityName.trim()} type="submit" variant="primary">{entityProcessing ? 'Saving…' : 'Save'}</Button>
                    </div>
                </form>
            </Dialog>

            <Dialog
                description={deleteDialog?.kind === 'folder' && deleteDialog.item.lists.length > 0
                    ? 'Choose what should happen to the lists inside this folder.'
                    : 'This action cannot be undone from the current interface.'}
                onClose={() => setDeleteDialog(null)}
                open={deleteDialog !== null}
                title={`Delete this ${deleteDialog?.kind ?? 'item'}?`}
            >
                <p className="dialog-body-copy">{deleteDialog ? (deleteDialog.kind === 'task' ? deleteDialog.item.title : deleteDialog.item.name) : ''}</p>
                {deleteDialog?.kind === 'folder' && deleteDialog.item.lists.length > 0 && (
                    <div className="delete-options">
                        <label><input checked={folderDeleteStrategy === 'detach'} onChange={() => setFolderDeleteStrategy('detach')} type="radio" />Keep lists and move them out of the folder</label>
                        <label><input checked={folderDeleteStrategy === 'delete'} onChange={() => setFolderDeleteStrategy('delete')} type="radio" />Delete the folder and all of its lists</label>
                    </div>
                )}
                {entityError && <p className="field-error" role="alert">{entityError}</p>}
                <div className="dialog-actions">
                    <Button disabled={entityProcessing} onClick={() => setDeleteDialog(null)} variant="ghost">Keep it</Button>
                    <Button disabled={entityProcessing} onClick={confirmDelete} variant="danger">{entityProcessing ? 'Deleting…' : 'Delete'}</Button>
                </div>
            </Dialog>

            {prototype && (
                <nav aria-label="Prototype states" className="prototype-switcher">
                    <span>Preview</span>
                    {(['inbox', 'list', 'starred', 'empty', 'complete'] as WorkspaceView[]).map((state) => (
                        <Link className={workspace.view === state ? 'is-active' : ''} href={prototypeRoute(state)} key={state}>{state}</Link>
                    ))}
                </nav>
            )}
        </div>
    );
}
