import { useEffect, useState, type FormEvent, type KeyboardEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Icon } from '@/components/ui/icon';
import type { NavigationList, TaskSummary } from '@/types';

type TaskDetailsProps = {
    task: TaskSummary;
    lists: NavigationList[];
    onClose: () => void;
    onAddComment: (taskId: number, body: string, onSuccess: () => void) => void;
    onDelete: (taskId: number) => void;
    onSave: (task: TaskSummary) => void;
    onToggleComplete: (taskId: number) => void;
    onToggleStar: (taskId: number) => void;
    errors?: Record<string, string>;
    commentError?: string;
    commentProcessing?: boolean;
    processing?: boolean;
};

function formatCommentDate(value: string): string {
    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}

function formatTaskCreationDate(value: string): string {
    const createdAt = new Date(value);
    const elapsedSeconds = Math.round((createdAt.getTime() - Date.now()) / 1_000);
    const relativeTime = new Intl.RelativeTimeFormat(undefined, { numeric: 'auto' });

    if (Math.abs(elapsedSeconds) < 60) return 'Created just now';
    if (Math.abs(elapsedSeconds) < 3_600) return `Created ${relativeTime.format(Math.round(elapsedSeconds / 60), 'minute')}`;
    if (Math.abs(elapsedSeconds) < 86_400) return `Created ${relativeTime.format(Math.round(elapsedSeconds / 3_600), 'hour')}`;
    if (Math.abs(elapsedSeconds) < 604_800) return `Created ${relativeTime.format(Math.round(elapsedSeconds / 86_400), 'day')}`;

    return `Created on ${new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' }).format(createdAt)}`;
}

export function TaskDetails({
    task,
    lists,
    onClose,
    onAddComment,
    onDelete,
    onSave,
    onToggleComplete,
    onToggleStar,
    errors = {},
    commentError = '',
    commentProcessing = false,
    processing = false,
}: TaskDetailsProps) {
    const [draft, setDraft] = useState(task);
    const [commentBody, setCommentBody] = useState('');

    useEffect(() => setDraft(task), [task.id]);
    useEffect(() => setDraft((current) => ({
        ...current,
        completedAt: task.completedAt,
        isStarred: task.isStarred,
    })), [task.completedAt, task.isStarred]);
    useEffect(() => setCommentBody(''), [task.id]);

    const submitComment = (event?: FormEvent<HTMLFormElement>) => {
        event?.preventDefault();
        const body = commentBody.trim();

        if (!body || commentProcessing) return;

        onAddComment(task.id, body, () => setCommentBody(''));
    };

    const handleCommentKeyDown = (event: KeyboardEvent<HTMLTextAreaElement>) => {
        if (event.key !== 'Enter' || event.shiftKey || event.nativeEvent.isComposing) return;

        event.preventDefault();
        submitComment();
    };

    return (
        <aside aria-label="Task details" className="task-details">
            <header className="details-header">
                <button
                    aria-label={task.completedAt ? 'Restore task' : 'Mark task complete'}
                    className="task-check task-check--large"
                    disabled={processing}
                    onClick={() => onToggleComplete(task.id)}
                    type="button"
                >
                    {task.completedAt && <Icon name="check" size={15} />}
                </button>
                <input
                    aria-label="Task title"
                    aria-invalid={Boolean(errors.title)}
                    className="details-title-input"
                    onChange={(event) => setDraft({ ...draft, title: event.target.value })}
                    value={draft.title}
                />
                <button
                    aria-label={task.isStarred ? 'Unstar task' : 'Star task'}
                    className={`task-star ${task.isStarred ? 'is-starred' : ''}`}
                    disabled={processing}
                    onClick={() => onToggleStar(task.id)}
                    type="button"
                >
                    <Icon fill={task.isStarred} name="star" size={21} />
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

                <section aria-label="Task comments" className="task-comments">
                    <header className="task-comments__heading">
                        <Icon name="comment" size={18} />
                        <strong>Comments</strong>
                        {task.comments.length > 0 && <span>{task.comments.length}</span>}
                    </header>

                    {task.comments.length > 0 ? (
                        <div className="task-comments__list">
                            {task.comments.map((comment) => (
                                <article className="task-comment" key={comment.id}>
                                    <span className="task-comment__avatar">
                                        {comment.author.avatarUrl
                                            ? <img alt="" src={comment.author.avatarUrl} />
                                            : <Icon name="user" size={16} />}
                                    </span>
                                    <div>
                                        <header>
                                            <strong>{comment.author.name}</strong>
                                            <time dateTime={comment.createdAt}>{formatCommentDate(comment.createdAt)}</time>
                                        </header>
                                        <p>{comment.body}</p>
                                    </div>
                                </article>
                            ))}
                        </div>
                    ) : (
                        <p className="task-comments__empty">No comments yet. Add the first bit of context.</p>
                    )}

                    <form className="comment-composer" onSubmit={submitComment}>
                        <Icon name="comment" size={17} />
                        <textarea
                            aria-describedby={commentError ? 'comment-error' : undefined}
                            aria-invalid={Boolean(commentError)}
                            aria-label="Add a comment"
                            disabled={commentProcessing}
                            maxLength={65_535}
                            onChange={(event) => setCommentBody(event.target.value)}
                            onKeyDown={handleCommentKeyDown}
                            placeholder="Add a comment…"
                            rows={2}
                            value={commentBody}
                        />
                        <button
                            aria-label="Post comment"
                            disabled={commentProcessing || !commentBody.trim()}
                            title="Post comment"
                            type="submit"
                        >
                            <Icon name="chevron-right" size={18} />
                        </button>
                    </form>
                    {commentError && <p className="comment-error" id="comment-error" role="alert">{commentError}</p>}
                    <p className="comment-hint">Press Enter to post · Shift + Enter for a new line</p>
                </section>
            </div>

            <div className="details-created-at">
                <time dateTime={task.createdAt} title={formatCommentDate(task.createdAt)}>
                    {formatTaskCreationDate(task.createdAt)}
                </time>
            </div>

            <footer className="details-footer">
                <Button aria-label="Close task details" className="details-footer-close" disabled={processing} onClick={onClose} size="sm" title="Close task details" variant="ghost"><Icon name="chevron-left" size={20} /></Button>
                <Button className="details-save-button" disabled={processing || !draft.title.trim()} onClick={() => onSave(draft)} variant="primary"><Icon name="check" size={19} />{processing ? 'Saving…' : 'Save'}</Button>
                <Button aria-label="Delete task" className="details-delete-button" disabled={processing} onClick={() => onDelete(task.id)} size="sm" title="Delete task" variant="ghost"><Icon name="trash" size={18} /></Button>
            </footer>
        </aside>
    );
}
