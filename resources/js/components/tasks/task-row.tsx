import { useDragOperation } from '@dnd-kit/react';
import { OptimisticSortingPlugin } from '@dnd-kit/dom/sortable';
import { useSortable } from '@dnd-kit/react/sortable';
import { useCallback, useRef, useState } from 'react';
import { Icon } from '@/components/ui/icon';
import { useDismissibleMenu } from '@/lib/use-dismissible-menu';
import type { TaskSummary } from '@/types';

type TaskRowProps = {
    task: TaskSummary;
    completed?: boolean;
    transitionState?: 'completing' | 'completed-arrival';
    onSelect: (task: TaskSummary) => void;
    onToggleComplete: (taskId: number) => void;
    onToggleStar: (taskId: number) => void;
    onDelete: (task: TaskSummary) => void;
    sortableIndex?: number;
    sortableDisabled?: boolean;
    canMoveAcrossLists?: boolean;
    pending?: boolean;
};

export function TaskRow({
    task,
    completed = false,
    transitionState,
    onSelect,
    onToggleComplete,
    onToggleStar,
    onDelete,
    sortableIndex = 0,
    sortableDisabled = false,
    canMoveAcrossLists = false,
    pending = false,
}: TaskRowProps) {
    const [menuOpen, setMenuOpen] = useState(false);
    const menuTriggerRef = useRef<HTMLButtonElement>(null);
    const menuRef = useRef<HTMLDivElement>(null);
    const closeMenu = useCallback(() => setMenuOpen(false), []);
    const hasMetadata = Boolean(task.dueDateLabel || task.note || task.taskListName !== 'Inbox');
    const dragOperation = useDragOperation();
    const isDragSource = dragOperation.source?.id === `task-${task.id}`;
    const sortable = useSortable({
        id: `task-${task.id}`,
        index: sortableIndex,
        group: `tasks-${task.taskListId}`,
        type: 'task',
        accept: 'task',
        data: {
            kind: 'task',
            taskId: task.id,
            taskListId: task.taskListId,
            title: task.title,
            canMoveAcrossLists,
        },
        // Tasks can target sidebar list sortables. Let React perform that
        // cross-tree move after persistence instead of allowing dnd-kit's
        // optimistic plugin to physically reparent the task DOM node.
        plugins: (defaults) => defaults.filter((plugin) => plugin !== OptimisticSortingPlugin),
        disabled: {
            draggable: completed || sortableDisabled,
            droppable: completed || sortableDisabled || isDragSource,
        },
    });

    useDismissibleMenu(menuOpen, menuTriggerRef, menuRef, closeMenu);

    return (
        <article
            aria-label={!completed && !sortableDisabled ? `Reorder or move task ${task.title}` : undefined}
            aria-roledescription={!completed && !sortableDisabled ? 'sortable task' : undefined}
            className={`task-row ${completed ? 'is-completed' : ''} ${transitionState === 'completing' ? 'is-completing' : ''} ${transitionState === 'completed-arrival' ? 'is-completed-arrival' : ''} ${!completed && !sortableDisabled ? 'is-sortable' : ''} ${sortable.isDragging ? 'is-dragging' : ''} ${sortable.isDropTarget ? 'is-drop-target' : ''} ${menuOpen ? 'has-open-menu' : ''}`}
            data-workspace-drop-id={`task-${task.id}`}
            ref={!completed && !sortableDisabled ? sortable.ref : undefined}
            role={!completed && !sortableDisabled ? 'group' : undefined}
            tabIndex={!completed && !sortableDisabled ? 0 : undefined}
        >
            <button
                aria-label={completed ? `Restore ${task.title}` : `Complete ${task.title}`}
                className="task-check"
                disabled={pending}
                onClick={() => onToggleComplete(task.id)}
                type="button"
            >
                {(completed || transitionState === 'completing') && <Icon name="check" size={14} />}
            </button>
            <button className="task-body" onClick={() => onSelect(task)} type="button">
                <span className="task-title">{task.title}</span>
                {hasMetadata && (
                    <span className="task-meta">
                        {task.dueDateLabel && (
                            <span className={`due-label due-label--${task.dueDateStatus ?? 'none'}`}>
                                <Icon name="calendar" size={13} />{task.dueDateLabel}
                            </span>
                        )}
                        {task.note && <span aria-label="Has a note" className="note-indicator"><Icon name="note" size={13} /></span>}
                        {task.taskListName !== 'Inbox' && <span>{task.taskListName}</span>}
                    </span>
                )}
            </button>
            <button
                aria-label={task.isStarred ? `Unstar ${task.title}` : `Star ${task.title}`}
                className={`task-star ${task.isStarred ? 'is-starred' : ''}`}
                disabled={pending}
                onClick={() => onToggleStar(task.id)}
                type="button"
            >
                <Icon fill={task.isStarred} name="star" size={19} />
            </button>
            <div className="task-more-wrap">
                <button aria-expanded={menuOpen} aria-label={`More options for ${task.title}`} className="task-more" onClick={() => setMenuOpen((open) => !open)} ref={menuTriggerRef} type="button">
                    <Icon name="more" size={18} />
                </button>
                {menuOpen && (
                    <div className="task-menu" ref={menuRef}>
                        <button onClick={() => { onSelect(task); setMenuOpen(false); }} type="button">Open details</button>
                        <button onClick={() => { onSelect(task); setMenuOpen(false); }} type="button">Move to another list…</button>
                        <button className="is-danger" onClick={() => { onDelete(task); setMenuOpen(false); }} type="button">Delete</button>
                    </div>
                )}
            </div>
        </article>
    );
}
