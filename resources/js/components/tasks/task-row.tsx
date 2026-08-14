import { useSortable } from '@dnd-kit/react/sortable';
import { useState } from 'react';
import { Icon } from '@/components/ui/icon';
import type { TaskSummary } from '@/types';

type TaskRowProps = {
    task: TaskSummary;
    completed?: boolean;
    onSelect: (task: TaskSummary) => void;
    onToggleComplete: (taskId: number) => void;
    onToggleStar: (taskId: number) => void;
    onDelete: (task: TaskSummary) => void;
    sortableIndex?: number;
    sortableDisabled?: boolean;
    pending?: boolean;
};

export function TaskRow({
    task,
    completed = false,
    onSelect,
    onToggleComplete,
    onToggleStar,
    onDelete,
    sortableIndex = 0,
    sortableDisabled = false,
    pending = false,
}: TaskRowProps) {
    const [menuOpen, setMenuOpen] = useState(false);
    const hasMetadata = Boolean(task.dueDateLabel || task.note || task.taskListName !== 'Inbox');
    const sortable = useSortable({
        id: `task-${task.id}`,
        index: sortableIndex,
        type: 'task',
        disabled: completed,
    });

    return (
        <article
            aria-label={!completed && !sortableDisabled ? `Reorder task ${task.title}` : undefined}
            aria-roledescription={!completed && !sortableDisabled ? 'sortable task' : undefined}
            className={`task-row ${completed ? 'is-completed' : ''} ${!completed && !sortableDisabled ? 'is-sortable' : ''} ${sortable.isDragging ? 'is-dragging' : ''} ${sortable.isDropTarget ? 'is-drop-target' : ''}`}
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
                {completed && <Icon name="check" size={14} />}
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
                <button aria-expanded={menuOpen} aria-label={`More options for ${task.title}`} className="task-more" onClick={() => setMenuOpen((open) => !open)} type="button">
                    <Icon name="more" size={18} />
                </button>
                {menuOpen && (
                    <div className="task-menu">
                        <button onClick={() => { onSelect(task); setMenuOpen(false); }} type="button">Open details</button>
                        <button onClick={() => { onSelect(task); setMenuOpen(false); }} type="button">Move to another list…</button>
                        <button className="is-danger" onClick={() => { onDelete(task); setMenuOpen(false); }} type="button">Delete</button>
                    </div>
                )}
            </div>
        </article>
    );
}
