import { useDragDropMonitor, useDragOperation, useDroppable, type DragEndEvent } from '@dnd-kit/react';
import { OptimisticSortingPlugin } from '@dnd-kit/dom/sortable';
import { isSortable, useSortable } from '@dnd-kit/react/sortable';
import { Link } from '@inertiajs/react';
import { useEffect, useLayoutEffect, useRef, useState, type ReactNode, type RefObject } from 'react';
import { createPortal } from 'react-dom';
import { NotificationCenter } from '@/components/navigation/notification-center';
import { Button } from '@/components/ui/button';
import { Icon } from '@/components/ui/icon';
import { Logo } from '@/components/ui/logo';
import { moveItem, orderByIds } from '@/lib/sortable';
import { workspaceDragData } from '@/lib/workspace-drag';
import { inbox as inboxRoute, logout, starred } from '@/routes';
import { show as showList } from '@/routes/lists';
import type { NavigationFolder, NavigationList, UserSummary, WorkspaceView } from '@/types';

type SidebarProps = {
    user: UserSummary;
    inbox: NavigationList;
    starredCount: number;
    folders: NavigationFolder[];
    ungroupedLists: NavigationList[];
    activeView: WorkspaceView;
    currentListId: number | null;
    mobileOpen: boolean;
    reorderPending?: boolean;
    unreadNotificationCount: number;
    onCloseMobile: () => void;
    onNavigate: () => void;
    onOpenProfile: () => void;
    onOpenCreate: (kind: 'folder' | 'list', folderId?: number) => void;
    onEditFolder: (folder: NavigationFolder) => void;
    onDeleteFolder: (folder: NavigationFolder) => void;
    onReorderFolder: (folderIds: number[]) => void;
    onEditList: (list: NavigationList) => void;
    onShareList: (list: NavigationList) => void;
    onDeleteList: (list: NavigationList) => void;
    onLeaveList: (list: NavigationList) => void;
    onReorderList: (folderId: number | null, taskListIds: number[]) => void;
    onMoveList: (list: NavigationList, folderId: number | null, onRejected: () => void) => void;
    lastCollisionTargetId: RefObject<string | null>;
};

type SortableListRowProps = {
    list: NavigationList;
    index: number;
    nested: boolean;
    active: boolean;
    reorderPending: boolean;
    menuOpen: boolean;
    onCloseMobile: () => void;
    onDelete: (list: NavigationList) => void;
    onEdit: (list: NavigationList) => void;
    onShare: (list: NavigationList) => void;
    onLeave: (list: NavigationList) => void;
    onToggleMenu: () => void;
};

function Count({ value }: { value: number }) {
    return value > 0 ? <span className="nav-count">{value}</span> : null;
}

type NavigationMenuProps = {
    anchorRef: RefObject<HTMLButtonElement | null>;
    children: ReactNode;
};

function NavigationMenu({ anchorRef, children }: NavigationMenuProps) {
    const menuRef = useRef<HTMLDivElement>(null);
    const [position, setPosition] = useState<{ left: number; top: number } | null>(null);

    useLayoutEffect(() => {
        const updatePosition = () => {
            const anchor = anchorRef.current;
            const menu = menuRef.current;

            if (!anchor || !menu) return;

            const viewportPadding = 8;
            const gap = 4;
            const anchorRect = anchor.getBoundingClientRect();
            const menuRect = menu.getBoundingClientRect();
            const left = Math.min(
                window.innerWidth - menuRect.width - viewportPadding,
                Math.max(viewportPadding, anchorRect.right - menuRect.width),
            );
            const fitsBelow = anchorRect.bottom + gap + menuRect.height <= window.innerHeight - viewportPadding;
            const top = fitsBelow
                ? anchorRect.bottom + gap
                : Math.max(viewportPadding, anchorRect.top - menuRect.height - gap);

            setPosition({ left, top });
        };

        updatePosition();
        window.addEventListener('resize', updatePosition);
        window.addEventListener('scroll', updatePosition, true);

        return () => {
            window.removeEventListener('resize', updatePosition);
            window.removeEventListener('scroll', updatePosition, true);
        };
    }, [anchorRef]);

    return createPortal(
        <div
            className="navigation-menu navigation-menu--overlay"
            ref={menuRef}
            style={position ? { left: position.left, top: position.top } : undefined}
        >
            {children}
        </div>,
        document.body,
    );
}

function SortableListRow({
    list,
    index,
    nested,
    active,
    reorderPending,
    menuOpen,
    onCloseMobile,
    onDelete,
    onEdit,
    onShare,
    onLeave,
    onToggleMenu,
}: SortableListRowProps) {
    const menuTriggerRef = useRef<HTMLButtonElement>(null);
    const dragOperation = useDragOperation();
    const isDragSource = dragOperation.source?.id === `list-${list.id}`;
    const sortable = useSortable({
        id: `list-${list.id}`,
        index,
        group: `lists-${list.folderId ?? 'ungrouped'}`,
        type: 'list',
        // Container targets must beat the dragged sortable's own droppable
        // rectangle after dnd-kit promotes it into the top layer.
        collisionPriority: 3,
        accept: (source) => {
            const data = workspaceDragData(source);

            if (data?.kind === 'list') return true;

            return data?.kind === 'task'
                && data.canMoveAcrossLists
                && data.taskListId !== list.id
                && list.isOwner
                && !list.isShared;
        },
        data: {
            kind: 'list',
            listId: list.id,
            folderId: list.folderId,
            name: list.name,
        },
        // The optimistic plugin physically reparents DOM nodes between
        // sortable groups. Lists live in nested React trees (folder bodies
        // versus the ungrouped collection), so React must own that cross-
        // container reparenting after drop. Keep dnd-kit's keyboard plugin.
        plugins: (defaults) => defaults.filter((plugin) => plugin !== OptimisticSortingPlugin),
        disabled: {
            draggable: reorderPending,
            // The moving element follows the pointer in dnd-kit's default
            // feedback mode. Disable its own target while active so the
            // container beneath it wins collision detection.
            droppable: reorderPending || isDragSource,
        },
    });
    const sortableEnabled = !reorderPending;

    return (
        <div
            aria-label={sortableEnabled ? `Reorder or move list ${list.name}` : undefined}
            aria-roledescription={sortableEnabled ? 'sortable list' : undefined}
            className={`nav-item-wrap ${sortableEnabled ? 'is-sortable' : ''} ${sortable.isDragging ? 'is-dragging' : ''} ${sortable.isDropTarget ? 'is-drop-target' : ''}`}
            data-workspace-drop-id={`list-${list.id}`}
            ref={sortable.ref}
            role={sortableEnabled ? 'group' : undefined}
            tabIndex={sortableEnabled ? 0 : undefined}
        >
            <Link
                className={`nav-row ${nested ? 'nav-row--nested' : ''} ${active ? 'is-active' : ''}`}
                draggable={false}
                href={showList(list.id)}
                onClick={onCloseMobile}
                onContextMenu={(event) => {
                    if (window.matchMedia('(hover: none) and (pointer: coarse)').matches) {
                        event.preventDefault();
                    }
                }}
            >
                <Icon className="nav-icon" name="list" size={16} />
                <span>{list.name}</span>
                {list.isShared && (
                    <>
                        <Icon className="nav-shared-icon" name="user" size={12} />
                        <span className="sr-only">Shared</span>
                    </>
                )}
                <Count value={list.activeTaskCount} />
            </Link>
            <button
                aria-expanded={menuOpen}
                aria-label={`More options for ${list.name}`}
                className="row-more nav-item-more"
                onClick={onToggleMenu}
                ref={menuTriggerRef}
                type="button"
            >
                <Icon name="more" size={17} />
            </button>
            {menuOpen && (
                <NavigationMenu anchorRef={menuTriggerRef}>
                    <button onClick={() => onShare(list)} type="button">Share…</button>
                    <button onClick={() => onEdit(list)} type="button">Rename or move…</button>
                    {list.isOwner ? (
                        <button className="is-danger" onClick={() => onDelete(list)} type="button">Delete list</button>
                    ) : (
                        <button className="is-danger" onClick={() => onLeave(list)} type="button">Leave list</button>
                    )}
                </NavigationMenu>
            )}
        </div>
    );
}

type SortableListCollectionProps = {
    lists: NavigationList[];
    nested?: boolean;
    activeView: WorkspaceView;
    currentListId: number | null;
    reorderPending: boolean;
    openMenu: string | null;
    onCloseMobile: () => void;
    onDelete: (list: NavigationList) => void;
    onEdit: (list: NavigationList) => void;
    onShare: (list: NavigationList) => void;
    onLeave: (list: NavigationList) => void;
    onToggleMenu: (menuKey: string) => void;
};

function SortableListCollection({
    lists,
    nested = false,
    activeView,
    currentListId,
    reorderPending,
    openMenu,
    onCloseMobile,
    onDelete,
    onEdit,
    onShare,
    onLeave,
    onToggleMenu,
}: SortableListCollectionProps) {
    return (
        <>
            {lists.map((list, index) => {
                const menuKey = `list-${list.id}`;

                return (
                    <SortableListRow
                        active={activeView === 'list' && currentListId === list.id}
                        index={index}
                        key={list.id}
                        list={list}
                        menuOpen={openMenu === menuKey}
                        nested={nested}
                        onCloseMobile={onCloseMobile}
                        onDelete={onDelete}
                        onEdit={onEdit}
                        onShare={onShare}
                        onLeave={onLeave}
                        onToggleMenu={() => onToggleMenu(menuKey)}
                        reorderPending={reorderPending}
                    />
                );
            })}
        </>
    );
}

type SortableFolderProps = {
    folder: NavigationFolder;
    index: number;
    itemCount: number;
    expanded: boolean;
    activeView: WorkspaceView;
    currentListId: number | null;
    reorderPending: boolean;
    openMenu: string | null;
    onCloseMobile: () => void;
    onCreateList: (folder: NavigationFolder) => void;
    onDeleteFolder: (folder: NavigationFolder) => void;
    onDeleteList: (list: NavigationList) => void;
    onEditFolder: (folder: NavigationFolder) => void;
    onEditList: (list: NavigationList) => void;
    onShareList: (list: NavigationList) => void;
    onLeaveList: (list: NavigationList) => void;
    onToggle: () => void;
    onToggleMenu: (menuKey: string) => void;
};

function SortableFolder({
    folder,
    index,
    itemCount,
    expanded,
    activeView,
    currentListId,
    reorderPending,
    openMenu,
    onCloseMobile,
    onCreateList,
    onDeleteFolder,
    onDeleteList,
    onEditFolder,
    onEditList,
    onShareList,
    onLeaveList,
    onToggle,
    onToggleMenu,
}: SortableFolderProps) {
    const menuTriggerRef = useRef<HTMLButtonElement>(null);
    const dragOperation = useDragOperation();
    const isDragSource = dragOperation.source?.id === `folder-${folder.id}`;
    const sortable = useSortable({
        id: `folder-${folder.id}`,
        index,
        type: 'folder',
        collisionPriority: 4,
        accept: ['folder', 'list'],
        data: { kind: 'folder', folderId: folder.id },
        // OptimisticSortingPlugin is registered globally by any sortable
        // that includes it. Keep it disabled for every workspace sortable so
        // dnd-kit never reparents React-owned nodes between nested trees.
        plugins: (defaults) => defaults.filter((plugin) => plugin !== OptimisticSortingPlugin),
        disabled: {
            draggable: reorderPending || itemCount < 2,
            droppable: reorderPending || isDragSource,
        },
    });
    const menuKey = `folder-${folder.id}`;
    const sortableEnabled = !reorderPending && itemCount > 1;

    return (
        <div
            className={`folder-group ${sortableEnabled ? 'is-sortable' : ''} ${sortable.isDragging ? 'is-dragging' : ''} ${sortable.isDropTarget ? 'is-drop-target' : ''}`}
        >
            <div
                aria-label={sortableEnabled ? `Reorder folder ${folder.name}` : undefined}
                aria-roledescription={sortableEnabled ? 'sortable folder' : undefined}
                className="folder-row"
                data-workspace-drop-id={`folder-${folder.id}`}
                ref={sortable.ref}
                role={sortableEnabled ? 'group' : undefined}
                tabIndex={sortableEnabled ? 0 : undefined}
            >
                <button aria-expanded={expanded} className="folder-toggle" onClick={onToggle} type="button">
                    <Icon name={expanded ? 'chevron-down' : 'chevron-right'} size={15} />
                    <Icon className="folder-icon" name="folder" size={17} />
                    <span>{folder.name}</span>
                </button>
                <button
                    aria-expanded={openMenu === menuKey}
                    aria-label={`More options for ${folder.name}`}
                    className="row-more"
                    onClick={() => onToggleMenu(menuKey)}
                    ref={menuTriggerRef}
                    type="button"
                >
                    <Icon name="more" size={17} />
                </button>
                {openMenu === menuKey && (
                    <NavigationMenu anchorRef={menuTriggerRef}>
                        <button onClick={() => onCreateList(folder)} type="button">Add list…</button>
                        <button onClick={() => onEditFolder(folder)} type="button">Rename folder…</button>
                        <button className="is-danger" onClick={() => onDeleteFolder(folder)} type="button">Delete folder</button>
                    </NavigationMenu>
                )}
            </div>
            {expanded && folder.lists.length > 0 && (
                <div className="folder-lists">
                    <SortableListCollection
                        activeView={activeView}
                        currentListId={currentListId}
                        lists={folder.lists}
                        nested
                        onCloseMobile={onCloseMobile}
                        onDelete={onDeleteList}
                        onEdit={onEditList}
                        onShare={onShareList}
                        onLeave={onLeaveList}
                        onToggleMenu={onToggleMenu}
                        openMenu={openMenu}
                        reorderPending={reorderPending}
                    />
                </div>
            )}
        </div>
    );
}

export function Sidebar({
    user,
    inbox,
    starredCount,
    folders,
    ungroupedLists,
    activeView,
    currentListId,
    mobileOpen,
    reorderPending = false,
    unreadNotificationCount,
    onCloseMobile,
    onNavigate,
    onOpenProfile,
    onOpenCreate,
    onEditFolder,
    onDeleteFolder,
    onReorderFolder,
    onEditList,
    onShareList,
    onDeleteList,
    onLeaveList,
    onReorderList,
    onMoveList,
    lastCollisionTargetId,
}: SidebarProps) {
    const [orderedFolders, setOrderedFolders] = useState(folders);
    const [orderedUngroupedLists, setOrderedUngroupedLists] = useState(ungroupedLists);
    const [expandedFolders, setExpandedFolders] = useState<number[]>(folders.map((folder) => folder.id));
    const [profileOpen, setProfileOpen] = useState(false);
    const [openMenu, setOpenMenu] = useState<string | null>(null);
    const dragOperation = useDragOperation();
    const draggedData = workspaceDragData(dragOperation.source);
    const inboxDrop = useDroppable({
        id: `list-target-${inbox.id}`,
        type: 'list-target',
        collisionPriority: 4,
        data: {
            kind: 'list',
            listId: inbox.id,
            folderId: null,
            name: inbox.name,
        },
        accept: (source) => {
            const data = workspaceDragData(source);

            return data?.kind === 'task'
                && data.canMoveAcrossLists
                && data.taskListId !== inbox.id;
        },
        disabled: reorderPending,
    });
    const ungroupedDrop = useDroppable({
        id: 'list-container-ungrouped',
        type: 'list-container',
        collisionPriority: 4,
        data: { kind: 'list-container', folderId: null },
        accept: (source) => {
            const data = workspaceDragData(source);

            return data?.kind === 'list' && data.folderId !== null;
        },
        disabled: reorderPending,
    });

    useEffect(() => {
        setOrderedFolders(folders);
        setExpandedFolders((current) => {
            const existingIds = new Set(folders.map((folder) => folder.id));
            const retainedIds = current.filter((id) => existingIds.has(id));
            const addedIds = folders.map((folder) => folder.id).filter((id) => !retainedIds.includes(id));

            return [...retainedIds, ...addedIds];
        });
    }, [folders]);

    useEffect(() => {
        setOrderedUngroupedLists(ungroupedLists);
    }, [ungroupedLists]);

    const toggleFolder = (folderId: number) => {
        setExpandedFolders((current) => current.includes(folderId)
            ? current.filter((id) => id !== folderId)
            : [...current, folderId]);
    };

    const toggleMenu = (menuKey: string) => {
        setOpenMenu((current) => current === menuKey ? null : menuKey);
    };

    const navigate = () => {
        onNavigate();
        onCloseMobile();
    };

    const editFolder = (folder: NavigationFolder) => {
        onEditFolder(folder);
        setOpenMenu(null);
    };

    const createListInFolder = (folder: NavigationFolder) => {
        onOpenCreate('list', folder.id);
        setOpenMenu(null);
    };

    const deleteFolder = (folder: NavigationFolder) => {
        onDeleteFolder(folder);
        setOpenMenu(null);
    };

    const editList = (list: NavigationList) => {
        onEditList(list);
        setOpenMenu(null);
    };

    const shareList = (list: NavigationList) => {
        onShareList(list);
        setOpenMenu(null);
    };

    const deleteList = (list: NavigationList) => {
        onDeleteList(list);
        setOpenMenu(null);
    };

    const leaveList = (list: NavigationList) => {
        onLeaveList(list);
        setOpenMenu(null);
    };

    const reorderLists = (folderId: number | null, taskListIds: number[]) => {
        if (folderId === null) {
            setOrderedUngroupedLists((current) => orderByIds(current, taskListIds));
        } else {
            setOrderedFolders((current) => current.map((folder) => folder.id === folderId
                ? { ...folder, lists: orderByIds(folder.lists, taskListIds) }
                : folder));
        }

        onReorderList(folderId, taskListIds);
    };

    const listInFolder = (folderId: number | null): NavigationList[] => folderId === null
        ? orderedUngroupedLists
        : orderedFolders.find((folder) => folder.id === folderId)?.lists ?? [];

    const findList = (listId: number): NavigationList | undefined => [
        ...orderedUngroupedLists,
        ...orderedFolders.flatMap((folder) => folder.lists),
    ].find((list) => list.id === listId);

    const moveListLocally = (list: NavigationList, targetFolderId: number | null) => {
        const movedList = { ...list, folderId: targetFolderId };

        setOrderedUngroupedLists((current) => current.filter((item) => item.id !== list.id));
        setOrderedFolders((current) => current.map((folder) => ({
            ...folder,
            lists: folder.id === targetFolderId
                ? [...folder.lists.filter((item) => item.id !== list.id), movedList]
                : folder.lists.filter((item) => item.id !== list.id),
        })));

        if (targetFolderId === null) {
            setOrderedUngroupedLists((current) => [...current, movedList]);
        } else {
            setExpandedFolders((current) => current.includes(targetFolderId) ? current : [...current, targetFolderId]);
        }
    };

    const handleSidebarDragEnd = (event: DragEndEvent) => {
        const source = event.operation.source;
        const target = event.operation.target;
        const sourceData = workspaceDragData(source);
        const targetData = workspaceDragData(target);
        const collisionTargetId = lastCollisionTargetId.current;

        if (event.canceled || reorderPending || !sourceData) {
            return;
        }

        if (sourceData.kind === 'folder' && isSortable(source)) {
            const collisionFolderId = collisionTargetId?.startsWith('folder-')
                ? Number(collisionTargetId.slice('folder-'.length))
                : targetData?.kind === 'folder'
                    ? targetData.folderId
                    : null;
            const targetIndex = orderedFolders.findIndex((folder) => folder.id === collisionFolderId);
            const reordered = moveItem(orderedFolders, source.initialIndex, targetIndex);

            if (reordered !== orderedFolders) {
                setOrderedFolders(reordered);
                onReorderFolder(reordered.map((folder) => folder.id));
            }

            return;
        }

        if (sourceData.kind !== 'list' || !targetData) {
            return;
        }

        const collisionFolderId = collisionTargetId === 'list-container-ungrouped'
            ? null
            : collisionTargetId?.startsWith('folder-')
                ? Number(collisionTargetId.slice('folder-'.length))
                : collisionTargetId?.startsWith('list-')
                    ? findList(Number(collisionTargetId.slice('list-'.length)))?.folderId
                    : undefined;
        const targetFolderId = collisionFolderId !== undefined
                ? collisionFolderId
            : targetData.kind === 'folder'
                ? targetData.folderId
                : targetData.kind === 'list' || targetData.kind === 'list-container'
                    ? targetData.folderId
                    : undefined;

        if (targetFolderId === undefined) {
            return;
        }

        if (targetFolderId === sourceData.folderId) {
            if (!isSortable(source)) {
                return;
            }

            const lists = listInFolder(sourceData.folderId);
            const targetListId = collisionTargetId?.startsWith('list-')
                ? Number(collisionTargetId.slice('list-'.length))
                : targetData.kind === 'list'
                    ? targetData.listId
                    : null;
            const targetIndex = lists.findIndex((list) => list.id === targetListId);
            const reordered = moveItem(lists, source.initialIndex, targetIndex);

            if (reordered !== lists) {
                reorderLists(sourceData.folderId, reordered.map((list) => list.id));
            }

            return;
        }

        const list = findList(sourceData.listId);

        if (list) {
            moveListLocally(list, targetFolderId);
            onMoveList(list, targetFolderId, () => {
                setOrderedFolders(folders);
                setOrderedUngroupedLists(ungroupedLists);
            });
        }
    };

    useDragDropMonitor({
        onDragStart: () => setOpenMenu(null),
        onDragEnd: handleSidebarDragEnd,
    });

    const initials = user.name
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase())
        .join('');

    return (
        <>
            {mobileOpen && <button aria-label="Close navigation" className="sidebar-scrim" onClick={onCloseMobile} type="button" />}
            <aside aria-label="Task navigation" className={`sidebar ${mobileOpen ? 'is-mobile-open' : ''}`}>
                <div className="sidebar-mobile-heading">
                    <div className="sidebar-mobile-brand"><Logo size={32} /><span>Purplelist</span></div>
                    <Button aria-label="Close navigation" onClick={onCloseMobile} size="sm" variant="ghost"><Icon name="close" /></Button>
                </div>

                <div className="account-panel">
                    <button aria-expanded={profileOpen} className="account-trigger" onClick={() => setProfileOpen((open) => !open)} type="button">
                        <span className="avatar">{initials || 'MF'}</span>
                        <span className="account-copy"><strong>{user.name}</strong><small>{user.email}</small></span>
                        <Icon className="account-chevron" name="chevron-down" size={16} />
                    </button>
                    <div className="account-actions">
                        <NotificationCenter
                            active={activeView === 'notifications'}
                            onNavigate={navigate}
                            unreadCount={unreadNotificationCount}
                        />
                        <button aria-label="Search tasks (not available yet)" className="icon-button" disabled type="button"><Icon name="search" size={18} /></button>
                    </div>
                    {profileOpen && (
                        <div className="account-menu">
                            <p>Account</p>
                            <button onClick={() => { setProfileOpen(false); onOpenProfile(); }} type="button">Profile settings</button>
                            <Link as="button" href={logout()} method="post">Sign out</Link>
                        </div>
                    )}
                </div>

                <nav className="sidebar-nav">
                    <div className="smart-list-group">
                        <div className={`smart-list-drop-target ${inboxDrop.isDropTarget ? 'is-drop-target' : ''}`} data-workspace-drop-id={`list-target-${inbox.id}`} ref={inboxDrop.ref}>
                            <Link className={`nav-row ${activeView === 'inbox' ? 'is-active' : ''}`} href={inboxRoute()} onClick={navigate}>
                                <Icon className="nav-icon nav-icon--inbox" name="inbox" size={18} />
                                <span>Inbox</span>
                                <Count value={inbox.activeTaskCount} />
                            </Link>
                        </div>
                        <Link className={`nav-row ${activeView === 'starred' ? 'is-active' : ''}`} href={starred()} onClick={navigate}>
                            <Icon className="nav-icon nav-icon--star" fill name="star" size={18} />
                            <span>Starred</span>
                            <Count value={starredCount} />
                        </Link>
                    </div>

                    <div className="navigation-scroll">
                        <div className="section-label"><span>Folders & lists</span></div>
                        {orderedFolders.map((folder, index) => (
                            <SortableFolder
                                    activeView={activeView}
                                    currentListId={currentListId}
                                    expanded={expandedFolders.includes(folder.id)}
                                    folder={folder}
                                    index={index}
                                    itemCount={orderedFolders.length}
                                    key={folder.id}
                                    onCloseMobile={navigate}
                                    onCreateList={createListInFolder}
                                    onDeleteFolder={deleteFolder}
                                    onDeleteList={deleteList}
                                    onEditFolder={editFolder}
                                    onEditList={editList}
                                    onShareList={shareList}
                                    onLeaveList={leaveList}
                                    onToggle={() => toggleFolder(folder.id)}
                                    onToggleMenu={toggleMenu}
                                    openMenu={openMenu}
                                    reorderPending={reorderPending}
                            />
                        ))}

                        <div
                            aria-hidden={draggedData?.kind !== 'list'}
                            className={`ungrouped-drop-zone ${draggedData?.kind === 'list' ? 'is-visible' : ''} ${ungroupedDrop.isDropTarget ? 'is-drop-target' : ''}`}
                            data-workspace-drop-id="list-container-ungrouped"
                            ref={ungroupedDrop.ref}
                        >
                            Move outside folders
                        </div>

                        <SortableListCollection
                            activeView={activeView}
                            currentListId={currentListId}
                            lists={orderedUngroupedLists}
                            onCloseMobile={navigate}
                            onDelete={deleteList}
                            onEdit={editList}
                            onShare={shareList}
                            onLeave={leaveList}
                            onToggleMenu={toggleMenu}
                            openMenu={openMenu}
                            reorderPending={reorderPending}
                        />
                    </div>
                </nav>

                <div className="sidebar-create-actions">
                    <button onClick={() => onOpenCreate('list')} type="button"><Icon name="plus" size={17} />Create list</button>
                    <button onClick={() => onOpenCreate('folder')} type="button"><Icon name="folder" size={17} />New folder</button>
                </div>
            </aside>
        </>
    );
}
