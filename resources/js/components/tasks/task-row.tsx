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
    onMoveUp?: (task: TaskSummary) => void;
    onMoveDown?: (task: TaskSummary) => void;
    moveUpDisabled?: boolean;
    moveDownDisabled?: boolean;
    pending?: boolean;
};

export function TaskRow({
    task,
    completed = false,
    onSelect,
    onToggleComplete,
    onToggleStar,
    onDelete,
    onMoveUp,
    onMoveDown,
    moveUpDisabled = false,
    moveDownDisabled = false,
    pending = false,
}: TaskRowProps) {
    const [menuOpen, setMenuOpen] = useState(false);

    return (
        <article className={`task-row ${completed ? 'is-completed' : ''}`}>
            {!completed && (
                <button aria-label={`Drag ${task.title}`} className="task-grip" type="button" title="Drag-and-drop arrives in Phase 3">
                    <Icon name="grip" size={17} />
                </button>
            )}
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
                <span className="task-meta">
                    {task.dueDateLabel && (
                        <span className={`due-label due-label--${task.dueDateStatus ?? 'none'}`}>
                            <Icon name="calendar" size={13} />{task.dueDateLabel}
                        </span>
                    )}
                    {task.note && <span aria-label="Has a note" className="note-indicator"><Icon name="note" size={13} /></span>}
                    {task.taskListName !== 'Inbox' && <span>{task.taskListName}</span>}
                </span>
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
                        {!completed && onMoveUp && (
                            <button disabled={moveUpDisabled || pending} onClick={() => { onMoveUp(task); setMenuOpen(false); }} type="button">Move up</button>
                        )}
                        {!completed && onMoveDown && (
                            <button disabled={moveDownDisabled || pending} onClick={() => { onMoveDown(task); setMenuOpen(false); }} type="button">Move down</button>
                        )}
                        <button onClick={() => { onSelect(task); setMenuOpen(false); }} type="button">Move to another list…</button>
                        <button className="is-danger" onClick={() => { onDelete(task); setMenuOpen(false); }} type="button">Delete</button>
                    </div>
                )}
            </div>
        </article>
    );
}
