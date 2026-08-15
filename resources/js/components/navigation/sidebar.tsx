import { DragDropProvider, PointerSensor, type DragEndEvent } from '@dnd-kit/react';
import { isSortable, useSortable } from '@dnd-kit/react/sortable';
import { Link } from '@inertiajs/react';
import { useEffect, useLayoutEffect, useRef, useState, type ReactNode, type RefObject } from 'react';
import { createPortal } from 'react-dom';
import { NotificationCenter } from '@/components/navigation/notification-center';
import { Button } from '@/components/ui/button';
import { Icon } from '@/components/ui/icon';
import { Logo } from '@/components/ui/logo';
import { moveItem, orderByIds, wholeItemPointerSensor } from '@/lib/sortable';
import { inbox as inboxRoute, logout, starred } from '@/routes';
import { show as showList } from '@/routes/lists';
import type { NavigationFolder, NavigationList, PendingInvitationSummary, UserSummary, WorkspaceView } from '@/types';

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
    pendingInvitationCount: number;
    invitations: PendingInvitationSummary[] | undefined;
    respondingInvitationIds: number[];
    notificationsOpen: boolean;
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
    onToggleNotifications: () => void;
    onCloseNotifications: () => void;
    onAcceptInvitation: (invitationId: number) => void;
    onDeclineInvitation: (invitationId: number) => void;
};

type SortableListRowProps = {
    list: NavigationList;
    index: number;
    itemCount: number;
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
    itemCount,
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
    const sortable = useSortable({
        id: `list-${list.id}`,
        index,
        group: `lists-${list.folderId ?? 'ungrouped'}`,
        type: 'list',
        disabled: reorderPending || itemCount < 2,
    });
    const sortableEnabled = !reorderPending && itemCount > 1;

    return (
        <div
            aria-label={sortableEnabled ? `Reorder list ${list.name}` : undefined}
            aria-roledescription={sortableEnabled ? 'sortable list' : undefined}
            className={`nav-item-wrap ${sortableEnabled ? 'is-sortable' : ''} ${sortable.isDragging ? 'is-dragging' : ''} ${sortable.isDropTarget ? 'is-drop-target' : ''}`}
            ref={sortableEnabled ? sortable.ref : undefined}
            role={sortableEnabled ? 'group' : undefined}
            tabIndex={sortableEnabled ? 0 : undefined}
        >
            <Link
                className={`nav-row ${nested ? 'nav-row--nested' : ''} ${active ? 'is-active' : ''}`}
                href={showList(list.id)}
                onClick={onCloseMobile}
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
    onCloseMenu: () => void;
    onDelete: (list: NavigationList) => void;
    onEdit: (list: NavigationList) => void;
    onShare: (list: NavigationList) => void;
    onLeave: (list: NavigationList) => void;
    onReorder: (taskListIds: number[]) => void;
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
    onCloseMenu,
    onDelete,
    onEdit,
    onShare,
    onLeave,
    onReorder,
    onToggleMenu,
}: SortableListCollectionProps) {
    const handleDragEnd = (event: DragEndEvent) => {
        const source = event.operation.source;

        if (event.canceled || reorderPending || !isSortable(source)) {
            return;
        }

        const reordered = moveItem(lists, source.initialIndex, source.index);

        if (reordered !== lists) {
            onReorder(reordered.map((list) => list.id));
        }
    };

    return (
        <DragDropProvider
            onDragEnd={handleDragEnd}
            onDragStart={onCloseMenu}
            sensors={(defaults) => [
                ...defaults.filter((sensor) => sensor !== PointerSensor),
                wholeItemPointerSensor,
            ]}
        >
            {lists.map((list, index) => {
                const menuKey = `list-${list.id}`;

                return (
                    <SortableListRow
                        active={activeView === 'list' && currentListId === list.id}
                        index={index}
                        itemCount={lists.length}
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
        </DragDropProvider>
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
    onCloseMenu: () => void;
    onCreateList: (folder: NavigationFolder) => void;
    onDeleteFolder: (folder: NavigationFolder) => void;
    onDeleteList: (list: NavigationList) => void;
    onEditFolder: (folder: NavigationFolder) => void;
    onEditList: (list: NavigationList) => void;
    onShareList: (list: NavigationList) => void;
    onLeaveList: (list: NavigationList) => void;
    onReorderLists: (folderId: number, taskListIds: number[]) => void;
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
    onCloseMenu,
    onCreateList,
    onDeleteFolder,
    onDeleteList,
    onEditFolder,
    onEditList,
    onShareList,
    onLeaveList,
    onReorderLists,
    onToggle,
    onToggleMenu,
}: SortableFolderProps) {
    const menuTriggerRef = useRef<HTMLButtonElement>(null);
    const sortable = useSortable({
        id: `folder-${folder.id}`,
        index,
        type: 'folder',
        disabled: reorderPending || itemCount < 2,
    });
    const menuKey = `folder-${folder.id}`;
    const sortableEnabled = !reorderPending && itemCount > 1;

    return (
        <div
            aria-label={sortableEnabled ? `Reorder folder ${folder.name}` : undefined}
            aria-roledescription={sortableEnabled ? 'sortable folder' : undefined}
            className={`folder-group ${sortableEnabled ? 'is-sortable' : ''} ${sortable.isDragging ? 'is-dragging' : ''} ${sortable.isDropTarget ? 'is-drop-target' : ''}`}
            ref={sortableEnabled ? sortable.ref : undefined}
            role={sortableEnabled ? 'group' : undefined}
            tabIndex={sortableEnabled ? 0 : undefined}
        >
            <div className="folder-row">
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
                        onCloseMenu={onCloseMenu}
                        onCloseMobile={onCloseMobile}
                        onDelete={onDeleteList}
                        onEdit={onEditList}
                        onShare={onShareList}
                        onLeave={onLeaveList}
                        onReorder={(taskListIds) => onReorderLists(folder.id, taskListIds)}
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
    pendingInvitationCount,
    invitations,
    respondingInvitationIds,
    notificationsOpen,
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
    onToggleNotifications,
    onCloseNotifications,
    onAcceptInvitation,
    onDeclineInvitation,
}: SidebarProps) {
    const [orderedFolders, setOrderedFolders] = useState(folders);
    const [orderedUngroupedLists, setOrderedUngroupedLists] = useState(ungroupedLists);
    const [expandedFolders, setExpandedFolders] = useState<number[]>(folders.map((folder) => folder.id));
    const [profileOpen, setProfileOpen] = useState(false);
    const [openMenu, setOpenMenu] = useState<string | null>(null);

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

    const handleFolderDragEnd = (event: DragEndEvent) => {
        const source = event.operation.source;

        if (event.canceled || reorderPending || !isSortable(source)) {
            return;
        }

        const reordered = moveItem(orderedFolders, source.initialIndex, source.index);

        if (reordered !== orderedFolders) {
            setOrderedFolders(reordered);
            onReorderFolder(reordered.map((folder) => folder.id));
        }
    };

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
                    <div className="sidebar-mobile-brand"><Logo size={32} /><span>My Fabulist</span></div>
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
                            invitations={invitations}
                            onAccept={onAcceptInvitation}
                            onClose={onCloseNotifications}
                            onDecline={onDeclineInvitation}
                            onToggle={onToggleNotifications}
                            open={notificationsOpen}
                            pendingCount={pendingInvitationCount}
                            respondingIds={respondingInvitationIds}
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
                        <Link className={`nav-row ${activeView === 'inbox' ? 'is-active' : ''}`} href={inboxRoute()} onClick={navigate}>
                            <Icon className="nav-icon nav-icon--inbox" name="inbox" size={18} />
                            <span>Inbox</span>
                            <Count value={inbox.activeTaskCount} />
                        </Link>
                        <Link className={`nav-row ${activeView === 'starred' ? 'is-active' : ''}`} href={starred()} onClick={navigate}>
                            <Icon className="nav-icon nav-icon--star" fill name="star" size={18} />
                            <span>Starred</span>
                            <Count value={starredCount} />
                        </Link>
                    </div>

                    <div className="navigation-scroll">
                        <div className="section-label"><span>Folders & lists</span></div>
                        <DragDropProvider
                            onDragEnd={handleFolderDragEnd}
                            onDragStart={() => setOpenMenu(null)}
                            sensors={(defaults) => [
                                ...defaults.filter((sensor) => sensor !== PointerSensor),
                                wholeItemPointerSensor,
                            ]}
                        >
                            {orderedFolders.map((folder, index) => (
                                <SortableFolder
                                    activeView={activeView}
                                    currentListId={currentListId}
                                    expanded={expandedFolders.includes(folder.id)}
                                    folder={folder}
                                    index={index}
                                    itemCount={orderedFolders.length}
                                    key={folder.id}
                                    onCloseMenu={() => setOpenMenu(null)}
                                    onCloseMobile={navigate}
                                    onCreateList={createListInFolder}
                                    onDeleteFolder={deleteFolder}
                                    onDeleteList={deleteList}
                                    onEditFolder={editFolder}
                                    onEditList={editList}
                                    onShareList={shareList}
                                    onLeaveList={leaveList}
                                    onReorderLists={reorderLists}
                                    onToggle={() => toggleFolder(folder.id)}
                                    onToggleMenu={toggleMenu}
                                    openMenu={openMenu}
                                    reorderPending={reorderPending}
                                />
                            ))}
                        </DragDropProvider>

                        <SortableListCollection
                            activeView={activeView}
                            currentListId={currentListId}
                            lists={orderedUngroupedLists}
                            onCloseMenu={() => setOpenMenu(null)}
                            onCloseMobile={navigate}
                            onDelete={deleteList}
                            onEdit={editList}
                            onShare={shareList}
                            onLeave={leaveList}
                            onReorder={(taskListIds) => reorderLists(null, taskListIds)}
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
