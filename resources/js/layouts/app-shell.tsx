import { DragDropProvider, PointerSensor, type DragEndEvent } from '@dnd-kit/react';
import { isSortable } from '@dnd-kit/react/sortable';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState, type FormEvent } from 'react';
import { ShareDialog } from '@/components/lists/share-dialog';
import { Sidebar } from '@/components/navigation/sidebar';
import { TaskDetails } from '@/components/tasks/task-details';
import { TaskRow } from '@/components/tasks/task-row';
import { Button } from '@/components/ui/button';
import { Dialog } from '@/components/ui/dialog';
import { Icon } from '@/components/ui/icon';
import { WorkspaceBackgroundSection } from '@/components/settings/workspace-background-section';
import { moveItem, orderByIds, wholeItemPointerSensor } from '@/lib/sortable';
import { workspaceBackgroundStyle } from '@/lib/workspace-background';
import * as folderRoutes from '@/routes/folders';
import * as invitationRoutes from '@/routes/invitations';
import * as listRoutes from '@/routes/lists';
import * as listMemberRoutes from '@/routes/lists/members';
import * as listMembershipRoutes from '@/routes/lists/membership';
import { store as storeTask } from '@/routes/lists/tasks';
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
    PendingInvitationSummary,
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
    const [taskOrder, setTaskOrder] = useState<number[]>(() => workspace.tasks
        .filter((task) => !task.completedAt)
        .map((task) => task.id));
    const [reorderPending, setReorderPending] = useState(false);
    const [entityProcessing, setEntityProcessing] = useState(false);
    const [notice, setNotice] = useState('');
    const [undo, setUndo] = useState<UndoState | null>(null);
    const [notificationsOpen, setNotificationsOpen] = useState(false);
    const [respondingInvitationIds, setRespondingInvitationIds] = useState<number[]>([]);
    const [shareDialogOpen, setShareDialogOpen] = useState(false);
    const [backgroundShareList, setBackgroundShareList] = useState<CurrentListDetails | null>(null);
    const [shareDetailsLoading, setShareDetailsLoading] = useState(false);
    const [shareDetailsError, setShareDetailsError] = useState('');
    const [shareInviteError, setShareInviteError] = useState('');
    const [shareInviteProcessing, setShareInviteProcessing] = useState(false);
    const [revokingMemberIds, setRevokingMemberIds] = useState<number[]>([]);
    // Always starts `undefined` — `notifications.invitations` is `Inertia::optional()`
    // and is never present on the initial page load, only after the partial
    // reload `openNotifications` triggers below.
    const [invitations, setInvitations] = useState<PendingInvitationSummary[] | undefined>(undefined);
    const inputRef = useRef<HTMLInputElement>(null);
    const shareDetailsRequestId = useRef(0);
    const quickAdd = useForm({ title: '' });
    const profileForm = useForm({ name: user.name, email: user.email });
    const passwordForm = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    });

    const tasks = workspace.tasks;
    const activeTasks = orderByIds(tasks.filter((task) => !task.completedAt), taskOrder);
    const completedTasks = tasks.filter((task) => task.completedAt);
    const selectedTask = tasks.find((task) => task.id === selectedTaskId) ?? null;
    const shareDialogList = backgroundShareList ?? workspace.currentList;
    const destinationLists = useMemo(
        () => [workspace.inbox, ...workspace.folders.flatMap((folder) => folder.lists), ...workspace.ungroupedLists],
        [workspace],
    );

    useEffect(() => {
        setTaskOrder(tasks.filter((task) => !task.completedAt).map((task) => task.id));
    }, [tasks]);

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

    useEffect(() => {
        if (!undo && !notice) return;

        const timeoutId = window.setTimeout(() => {
            setUndo(null);
            setNotice('');
        }, NOTIFICATION_TIMEOUT_MS);

        return () => window.clearTimeout(timeoutId);
    }, [notice, undo]);

    // `notifications.invitations` is `Inertia::optional()` on the server, so
    // it is only present on the response that follows a partial reload
    // naming `notifications` (see `openNotifications` below). Every other
    // visit — including the plain `back()` redirect after accept/decline —
    // omits the key entirely, so this only re-seeds local state when a fresh
    // list actually arrives; it never resets `invitations` back to
    // `undefined` on an unrelated navigation.
    useEffect(() => {
        if (page.props.notifications.invitations !== undefined) {
            setInvitations(page.props.notifications.invitations);
        }
    }, [page.props.notifications.invitations]);

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

    const reorderTask = (taskIds: number[]) => {
        const canonicalIds = tasks.filter((task) => !task.completedAt).map((task) => task.id);

        setTaskOrder(taskIds);

        if (!workspace.currentList) return;

        setReorderPending(true);
        router.put(listRoutes.taskOrder(workspace.currentList.id), { task_ids: taskIds }, {
            preserveScroll: true,
            onError: (errors) => {
                setTaskOrder(canonicalIds);
                setNotice(Object.values(errors)[0] ?? 'The task order could not be saved. The current order was restored.');
            },
            onFinish: () => setReorderPending(false),
        });
    };

    const handleTaskDragEnd = (event: DragEndEvent) => {
        const source = event.operation.source;

        if (event.canceled || reorderPending || !isSortable(source)) {
            return;
        }

        const reordered = moveItem(activeTasks, source.initialIndex, source.index);

        if (reordered !== activeTasks) {
            reorderTask(reordered.map((task) => task.id));
        }
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

    const setInvitationResponding = (invitationId: number, responding: boolean) => {
        setRespondingInvitationIds((current) => responding
            ? [...new Set([...current, invitationId])]
            : current.filter((id) => id !== invitationId));
    };

    const openNotifications = () => {
        setNotificationsOpen(true);

        // Nothing pending — skip the round trip and the "Loading…" flash for
        // what's the overwhelmingly common case.
        if (page.props.notifications.pendingInvitationCount === 0) {
            setInvitations([]);
            return;
        }

        router.reload({
            only: ['notifications'],
            onError: () => setNotice('Notifications could not be loaded.'),
            // If the reload never resolves into a fresh `invitations` array
            // (a network error, not a validation error `onError` already
            // handles), fall back to the empty state instead of leaving the
            // panel stuck on "Loading…" forever. A no-op once the prop-sync
            // effect above has already populated a real list.
            onFinish: () => setInvitations((current) => current ?? []),
        });
    };

    const closeNotifications = () => {
        setNotificationsOpen(false);
        // Every open should be a genuinely fresh load — otherwise a stale
        // row (e.g. one the owner already revoked) could sit in the panel
        // and surface as a raw Inertia error on Accept/Decline instead of
        // the normal notice/flash path.
        setInvitations(undefined);
    };

    const toggleNotifications = () => {
        if (notificationsOpen) {
            closeNotifications();
        } else {
            openNotifications();
        }
    };

    const respondToInvitation = (invitationId: number, accepting: boolean) => {
        setInvitationResponding(invitationId, true);
        const route = accepting ? invitationRoutes.accept(invitationId) : invitationRoutes.decline(invitationId);

        router.post(route, {}, {
            preserveScroll: true,
            onSuccess: () => setInvitations((current) => current?.filter((invitation) => invitation.id !== invitationId)),
            onError: (errors) => setNotice(Object.values(errors)[0] ?? 'That invitation could not be updated.'),
            onFinish: () => setInvitationResponding(invitationId, false),
        });
    };

    const acceptInvitation = (invitationId: number) => respondToInvitation(invitationId, true);

    const declineInvitation = (invitationId: number) => respondToInvitation(invitationId, false);

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
        <div className="app-frame" style={workspaceBackgroundStyle(user.workspaceBackground)}>
            <Head title={`${workspace.heading} · Purplelist`} />
            <Sidebar
                activeView={workspace.view}
                currentListId={workspace.currentList?.id ?? null}
                folders={workspace.folders}
                inbox={workspace.inbox}
                invitations={invitations}
                mobileOpen={mobileNavOpen}
                notificationsOpen={notificationsOpen}
                onAcceptInvitation={acceptInvitation}
                onCloseMobile={() => setMobileNavOpen(false)}
                onCloseNotifications={closeNotifications}
                onDeclineInvitation={declineInvitation}
                onDeleteFolder={(folder) => { setEntityError(''); setDeleteDialog({ kind: 'folder', item: folder }); }}
                onDeleteList={(list) => { setEntityError(''); setDeleteDialog({ kind: 'list', item: list }); }}
                onEditFolder={openEditFolder}
                onEditList={openEditList}
                onLeaveList={leaveList}
                onNavigate={() => setSelectedTaskId(null)}
                onOpenCreate={openCreate}
                onOpenProfile={openProfile}
                onShareList={openListShareDialog}
                onReorderFolder={reorderFolder}
                onReorderList={reorderList}
                onToggleNotifications={toggleNotifications}
                pendingInvitationCount={page.props.notifications.pendingInvitationCount}
                reorderPending={reorderPending}
                respondingInvitationIds={respondingInvitationIds}
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
                        {workspace.currentList && !workspace.currentList.isDefault ? (
                            <button aria-label={`Share “${workspace.currentList.name}”`} onClick={openShareDialog} type="button">
                                <Icon name="user" size={19} /><span>Share</span>
                            </button>
                        ) : (
                            <button aria-label="More list options are available in the sidebar" disabled type="button"><Icon name="more" size={19} /><span>More</span></button>
                        )}
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
                                <DragDropProvider
                                    onDragEnd={handleTaskDragEnd}
                                    sensors={(defaults) => [
                                        ...defaults.filter((sensor) => sensor !== PointerSensor),
                                        wholeItemPointerSensor,
                                    ]}
                                >
                                    <div className="task-list">
                                        {activeTasks.map((task, index) => (
                                            <TaskRow
                                                key={task.id}
                                                onDelete={(item) => setDeleteDialog({ kind: 'task', item })}
                                                onSelect={(selected) => { setTaskErrors({}); setCommentError(''); setSelectedTaskId(selected.id); }}
                                                onToggleComplete={toggleComplete}
                                                onToggleStar={toggleStar}
                                                pending={pendingTaskIds.includes(task.id)}
                                                sortableDisabled={!workspace.currentList || reorderPending || activeTasks.length < 2}
                                                sortableIndex={index}
                                                task={task}
                                            />
                                        ))}
                                    </div>
                                </DragDropProvider>
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
                    onToggleComplete={toggleComplete}
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

            <Dialog
                description="Update your account details without leaving your tasks."
                onClose={closeProfile}
                open={profileDialogOpen}
                panelClassName="dialog-panel--profile"
                title="Profile settings"
            >
                {profileSuccess && <p className="profile-modal__success" role="status">{profileSuccess}</p>}

                <div className="profile-modal__sections">
                    <section className="profile-modal__section" aria-labelledby="profile-details-heading">
                        <div className="profile-modal__section-heading">
                            <h3 id="profile-details-heading">Personal details</h3>
                            <p>Change the name and email used for your account.</p>
                        </div>

                        <form className="profile-modal__form" noValidate onSubmit={saveProfile}>
                            <label className="field-label" htmlFor="profile-name">Name</label>
                            <input
                                aria-invalid={Boolean(profileForm.errors.name)}
                                autoComplete="name"
                                className="text-field"
                                id="profile-name"
                                onChange={(event) => profileForm.setData('name', event.target.value)}
                                value={profileForm.data.name}
                            />
                            {profileForm.errors.name && <p className="field-error">{profileForm.errors.name}</p>}

                            <label className="field-label" htmlFor="profile-email">Email address</label>
                            <input
                                aria-invalid={Boolean(profileForm.errors.email)}
                                autoComplete="email"
                                className="text-field"
                                id="profile-email"
                                onChange={(event) => profileForm.setData('email', event.target.value)}
                                type="email"
                                value={profileForm.data.email}
                            />
                            {profileForm.errors.email && <p className="field-error">{profileForm.errors.email}</p>}

                            <div className="profile-modal__actions">
                                <Button disabled={profileForm.processing} type="submit" variant="primary">
                                    {profileForm.processing ? 'Saving…' : 'Save profile'}
                                </Button>
                            </div>
                        </form>
                    </section>

                    <section className="profile-modal__section" aria-labelledby="profile-password-heading">
                        <div className="profile-modal__section-heading">
                            <h3 id="profile-password-heading">Change password</h3>
                            <p>Confirm your current password before choosing a new one.</p>
                        </div>

                        <form className="profile-modal__form" noValidate onSubmit={savePassword}>
                            <label className="field-label" htmlFor="current-password">Current password</label>
                            <input
                                aria-invalid={Boolean(passwordForm.errors.current_password)}
                                autoComplete="current-password"
                                className="text-field"
                                id="current-password"
                                onChange={(event) => passwordForm.setData('current_password', event.target.value)}
                                type="password"
                                value={passwordForm.data.current_password}
                            />
                            {passwordForm.errors.current_password && <p className="field-error">{passwordForm.errors.current_password}</p>}

                            <label className="field-label" htmlFor="new-password">New password</label>
                            <input
                                aria-invalid={Boolean(passwordForm.errors.password)}
                                autoComplete="new-password"
                                className="text-field"
                                id="new-password"
                                onChange={(event) => passwordForm.setData('password', event.target.value)}
                                placeholder="At least 8 characters"
                                type="password"
                                value={passwordForm.data.password}
                            />
                            {passwordForm.errors.password && <p className="field-error">{passwordForm.errors.password}</p>}

                            <label className="field-label" htmlFor="new-password-confirmation">Confirm new password</label>
                            <input
                                autoComplete="new-password"
                                className="text-field"
                                id="new-password-confirmation"
                                onChange={(event) => passwordForm.setData('password_confirmation', event.target.value)}
                                type="password"
                                value={passwordForm.data.password_confirmation}
                            />

                            <div className="profile-modal__actions">
                                <Button disabled={passwordForm.processing} type="submit" variant="primary">
                                    {passwordForm.processing ? 'Updating…' : 'Update password'}
                                </Button>
                            </div>
                        </form>
                    </section>

                    <WorkspaceBackgroundSection
                        currentBackground={user.workspaceBackground}
                        options={page.props.workspaceBackgroundOptions}
                    />
                </div>
            </Dialog>

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
    );
}
