import { DragDropProvider, PointerSensor, type CollisionEvent, type DragEndEvent, type DragMoveEvent, type DragOverEvent, type DragStartEvent } from '@dnd-kit/react';
import { isSortable } from '@dnd-kit/react/sortable';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState, type FormEvent } from 'react';
import { ShareDialog } from '@/components/lists/share-dialog';
import { Sidebar } from '@/components/navigation/sidebar';
import { NotificationCenterView } from '@/components/notifications/notification-center-view';
import { UseCaseDialog } from '@/components/onboarding/use-case-dialog';
import { TaskDetails } from '@/components/tasks/task-details';
import { TaskRow } from '@/components/tasks/task-row';
import { Button } from '@/components/ui/button';
import { Dialog } from '@/components/ui/dialog';
import { Icon } from '@/components/ui/icon';
import { ProfileSettingsDialog } from '@/components/settings/profile-settings-dialog';
import { trackAnalyticsEvent } from '@/lib/analytics';
import { moveItem, orderByIds, wholeItemPointerSensor } from '@/lib/sortable';
import { workspaceDragData } from '@/lib/workspace-drag';
import { workspaceBackgroundStyle } from '@/lib/workspace-background';
import * as folderRoutes from '@/routes/folders';
import * as invitationRoutes from '@/routes/invitations';
import * as listRoutes from '@/routes/lists';
import * as listMemberRoutes from '@/routes/lists/members';
import * as listMembershipRoutes from '@/routes/lists/membership';
import { store as storeTask } from '@/routes/lists/tasks';
import * as notificationRoutes from '@/routes/notifications';
import { update as updatePassword } from '@/routes/profile/password';
import { update as updateProfile } from '@/routes/profile';
import * as taskRoutes from '@/routes/tasks';
import { store as storeTaskComment } from '@/routes/tasks/comments';
import { store as storeSubtask } from '@/routes/tasks/subtasks';
import * as subtaskRoutes from '@/routes/subtasks';
import type {
    CurrentListDetails,
    NavigationFolder,
    NavigationList,
    NotificationItem,
    SharedPageProps,
    SubtaskSummary,
    TaskSummary,
    UserSummary,
    WorkspaceData,
} from '@/types';

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

const NOTIFICATION_TIMEOUT_MS = 5_000;
const TASK_COMPLETION_EXIT_MS = 280;
const TASK_COMPLETION_ARRIVAL_MS = 260;

type AppShellProps = {
    workspace: WorkspaceData;
    user: UserSummary;
};

export function AppShell({ workspace, user }: AppShellProps) {
    const page = usePage<SharedPageProps>();
    const [selectedTaskId, setSelectedTaskId] = useState<number | null>(null);
    const [completedOpen, setCompletedOpen] = useState(true);
    const [mobileNavOpen, setMobileNavOpen] = useState(false);
    const [profileDialogOpen, setProfileDialogOpen] = useState(false);
    const [profileSuccess, setProfileSuccess] = useState('');
    const [entityDialog, setEntityDialog] = useState<EntityDialog | null>(null);
    const [entityName, setEntityName] = useState('');
    const [entityFolderId, setEntityFolderId] = useState<number | null>(null);
    const [entityError, setEntityError] = useState('');
    const [deleteDialog, setDeleteDialog] = useState<DeleteDialog | null>(null);
    const [folderDeleteStrategy, setFolderDeleteStrategy] = useState<'detach' | 'delete'>('detach');
    const [taskErrors, setTaskErrors] = useState<Record<string, string>>({});
    const [commentError, setCommentError] = useState('');
    const [commentProcessing, setCommentProcessing] = useState(false);
    const [subtaskError, setSubtaskError] = useState('');
    const [subtaskCreating, setSubtaskCreating] = useState(false);
    const [pendingSubtaskIds, setPendingSubtaskIds] = useState<number[]>([]);
    const [pendingTaskIds, setPendingTaskIds] = useState<number[]>([]);
    const [completingTasks, setCompletingTasks] = useState<TaskSummary[]>([]);
    const [completedArrivalTaskIds, setCompletedArrivalTaskIds] = useState<number[]>([]);
    const [optimisticallyMovedTaskIds, setOptimisticallyMovedTaskIds] = useState<number[]>([]);
    const [taskOrder, setTaskOrder] = useState<number[]>(() => workspace.tasks
        .filter((task) => !task.completedAt)
        .map((task) => task.id));
    const [reorderPending, setReorderPending] = useState(false);
    const [entityProcessing, setEntityProcessing] = useState(false);
    const [notice, setNotice] = useState('');
    const [undo, setUndo] = useState<UndoState | null>(null);
    const [respondingInvitationIds, setRespondingInvitationIds] = useState<number[]>([]);
    const [shareDialogOpen, setShareDialogOpen] = useState(false);
    const [backgroundShareList, setBackgroundShareList] = useState<CurrentListDetails | null>(null);
    const [shareDetailsLoading, setShareDetailsLoading] = useState(false);
    const [shareDetailsError, setShareDetailsError] = useState('');
    const [shareInviteError, setShareInviteError] = useState('');
    const [shareInviteProcessing, setShareInviteProcessing] = useState(false);
    const [revokingMemberIds, setRevokingMemberIds] = useState<number[]>([]);
    const inputRef = useRef<HTMLInputElement>(null);
    const completionSoundAudioRef = useRef<HTMLAudioElement>(null);
    const restoreQuickAddFocusRef = useRef(false);
    const animationTimeoutIdsRef = useRef<Set<number>>(new Set());
    const completionStartedAtRef = useRef<Map<number, number>>(new Map());
    const dragSourceId = useRef<string | null>(null);
    const lastCollisionTargetId = useRef<string | null>(null);
    const taskOrderRef = useRef(taskOrder);
    const taskDragInitialOrder = useRef<number[] | null>(null);
    const taskDragPreviewTargetId = useRef<number | null>(null);
    const shareDetailsRequestId = useRef(0);
    const quickAdd = useForm({ title: '' });
    const profileForm = useForm({ name: user.name, email: user.email });
    const passwordForm = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    });

    const tasks = workspace.tasks;
    const completingTaskIds = useMemo(() => completingTasks.map((task) => task.id), [completingTasks]);
    const activeTasks = orderByIds(
        [
            ...tasks.filter((task) => !task.completedAt && !optimisticallyMovedTaskIds.includes(task.id)),
            ...completingTasks.filter((task) => tasks.some((item) => item.id === task.id && item.completedAt)),
        ],
        taskOrder,
    );
    const completedTasks = tasks.filter((task) => task.completedAt && !completingTaskIds.includes(task.id));
    const selectedTask = tasks.find((task) => task.id === selectedTaskId) ?? null;
    const shareDialogList = backgroundShareList ?? workspace.currentList;
    const destinationLists = useMemo(
        () => [workspace.inbox, ...workspace.folders.flatMap((folder) => folder.lists), ...workspace.ungroupedLists],
        [workspace],
    );

    useEffect(() => {
        const canonicalActiveIds = tasks.filter((task) => !task.completedAt).map((task) => task.id);
        const visibleTaskIds = new Set([...canonicalActiveIds, ...completingTaskIds]);
        const previousTaskIds = new Set(taskOrderRef.current);
        const nextTaskOrder = taskOrderRef.current.filter((taskId) => visibleTaskIds.has(taskId));

        canonicalActiveIds.forEach((taskId, canonicalIndex) => {
            if (previousTaskIds.has(taskId)) return;

            const nextCanonicalSiblingId = canonicalActiveIds
                .slice(canonicalIndex + 1)
                .find((candidateId) => nextTaskOrder.includes(candidateId));
            const insertionIndex = nextCanonicalSiblingId === undefined
                ? nextTaskOrder.length
                : nextTaskOrder.indexOf(nextCanonicalSiblingId);

            nextTaskOrder.splice(insertionIndex, 0, taskId);
        });

        taskOrderRef.current = nextTaskOrder;
        setTaskOrder(nextTaskOrder);
        setOptimisticallyMovedTaskIds((current) => current.filter((taskId) => tasks.some((task) => task.id === taskId)));
    }, [completingTaskIds, tasks]);

    useEffect(() => {
        if (selectedTaskId !== null && !tasks.some((task) => task.id === selectedTaskId)) {
            setSelectedTaskId(null);
        }
    }, [selectedTaskId, tasks]);

    useEffect(() => {
        const taskId = Number(new URLSearchParams(page.url.split('?')[1] ?? '').get('task'));

        if (Number.isInteger(taskId) && tasks.some((task) => task.id === taskId)) {
            setTaskErrors({});
            setCommentError('');
            setSelectedTaskId(taskId);
        }
    }, [page.url, tasks]);

    useEffect(() => {
        if (page.props.flash?.success) {
            setNotice(page.props.flash.success);
        } else if (page.props.flash?.error) {
            setNotice(page.props.flash.error);
        } else if (page.props.errors?.domain) {
            setNotice(page.props.errors.domain);
        }
    }, [page.props.errors, page.props.flash]);

    useEffect(() => {
        if (!quickAdd.processing && restoreQuickAddFocusRef.current) {
            restoreQuickAddFocusRef.current = false;
            inputRef.current?.focus();
        }
    }, [quickAdd.processing]);

    useEffect(() => {
        const analyticsEvent = page.props.flash?.analyticsEvent;

        if (analyticsEvent) {
            trackAnalyticsEvent(analyticsEvent.name, { method: analyticsEvent.method });
        }
    }, [page.props.flash?.analyticsEvent]);

    useEffect(() => {
        if (!undo && !notice) return;

        const timeoutId = window.setTimeout(() => {
            setUndo(null);
            setNotice('');
        }, NOTIFICATION_TIMEOUT_MS);

        return () => window.clearTimeout(timeoutId);
    }, [notice, undo]);

    useEffect(() => () => {
        animationTimeoutIdsRef.current.forEach((timeoutId) => window.clearTimeout(timeoutId));
    }, []);

    const queueAnimationTimeout = (callback: () => void, delay: number) => {
        const timeoutId = window.setTimeout(() => {
            animationTimeoutIdsRef.current.delete(timeoutId);
            callback();
        }, delay);

        animationTimeoutIdsRef.current.add(timeoutId);
    };

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

        if (!workspace.currentList) return;

        restoreQuickAddFocusRef.current = true;
        quickAdd.transform(() => ({ title }));
        quickAdd.post(storeTask.url(workspace.currentList.id), {
            preserveScroll: true,
            onSuccess: () => {
                trackAnalyticsEvent('task_created');
                quickAdd.reset('title');
                quickAdd.clearErrors();
            },
        });
    };

    const toggleComplete = (taskId: number) => {
        const task = tasks.find((item) => item.id === taskId);
        if (!task) return;

        setTaskPending(taskId, true);
        const completing = task.completedAt === null;
        const route = completing ? taskRoutes.complete(taskId) : taskRoutes.restore(taskId);
        const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        if (completing) {
            completionStartedAtRef.current.set(taskId, window.performance.now());
            setCompletingTasks((current) => current.some((item) => item.id === taskId) ? current : [...current, task]);
        }

        const persistCompletion = () => {
            router.post(route, {}, {
                preserveScroll: true,
                onSuccess: () => {
                    if (completing) {
                        trackAnalyticsEvent('task_completed');
                        const completionAudio = completionSoundAudioRef.current;

                        if (user.completionSound && completionAudio) {
                            completionAudio.currentTime = 0;
                            void completionAudio.play().catch(() => undefined);
                        }

                        const startedAt = completionStartedAtRef.current.get(taskId) ?? window.performance.now();
                        const elapsed = window.performance.now() - startedAt;
                        const remainingExitTime = reducedMotion ? 0 : Math.max(0, TASK_COMPLETION_EXIT_MS - elapsed);

                        queueAnimationTimeout(() => {
                            completionStartedAtRef.current.delete(taskId);
                            setCompletingTasks((current) => current.filter((item) => item.id !== taskId));
                            setCompletedArrivalTaskIds((current) => [...new Set([...current, taskId])]);
                            queueAnimationTimeout(() => {
                                setCompletedArrivalTaskIds((current) => current.filter((id) => id !== taskId));
                            }, reducedMotion ? 0 : TASK_COMPLETION_ARRIVAL_MS);
                        }, remainingExitTime);
                    }

                    setUndo({
                        message: completing ? `“${task.title}” completed.` : `“${task.title}” restored.`,
                        execute: () => router.post(completing ? taskRoutes.restore(taskId) : taskRoutes.complete(taskId), {}, { preserveScroll: true }),
                    });
                },
                onError: (errors) => {
                    completionStartedAtRef.current.delete(taskId);
                    setCompletingTasks((current) => current.filter((item) => item.id !== taskId));
                    setNotice(Object.values(errors)[0] ?? 'That change could not be saved.');
                },
                onFinish: () => {
                    setTaskPending(taskId, false);
                },
            });
        };

        persistCompletion();
    };

    const toggleCompleteFromDetails = (taskId: number) => {
        const task = tasks.find((item) => item.id === taskId);
        if (!task) return;

        // Completing a task removes the details surface immediately, which
        // is especially important when that surface fills a mobile viewport.
        // A rejected write still reconciles through toggleComplete's existing
        // error handling and leaves the canonical active task in the list.
        if (task.completedAt === null) {
            setSelectedTaskId(null);
        }

        toggleComplete(taskId);
    };

    const toggleStar = (taskId: number) => {
        const task = tasks.find((item) => item.id === taskId);
        if (!task) return;

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
                    trackAnalyticsEvent('task_moved');
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

    const addComment = (taskId: number, body: string, onSuccess: () => void) => {
        setCommentError('');
        setCommentProcessing(true);

        router.post(storeTaskComment(taskId), { body }, {
            preserveScroll: true,
            preserveState: true,
            onSuccess,
            onError: (errors) => setCommentError(errors.body ?? errors.domain ?? 'The comment could not be saved.'),
            onFinish: () => setCommentProcessing(false),
        });
    };

    const setSubtaskPending = (subtaskId: number, pending: boolean) => {
        setPendingSubtaskIds((current) => pending
            ? [...new Set([...current, subtaskId])]
            : current.filter((id) => id !== subtaskId));
    };

    const subtaskErrorMessage = (errors: Record<string, string>) =>
        errors.title ?? errors.domain ?? Object.values(errors)[0] ?? 'The subtask could not be saved.';

    const addSubtask = (taskId: number, title: string, onSuccess: () => void) => {
        setSubtaskError('');
        setSubtaskCreating(true);
        router.post(storeSubtask(taskId), { title }, {
            preserveScroll: true,
            preserveState: true,
            onSuccess,
            onError: (errors) => setSubtaskError(subtaskErrorMessage(errors)),
            onFinish: () => setSubtaskCreating(false),
        });
    };

    const toggleSubtask = (subtask: SubtaskSummary) => {
        setSubtaskError('');
        setSubtaskPending(subtask.id, true);
        router.post(subtask.isCompleted ? subtaskRoutes.restore(subtask.id) : subtaskRoutes.complete(subtask.id), {}, {
            preserveScroll: true,
            preserveState: true,
            onError: (errors) => setSubtaskError(subtaskErrorMessage(errors)),
            onFinish: () => setSubtaskPending(subtask.id, false),
        });
    };

    const renameSubtask = (subtaskId: number, title: string, onError: () => void) => {
        setSubtaskError('');
        setSubtaskPending(subtaskId, true);
        router.put(subtaskRoutes.update(subtaskId), { title }, {
            preserveScroll: true,
            preserveState: true,
            onError: (errors) => {
                setSubtaskError(subtaskErrorMessage(errors));
                onError();
            },
            onFinish: () => setSubtaskPending(subtaskId, false),
        });
    };

    const deleteSubtask = (subtaskId: number) => {
        setSubtaskError('');
        setSubtaskPending(subtaskId, true);
        router.delete(subtaskRoutes.destroy(subtaskId), {
            preserveScroll: true,
            preserveState: true,
            onError: (errors) => setSubtaskError(subtaskErrorMessage(errors)),
            onFinish: () => setSubtaskPending(subtaskId, false),
        });
    };

    const confirmDelete = () => {
        if (!deleteDialog) return;

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

    const openCreate = (kind: 'folder' | 'list', folderId?: number) => {
        setEntityDialog({ kind, mode: 'create' });
        setEntityName('');
        setEntityFolderId(kind === 'list' ? (folderId ?? null) : null);
        setEntityError('');
    };

    const openProfile = () => {
        profileForm.setData({ name: user.name, email: user.email });
        profileForm.clearErrors();
        passwordForm.reset();
        passwordForm.clearErrors();
        setProfileSuccess('');
        setProfileDialogOpen(true);
    };

    const closeProfile = () => {
        if (profileForm.processing || passwordForm.processing) return;

        setProfileDialogOpen(false);
        setProfileSuccess('');
    };

    const saveProfile = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        setProfileSuccess('');
        profileForm.patch(updateProfile.url(), {
            preserveScroll: true,
            onSuccess: () => setProfileSuccess('Profile updated.'),
        });
    };

    const savePassword = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        setProfileSuccess('');
        passwordForm.put(updatePassword.url(), {
            preserveScroll: true,
            onSuccess: () => {
                passwordForm.reset();
                setProfileSuccess('Password updated.');
            },
            onFinish: () => passwordForm.reset('current_password', 'password', 'password_confirmation'),
        });
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

        setEntityProcessing(true);
        setEntityError('');
        const data = entityDialog.kind === 'folder'
            ? { name: entityName.trim() }
            : { name: entityName.trim(), folder_id: entityFolderId };
        const options = {
            preserveScroll: true,
            onSuccess: () => {
                if (entityDialog.mode === 'create') {
                    trackAnalyticsEvent(entityDialog.kind === 'folder' ? 'folder_created' : 'list_created');
                }

                setEntityDialog(null);
            },
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

    const reorderTask = (taskIds: number[]) => {
        const canonicalIds = tasks.filter((task) => !task.completedAt).map((task) => task.id);

        taskOrderRef.current = taskIds;
        setTaskOrder(taskIds);

        if (!workspace.currentList) return;

        setReorderPending(true);
        router.put(listRoutes.taskOrder(workspace.currentList.id), { task_ids: taskIds }, {
            preserveScroll: true,
            onError: (errors) => {
                taskOrderRef.current = canonicalIds;
                setTaskOrder(canonicalIds);
                setNotice(Object.values(errors)[0] ?? 'The task order could not be saved. The current order was restored.');
            },
            onFinish: () => setReorderPending(false),
        });
    };

    const restoreTaskDragOrder = () => {
        const initialOrder = taskDragInitialOrder.current;

        taskDragInitialOrder.current = null;
        taskDragPreviewTargetId.current = null;

        if (initialOrder) {
            taskOrderRef.current = initialOrder;
            setTaskOrder(initialOrder);
        }
    };

    const handleTaskDragEnd = (event: DragEndEvent) => {
        const source = event.operation.source;
        const target = event.operation.target;
        const sourceData = workspaceDragData(source);
        const targetData = workspaceDragData(target);
        const collisionTargetId = lastCollisionTargetId.current;

        if (event.canceled || reorderPending || sourceData?.kind !== 'task') {
            if (sourceData?.kind === 'task') restoreTaskDragOrder();

            return;
        }

        const collisionListId = collisionTargetId?.startsWith('list-target-')
            ? Number(collisionTargetId.slice('list-target-'.length))
            : collisionTargetId?.startsWith('list-')
                ? Number(collisionTargetId.slice('list-'.length))
                : null;
        const targetListId = collisionListId ?? (targetData?.kind === 'list' ? targetData.listId : null);

        if (targetListId !== null && targetListId !== sourceData.taskListId) {
            const task = tasks.find((item) => item.id === sourceData.taskId);
            const targetList = destinationLists.find((list) => list.id === targetListId);

            if (!task || !targetList || !sourceData.canMoveAcrossLists || !targetList.isOwner || targetList.isShared) {
                restoreTaskDragOrder();

                return;
            }

            restoreTaskDragOrder();
            setTaskPending(task.id, true);
            setOptimisticallyMovedTaskIds((current) => [...new Set([...current, task.id])]);
            router.post(taskRoutes.move(task.id), { task_list_id: targetList.id }, {
                preserveScroll: true,
                onSuccess: () => {
                    trackAnalyticsEvent('task_moved');
                    setUndo({
                        message: `“${task.title}” moved to ${targetList.name}.`,
                        execute: () => router.post(taskRoutes.move(task.id), { task_list_id: task.taskListId }, { preserveScroll: true }),
                    });
                },
                onError: (errors) => {
                    setOptimisticallyMovedTaskIds((current) => current.filter((taskId) => taskId !== task.id));
                    setNotice(Object.values(errors)[0] ?? 'The task could not be moved.');
                },
                onFinish: () => setTaskPending(task.id, false),
            });

            return;
        }

        if (!isSortable(source) || targetData?.kind !== 'task' || targetData.taskListId !== sourceData.taskListId) {
            restoreTaskDragOrder();

            return;
        }

        const collisionTaskId = collisionTargetId?.startsWith('task-')
            ? Number(collisionTargetId.slice('task-'.length))
            : taskDragPreviewTargetId.current;

        if (collisionTaskId === null && targetData.taskId === sourceData.taskId) {
            restoreTaskDragOrder();

            return;
        }

        const initialOrder = taskDragInitialOrder.current ?? activeTasks.map((task) => task.id);
        let reorderedIds = taskOrderRef.current;

        if (reorderedIds.every((taskId, index) => taskId === initialOrder[index])) {
            const finalTargetTaskId = collisionTaskId ?? targetData.taskId;
            const sourceIndex = reorderedIds.indexOf(sourceData.taskId);
            const targetIndex = reorderedIds.indexOf(finalTargetTaskId);

            reorderedIds = moveItem(reorderedIds, sourceIndex, targetIndex);
        }

        taskDragInitialOrder.current = null;
        taskDragPreviewTargetId.current = null;

        if (!reorderedIds.every((taskId, index) => taskId === initialOrder[index])) {
            reorderTask(reorderedIds);
        }
    };

    const handleDragStart = (event: DragStartEvent) => {
        const sourceData = workspaceDragData(event.operation.source);

        dragSourceId.current = String(event.operation.source?.id ?? '');
        lastCollisionTargetId.current = null;
        taskDragPreviewTargetId.current = null;

        if (sourceData?.kind === 'task') {
            taskDragInitialOrder.current = activeTasks.map((task) => task.id);
            taskOrderRef.current = activeTasks.map((task) => task.id);
        }
    };

    const handleCollision = (event: CollisionEvent) => {
        const collision = event.collisions.find((candidate) => String(candidate.id) !== dragSourceId.current);

        if (collision) {
            lastCollisionTargetId.current = String(collision.id);
        }
    };

    const previewTaskOrder = (sourceTaskId: number, targetTaskId: number) => {
        if (sourceTaskId === targetTaskId || taskDragPreviewTargetId.current === targetTaskId) return;

        taskDragPreviewTargetId.current = targetTaskId;

        const currentOrder = taskOrderRef.current;
        const sourceIndex = currentOrder.indexOf(sourceTaskId);
        const targetIndex = currentOrder.indexOf(targetTaskId);
        const reordered = moveItem(currentOrder, sourceIndex, targetIndex);

        if (reordered !== currentOrder) {
            taskOrderRef.current = reordered;
            setTaskOrder(reordered);
        }
    };

    const handleDragMove = (event: DragMoveEvent) => {
        const sourceData = workspaceDragData(event.operation.source);
        const nativeEvent = event.nativeEvent;
        const point = event.to
            ?? (nativeEvent instanceof PointerEvent
                ? { x: nativeEvent.clientX, y: nativeEvent.clientY }
                : event.operation.position.current);
        const elementsAtPoint = document.elementsFromPoint(point.x, point.y);
        const target = elementsAtPoint
            .map((element) => element.closest<HTMLElement>('[data-workspace-drop-id]'))
            .find((element) => element !== null && element.dataset.workspaceDropId !== dragSourceId.current);
        const targetId = target?.dataset.workspaceDropId ?? null;

        lastCollisionTargetId.current = targetId;

        if (sourceData?.kind !== 'task') return;

        if (targetId?.startsWith('task-')) {
            previewTaskOrder(sourceData.taskId, Number(targetId.slice('task-'.length)));

            return;
        }

        const remainsOverTaskRegion = elementsAtPoint.some((element) => element.closest('.task-list'));

        if (!(nativeEvent instanceof KeyboardEvent) && !remainsOverTaskRegion && taskDragPreviewTargetId.current !== null) {
            const initialOrder = taskDragInitialOrder.current;

            taskDragPreviewTargetId.current = null;

            if (initialOrder) {
                taskOrderRef.current = initialOrder;
                setTaskOrder(initialOrder);
            }
        }
    };

    const handleDragOver = (event: DragOverEvent) => {
        const sourceData = workspaceDragData(event.operation.source);
        const targetData = workspaceDragData(event.operation.target);

        if (sourceData?.kind !== 'task'
            || targetData?.kind !== 'task'
            || sourceData.taskListId !== targetData.taskListId
            || sourceData.taskId === targetData.taskId) {
            return;
        }

        previewTaskOrder(sourceData.taskId, targetData.taskId);
    };

    const reorderFolder = (folderIds: number[]) => {
        setReorderPending(true);
        router.put(folderRoutes.order(), { folder_ids: folderIds }, {
            preserveScroll: true,
            onError: (errors) => setNotice(Object.values(errors)[0] ?? 'The folder order could not be saved. The current order was restored.'),
            onFinish: () => setReorderPending(false),
        });
    };

    const reorderList = (folderId: number | null, taskListIds: number[]) => {
        setReorderPending(true);
        router.put(listRoutes.order(), { folder_id: folderId, task_list_ids: taskListIds }, {
            preserveScroll: true,
            onError: (errors) => setNotice(Object.values(errors)[0] ?? 'The list order could not be saved. The current order was restored.'),
            onFinish: () => setReorderPending(false),
        });
    };

    const moveList = (list: NavigationList, folderId: number | null, onRejected: () => void) => {
        setReorderPending(true);
        router.post(listRoutes.move(list.id), { folder_id: folderId }, {
            preserveScroll: true,
            onError: (errors) => {
                onRejected();
                setNotice(Object.values(errors)[0] ?? 'The list could not be moved.');
            },
            onFinish: () => setReorderPending(false),
        });
    };

    const setInvitationResponding = (invitationId: number, responding: boolean) => {
        setRespondingInvitationIds((current) => responding
            ? [...new Set([...current, invitationId])]
            : current.filter((id) => id !== invitationId));
    };

    const respondToInvitation = (invitationId: number, accepting: boolean) => {
        setInvitationResponding(invitationId, true);
        const route = accepting ? invitationRoutes.accept(invitationId) : invitationRoutes.decline(invitationId);

        router.post(route, {}, {
            preserveScroll: true,
            onError: (errors) => setNotice(Object.values(errors)[0] ?? 'That invitation could not be updated.'),
            onFinish: () => setInvitationResponding(invitationId, false),
        });
    };

    const acceptInvitation = (invitationId: number) => respondToInvitation(invitationId, true);

    const declineInvitation = (invitationId: number) => respondToInvitation(invitationId, false);

    const openNotification = (notificationId: string) => {
        router.post(notificationRoutes.open(notificationId), {}, {
            onError: () => setNotice('That notification could not be opened.'),
        });
    };

    const toggleNotificationRead = (notification: NotificationItem) => {
        router.patch(notificationRoutes.update(notification.id), { read: notification.readAt === null }, {
            preserveScroll: true,
            onError: () => setNotice('That notification status could not be updated.'),
        });
    };

    const openShareDialog = () => {
        shareDetailsRequestId.current += 1;
        setBackgroundShareList(null);
        setShareDetailsLoading(false);
        setShareDetailsError('');
        setShareInviteError('');
        setShareDialogOpen(true);
    };

    const loadBackgroundShareList = (list: NavigationList, showLoading: boolean) => {
        const requestId = shareDetailsRequestId.current + 1;
        shareDetailsRequestId.current = requestId;

        if (showLoading) {
            setShareDetailsLoading(true);
            setShareDetailsError('');
        }

        const fail = () => {
            if (shareDetailsRequestId.current === requestId) {
                setShareDetailsError('That list’s sharing details could not be loaded.');
            }
        };

        router.reload({
            data: { sharing_list_id: list.id },
            only: ['sharingDialog'],
            preserveUrl: true,
            onSuccess: (response) => {
                const details = response.props.sharingDialog as CurrentListDetails | null | undefined;

                if (shareDetailsRequestId.current !== requestId) return;

                if (!details || details.id !== list.id) {
                    fail();
                    return;
                }

                setBackgroundShareList(details);
                setShareDetailsError('');
            },
            onError: fail,
            onHttpException: fail,
            onNetworkError: fail,
            onFinish: () => {
                if (shareDetailsRequestId.current === requestId) {
                    setShareDetailsLoading(false);
                }
            },
        });
    };

    const refreshBackgroundShareList = () => {
        if (backgroundShareList) {
            loadBackgroundShareList(backgroundShareList, false);
        }
    };

    const openListShareDialog = (list: NavigationList) => {
        if (workspace.currentList?.id === list.id) {
            openShareDialog();
            return;
        }

        setBackgroundShareList({
            ...list,
            members: [],
            pendingInvitations: [],
            canManageSharing: list.isOwner,
        });
        setShareInviteError('');
        setShareDialogOpen(true);
        loadBackgroundShareList(list, true);
    };

    const closeShareDialog = () => {
        shareDetailsRequestId.current += 1;
        setShareDialogOpen(false);
        setBackgroundShareList(null);
        setShareDetailsLoading(false);
        setShareDetailsError('');
        setShareInviteError('');
    };

    const inviteMember = (email: string) => {
        if (!shareDialogList) return;

        setShareInviteProcessing(true);
        setShareInviteError('');
        router.post(listMemberRoutes.store(shareDialogList.id), { email }, {
            preserveScroll: true,
            onSuccess: refreshBackgroundShareList,
            onError: (errors) => setShareInviteError(errors.email ?? errors.domain ?? 'That invitation could not be sent.'),
            onFinish: () => setShareInviteProcessing(false),
        });
    };

    const setMemberRevoking = (userId: number, revoking: boolean) => {
        setRevokingMemberIds((current) => revoking
            ? [...new Set([...current, userId])]
            : current.filter((id) => id !== userId));
    };

    // The same underlying route (DELETE lists/{list}/members/{user}) revokes
    // both an accepted member and a still-pending invitation — the backend
    // doesn't distinguish, so this one handler serves both
    // ShareDialog callbacks (`onRevokeMember`/`onRevokeInvitation`).
    const revokeMembership = (userId: number) => {
        if (!shareDialogList) return;

        setMemberRevoking(userId, true);
        router.delete(listMemberRoutes.destroy([shareDialogList.id, userId]), {
            preserveScroll: true,
            onSuccess: refreshBackgroundShareList,
            onError: (errors) => setNotice(Object.values(errors)[0] ?? 'That member could not be removed.'),
            onFinish: () => setMemberRevoking(userId, false),
        });
    };

    // TaskListMembershipController::destroy() (web) redirects to the inbox
    // route on success, and Inertia's router.delete() follows that redirect
    // as part of the same visit — no extra client-side navigation is needed
    // here on top of it.
    const leaveList = (list: NavigationList) => {
        // No preserveScroll — a successful leave redirects to a different
        // page entirely (the inbox), so there is no scroll position on this
        // page worth preserving into it.
        router.delete(listMembershipRoutes.destroy(list.id), {
            onError: (errors) => setNotice(Object.values(errors)[0] ?? 'You could not leave this list.'),
        });
    };

    return (
        <DragDropProvider
            onCollision={handleCollision}
            onDragEnd={handleTaskDragEnd}
            onDragMove={handleDragMove}
            onDragOver={handleDragOver}
            onDragStart={handleDragStart}
            sensors={(defaults) => [
                ...defaults.filter((sensor) => sensor !== PointerSensor),
                wholeItemPointerSensor,
            ]}
        >
        <div className="app-frame" style={workspaceBackgroundStyle(user.workspaceBackground)}>
            <Head title={`${workspace.heading} · Purplelist`} />
            <Sidebar
                activeView={workspace.view}
                currentListId={workspace.currentList?.id ?? null}
                folders={workspace.folders}
                inbox={workspace.inbox}
                lastCollisionTargetId={lastCollisionTargetId}
                mobileOpen={mobileNavOpen}
                onCloseMobile={() => setMobileNavOpen(false)}
                onDeleteFolder={(folder) => { setEntityError(''); setDeleteDialog({ kind: 'folder', item: folder }); }}
                onDeleteList={(list) => { setEntityError(''); setDeleteDialog({ kind: 'list', item: list }); }}
                onEditFolder={openEditFolder}
                onEditList={openEditList}
                onLeaveList={leaveList}
                onMoveList={moveList}
                onNavigate={() => setSelectedTaskId(null)}
                onOpenCreate={openCreate}
                onOpenProfile={openProfile}
                onShareList={openListShareDialog}
                onReorderFolder={reorderFolder}
                onReorderList={reorderList}
                reorderPending={reorderPending}
                starredCount={workspace.starredCount}
                ungroupedLists={workspace.ungroupedLists}
                unreadNotificationCount={page.props.notifications.unreadCount}
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
                        {workspace.currentList && !workspace.currentList.isDefault ? (
                            <button aria-label={`Share “${workspace.currentList.name}”`} onClick={openShareDialog} type="button">
                                <Icon name="user" size={19} /><span>Share</span>
                            </button>
                        ) : (
                            <button aria-label="More list options are available in the sidebar" disabled type="button"><Icon name="more" size={19} /><span>More</span></button>
                        )}
                    </div>
                </header>

                {workspace.view === 'notifications' ? (
                    <NotificationCenterView
                        items={workspace.notificationItems}
                        onAccept={acceptInvitation}
                        onDecline={declineInvitation}
                        onOpen={openNotification}
                        onToggleRead={toggleNotificationRead}
                        respondingInvitationIds={respondingInvitationIds}
                    />
                ) : <div className="task-canvas">
                    <div className="task-column">
                        {workspace.canAddTask && (
                            <form className="task-composer" onSubmit={addTask}>
                                <button
                                    aria-label="Focus new task input"
                                    className="task-composer-focus"
                                    disabled={quickAdd.processing}
                                    onClick={() => inputRef.current?.focus()}
                                    type="button"
                                >
                                    <Icon name="plus" size={21} />
                                </button>
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
                                                onDelete={(item) => setDeleteDialog({ kind: 'task', item })}
                                                onSelect={(selected) => { setTaskErrors({}); setCommentError(''); setSelectedTaskId(selected.id); }}
                                                onToggleComplete={toggleComplete}
                                                onToggleStar={toggleStar}
                                                pending={pendingTaskIds.includes(task.id)}
                                                canMoveAcrossLists={Boolean(workspace.currentList?.isOwner && !workspace.currentList.isShared)}
                                                sortableDisabled={!workspace.currentList || reorderPending}
                                                sortableIndex={index}
                                                task={task}
                                                transitionState={completingTaskIds.includes(task.id) ? 'completing' : undefined}
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
                                                onSelect={(selected) => { setTaskErrors({}); setCommentError(''); setSelectedTaskId(selected.id); }}
                                                onToggleComplete={toggleComplete}
                                                onToggleStar={toggleStar}
                                                pending={pendingTaskIds.includes(task.id)}
                                                task={task}
                                                transitionState={completedArrivalTaskIds.includes(task.id) ? 'completed-arrival' : undefined}
                                            />
                                        ))}
                                    </div>
                                )}
                            </section>
                        )}
                    </div>
                </div>}
            </main>

            {selectedTask && (
                <TaskDetails
                    commentError={commentError}
                    commentProcessing={commentProcessing}
                    errors={taskErrors}
                    lists={destinationLists}
                    onAddComment={addComment}
                    onAddSubtask={addSubtask}
                    onClose={() => setSelectedTaskId(null)}
                    onDelete={() => setDeleteDialog({ kind: 'task', item: selectedTask })}
                    onDeleteSubtask={deleteSubtask}
                    onRenameSubtask={renameSubtask}
                    onSave={saveTask}
                    onToggleSubtask={toggleSubtask}
                    onToggleComplete={toggleCompleteFromDetails}
                    onToggleStar={toggleStar}
                    processing={pendingTaskIds.includes(selectedTask.id)}
                    pendingSubtaskIds={pendingSubtaskIds}
                    subtaskCreating={subtaskCreating}
                    subtaskError={subtaskError}
                    task={selectedTask}
                />
            )}

            {(undo || notice) && (
                <div className="undo-bar" role="status">
                    <span>{undo?.message ?? notice}</span>
                    <button onClick={() => { setUndo(null); setNotice(''); }} type="button">Dismiss</button>
                    {undo && <button onClick={() => { undo.execute(); setUndo(null); setNotice(''); }} type="button">Undo</button>}
                </div>
            )}

            <ProfileSettingsDialog
                backgroundOptions={page.props.workspaceBackgroundOptions}
                completionSoundOptions={page.props.completionSoundOptions}
                currentBackground={user.workspaceBackground}
                currentCompletionSound={user.completionSound}
                email={user.email}
                emailVerified={user.emailVerified}
                hasPassword={user.hasPassword}
                onClose={closeProfile}
                onSaveProfile={saveProfile}
                onSavePassword={savePassword}
                open={profileDialogOpen}
                passwordForm={passwordForm}
                passwordRequirements={page.props.passwordRequirements}
                profileForm={profileForm}
                successMessage={profileSuccess}
            />

            {user.completionSound && (
                <audio aria-hidden="true" preload="auto" ref={completionSoundAudioRef} src={user.completionSound.url} />
            )}

            <UseCaseDialog
                open={workspace.view === 'inbox' && page.props.onboarding.pending}
                options={page.props.onboarding.useCaseOptions}
            />

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

            <ShareDialog
                detailsError={shareDetailsError}
                detailsLoading={shareDetailsLoading}
                inviteError={shareInviteError}
                inviteProcessing={shareInviteProcessing}
                list={shareDialogList}
                onClose={closeShareDialog}
                onInvite={inviteMember}
                onRevokeInvitation={revokeMembership}
                onRevokeMember={revokeMembership}
                open={shareDialogOpen}
                revokingIds={revokingMemberIds}
                viewerId={user.id}
            />

        </div>
        </DragDropProvider>
    );
}
