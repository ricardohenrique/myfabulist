import { useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Icon } from '@/components/ui/icon';
import type { NavigationList, TaskSummary } from '@/types';

type TaskDetailsProps = {
    task: TaskSummary;
    lists: NavigationList[];
    onClose: () => void;
    onDelete: (taskId: number) => void;
    onSave: (task: TaskSummary) => void;
    errors?: Record<string, string>;
    processing?: boolean;
};

export function TaskDetails({ task, lists, onClose, onDelete, onSave, errors = {}, processing = false }: TaskDetailsProps) {
    const [draft, setDraft] = useState(task);

    useEffect(() => setDraft(task), [task]);

    return (
        <aside aria-label="Task details" className="task-details">
            <header className="details-header">
                <button aria-label="Mark task complete" className="task-check task-check--large" type="button" />
                <input
                    aria-label="Task title"
                    aria-invalid={Boolean(errors.title)}
                    className="details-title-input"
                    onChange={(event) => setDraft({ ...draft, title: event.target.value })}
                    value={draft.title}
                />
                <button
                    aria-label={draft.isStarred ? 'Unstar task' : 'Star task'}
                    className={`task-star ${draft.isStarred ? 'is-starred' : ''}`}
                    onClick={() => setDraft({ ...draft, isStarred: !draft.isStarred })}
                    type="button"
                >
                    <Icon fill={draft.isStarred} name="star" size={21} />
                </button>
                <button aria-label="Close task details" className="details-close" onClick={onClose} type="button"><Icon name="close" /></button>
            </header>

            {(errors.title || errors.domain) && <p className="details-error" role="alert">{errors.title ?? errors.domain}</p>}

            <div className="details-scroll">
                <label className="detail-field detail-field--date">
                    <Icon name="calendar" />
                    <span>
                        <small>Due date</small>
                        <input
                            onChange={(event) => setDraft({
                                ...draft,
                                dueDate: event.target.value || null,
                                dueDateLabel: event.target.value ? 'Selected date' : null,
                                dueDateStatus: event.target.value ? 'upcoming' : null,
                            })}
                            type="date"
                            value={draft.dueDate ?? ''}
                        />
                    </span>
                </label>

                <div className="detail-field is-disabled" aria-disabled="true">
                    <Icon name="bell" />
                    <span><small>Reminder</small><strong>Not available yet</strong></span>
                </div>

                <label className="detail-field detail-field--note">
                    <Icon name="note" />
                    <span>
                        <small>Notes</small>
                        <textarea
                            onChange={(event) => setDraft({ ...draft, note: event.target.value || null })}
                            placeholder="Add a note…"
                            rows={6}
                            value={draft.note ?? ''}
                        />
                    </span>
                </label>

                <label className="detail-field">
                    <Icon name="list" />
                    <span>
                        <small>List</small>
                        <select
                            onChange={(event) => {
                                const selected = lists.find((list) => list.id === Number(event.target.value));
                                if (selected) setDraft({ ...draft, taskListId: selected.id, taskListName: selected.name });
                            }}
                            value={draft.taskListId}
                        >
                            {lists.map((list) => <option key={list.id} value={list.id}>{list.name}</option>)}
                        </select>
                    </span>
                </label>
            </div>

            <footer className="details-footer">
                <Button aria-label="Delete task" disabled={processing} onClick={() => onDelete(task.id)} variant="danger"><Icon name="trash" size={18} />Delete</Button>
                <div>
                    <Button disabled={processing} onClick={onClose} variant="ghost">Cancel</Button>
                    <Button disabled={processing || !draft.title.trim()} onClick={() => onSave(draft)} variant="primary"><Icon name="check" size={18} />{processing ? 'Saving…' : 'Save'}</Button>
                </div>
            </footer>
        </aside>
    );
}
