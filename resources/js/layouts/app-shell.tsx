import { Head, Link } from '@inertiajs/react';
import { useMemo, useRef, useState, type FormEvent } from 'react';
import { Sidebar } from '@/components/navigation/sidebar';
import { TaskDetails } from '@/components/tasks/task-details';
import { TaskRow } from '@/components/tasks/task-row';
import { Button } from '@/components/ui/button';
import { Dialog } from '@/components/ui/dialog';
import { Icon } from '@/components/ui/icon';
import { show as prototype } from '@/routes/prototype';
import type { TaskSummary, WorkspaceFixture, WorkspaceView } from '@/types';

type AppShellProps = {
    fixture: WorkspaceFixture;
    view: WorkspaceView;
};

export function AppShell({ fixture, view }: AppShellProps) {
    const [tasks, setTasks] = useState(fixture.tasks);
    const [selectedTaskId, setSelectedTaskId] = useState<number | null>(() =>
        window.matchMedia('(min-width: 981px)').matches ? (fixture.tasks[0]?.id ?? null) : null,
    );
    const [completedOpen, setCompletedOpen] = useState(true);
    const [mobileNavOpen, setMobileNavOpen] = useState(false);
    const [newTaskTitle, setNewTaskTitle] = useState('');
    const [composerError, setComposerError] = useState('');
    const [createKind, setCreateKind] = useState<'folder' | 'list' | null>(null);
    const [createName, setCreateName] = useState('');
    const [deleteTask, setDeleteTask] = useState<TaskSummary | null>(null);
    const [undoMessage, setUndoMessage] = useState('');
    const inputRef = useRef<HTMLInputElement>(null);

    const activeTasks = tasks.filter((task) => !task.completedAt);
    const completedTasks = tasks.filter((task) => task.completedAt);
    const selectedTask = tasks.find((task) => task.id === selectedTaskId) ?? null;
    const destinationLists = useMemo(
        () => [fixture.inbox, ...fixture.folders.flatMap((folder) => folder.lists), ...fixture.ungroupedLists],
        [fixture],
    );

    const addTask = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        const title = newTaskTitle.trim();
        if (!title) {
            setComposerError('Give your task a short title first.');
            inputRef.current?.focus();
            return;
        }

        const task: TaskSummary = {
            id: Date.now(),
            title,
            note: null,
            dueDate: null,
            dueDateLabel: null,
            dueDateStatus: null,
            isStarred: false,
            completedAt: null,
            taskListId: fixture.currentList?.id ?? fixture.inbox.id,
            taskListName: fixture.currentList?.name ?? fixture.inbox.name,
        };

        setTasks((current) => [...current, task]);
        setNewTaskTitle('');
        setComposerError('');
        setUndoMessage('Task added to this static preview.');
        inputRef.current?.focus();
    };

    const toggleComplete = (taskId: number) => {
        setTasks((current) => current.map((task) => task.id === taskId
            ? { ...task, completedAt: task.completedAt ? null : new Date().toISOString() }
            : task));
        setUndoMessage('Task status changed. Undo is demonstrated in Phase 1 only.');
    };

    const toggleStar = (taskId: number) => {
        setTasks((current) => current.map((task) => task.id === taskId ? { ...task, isStarred: !task.isStarred } : task));
        setUndoMessage('Importance updated.');
    };

    const saveTask = (draft: TaskSummary) => {
        setTasks((current) => current.map((task) => task.id === draft.id ? draft : task));
        setSelectedTaskId(null);
        setUndoMessage('Task details saved to the static preview.');
    };

    const confirmDelete = () => {
        if (!deleteTask) return;
        setTasks((current) => current.filter((task) => task.id !== deleteTask.id));
        if (selectedTaskId === deleteTask.id) setSelectedTaskId(null);
        setUndoMessage(`“${deleteTask.title}” removed from this preview.`);
        setDeleteTask(null);
    };

    return (
        <div className="app-frame">
            <Head title={`${fixture.heading} · My Fabulist`} />
            <Sidebar
                activeView={view}
                folders={fixture.folders}
                inbox={fixture.inbox}
                mobileOpen={mobileNavOpen}
                onCloseMobile={() => setMobileNavOpen(false)}
                onOpenCreate={setCreateKind}
                starredCount={fixture.starredCount}
                ungroupedLists={fixture.ungroupedLists}
                user={fixture.user}
            />

            <main className="workspace">
                <header className="workspace-header">
                    <button aria-label="Open navigation" className="mobile-menu-button" onClick={() => setMobileNavOpen(true)} type="button">
                        <Icon name="menu" />
                    </button>
                    <div className="workspace-heading">
                        <span>{fixture.eyebrow}</span>
                        <h1>{fixture.heading}</h1>
                    </div>
                    <div className="workspace-actions">
                        <button aria-label="Search this list" type="button"><Icon name="search" size={19} /><span>Search</span></button>
                        <button aria-label="List options" type="button"><Icon name="more" size={19} /><span>More</span></button>
                    </div>
                </header>

                <div className="task-canvas">
                    <div className="task-column">
                        <form className="task-composer" onSubmit={addTask}>
                            <Icon name="plus" size={21} />
                            <input
                                aria-describedby={composerError ? 'composer-error' : undefined}
                                aria-invalid={Boolean(composerError)}
                                onChange={(event) => setNewTaskTitle(event.target.value)}
                                placeholder={`Add a to-do in “${fixture.heading}”…`}
                                ref={inputRef}
                                value={newTaskTitle}
                            />
                            <button aria-label="Add task" type="submit"><span>Enter</span><Icon name="chevron-right" size={18} /></button>
                        </form>
                        {composerError && <p className="composer-error" id="composer-error">{composerError}</p>}

                        <section aria-label="Active tasks" className="task-list-section">
                            {activeTasks.length > 0 ? (
                                <div className="task-list">
                                    {activeTasks.map((task) => (
                                        <TaskRow
                                            key={task.id}
                                            onSelect={(selected) => setSelectedTaskId(selected.id)}
                                            onToggleComplete={toggleComplete}
                                            onToggleStar={toggleStar}
                                            task={task}
                                        />
                                    ))}
                                </div>
                            ) : (
                                <div className="empty-state">
                                    <div className="empty-state__icon"><Icon name="check" size={34} /></div>
                                    <h2>{tasks.length === 0 ? (view === 'inbox' ? 'Your Inbox is clear.' : 'Nothing here yet.') : 'Everything is done.'}</h2>
                                    <p>{tasks.length === 0 ? 'Add your first task above whenever you’re ready.' : 'That deserves a moment. Completed tasks are waiting below.'}</p>
                                </div>
                            )}
                        </section>

                        {completedTasks.length > 0 && (
                            <section className="completed-section">
                                <button
                                    aria-expanded={completedOpen}
                                    className="completed-toggle"
                                    onClick={() => setCompletedOpen((open) => !open)}
                                    type="button"
                                >
                                    <Icon className={completedOpen ? '' : 'is-collapsed'} name="chevron-down" size={17} />
                                    Completed <span>{completedTasks.length}</span>
                                </button>
                                {completedOpen && (
                                    <div className="task-list completed-list">
                                        {completedTasks.map((task) => (
                                            <TaskRow
                                                completed
                                                key={task.id}
                                                onSelect={(selected) => setSelectedTaskId(selected.id)}
                                                onToggleComplete={toggleComplete}
                                                onToggleStar={toggleStar}
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
                    lists={destinationLists}
                    onClose={() => setSelectedTaskId(null)}
                    onDelete={() => setDeleteTask(selectedTask)}
                    onSave={saveTask}
                    task={selectedTask}
                />
            )}

            {undoMessage && (
                <div className="undo-bar" role="status">
                    <span>{undoMessage}</span>
                    <button onClick={() => setUndoMessage('')} type="button">Dismiss</button>
                    <button onClick={() => setUndoMessage('')} type="button">Undo</button>
                </div>
            )}

            <Dialog
                description={`This static ${createKind ?? 'item'} form demonstrates the Phase 1 component state.`}
                onClose={() => { setCreateKind(null); setCreateName(''); }}
                open={createKind !== null}
                title={`Create ${createKind ?? 'item'}`}
            >
                <form className="dialog-form" onSubmit={(event) => {
                    event.preventDefault();
                    if (createName.trim()) {
                        setUndoMessage(`${createKind === 'folder' ? 'Folder' : 'List'} “${createName.trim()}” added to this static preview.`);
                        setCreateKind(null);
                        setCreateName('');
                    }
                }}>
                    <label className="field-label" htmlFor="create-name">Name</label>
                    <input autoFocus className="text-field" id="create-name" onChange={(event) => setCreateName(event.target.value)} placeholder={createKind === 'folder' ? 'e.g. Work' : 'e.g. Website launch'} value={createName} />
                    <div className="dialog-actions">
                        <Button onClick={() => setCreateKind(null)} variant="ghost">Cancel</Button>
                        <Button disabled={!createName.trim()} type="submit" variant="primary">Create</Button>
                    </div>
                </form>
            </Dialog>

            <Dialog
                description="Deleting and completing are intentionally different actions. Persistence is added in Phase 2."
                onClose={() => setDeleteTask(null)}
                open={deleteTask !== null}
                title="Delete this task?"
            >
                <p className="dialog-body-copy">{deleteTask?.title}</p>
                <div className="dialog-actions">
                    <Button onClick={() => setDeleteTask(null)} variant="ghost">Keep task</Button>
                    <Button onClick={confirmDelete} variant="danger">Delete task</Button>
                </div>
            </Dialog>

            <nav aria-label="Prototype states" className="prototype-switcher">
                <span>Preview</span>
                {(['inbox', 'list', 'starred', 'empty', 'complete'] as WorkspaceView[]).map((state) => (
                    <Link className={view === state ? 'is-active' : ''} href={prototype(state)} key={state}>{state}</Link>
                ))}
            </nav>
        </div>
    );
}
