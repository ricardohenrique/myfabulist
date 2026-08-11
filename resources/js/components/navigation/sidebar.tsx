import { DragDropProvider, type DragEndEvent } from '@dnd-kit/react';
import { isSortable, useSortable } from '@dnd-kit/react/sortable';
import { Link } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Icon } from '@/components/ui/icon';
import { Logo } from '@/components/ui/logo';
import { moveItem, orderByIds } from '@/lib/sortable';
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
    onCloseMobile: () => void;
    onOpenCreate: (kind: 'folder' | 'list') => void;
    onEditFolder: (folder: NavigationFolder) => void;
    onDeleteFolder: (folder: NavigationFolder) => void;
    onReorderFolder: (folderIds: number[]) => void;
    onEditList: (list: NavigationList) => void;
    onDeleteList: (list: NavigationList) => void;
    onReorderList: (folderId: number | null, taskListIds: number[]) => void;
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
    onToggleMenu: () => void;
};

function Count({ value }: { value: number }) {
    return value > 0 ? <span className="nav-count">{value}</span> : null;
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
    onToggleMenu,
}: SortableListRowProps) {
    const sortable = useSortable({
        id: `list-${list.id}`,
        index,
        group: `lists-${list.folderId ?? 'ungrouped'}`,
        type: 'list',
        disabled: reorderPending || itemCount < 2,
    });

    return (
        <div
            className={`nav-item-wrap is-sortable ${sortable.isDragging ? 'is-dragging' : ''} ${sortable.isDropTarget ? 'is-drop-target' : ''}`}
            ref={sortable.ref}
        >
            <button
                aria-label={`Reorder list ${list.name}`}
                className={`nav-drag-handle ${nested ? 'is-nested' : ''}`}
                disabled={reorderPending || itemCount < 2}
                ref={sortable.handleRef}
                title="Drag to reorder. With a keyboard, press Space, use the arrow keys, then press Space again."
                type="button"
            >
                <Icon name="grip" size={15} />
            </button>
            <Link
                className={`nav-row ${nested ? 'nav-row--nested' : ''} ${active ? 'is-active' : ''}`}
                href={showList(list.id)}
                onClick={onCloseMobile}
            >
                <Icon className="nav-icon" name="list" size={16} />
                <span>{list.name}</span>
                <Count value={list.activeTaskCount} />
            </Link>
            <button
                aria-expanded={menuOpen}
                aria-label={`More options for ${list.name}`}
                className="row-more nav-item-more"
                onClick={onToggleMenu}
                type="button"
            >
                <Icon name="more" size={17} />
            </button>
            {menuOpen && (
                <div className="navigation-menu">
                    <button onClick={() => onEdit(list)} type="button">Rename or move…</button>
                    <button className="is-danger" onClick={() => onDelete(list)} type="button">Delete list</button>
                </div>
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
        <DragDropProvider onDragEnd={handleDragEnd} onDragStart={onCloseMenu}>
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
    onDeleteFolder: (folder: NavigationFolder) => void;
    onDeleteList: (list: NavigationList) => void;
    onEditFolder: (folder: NavigationFolder) => void;
    onEditList: (list: NavigationList) => void;
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
    onDeleteFolder,
    onDeleteList,
    onEditFolder,
    onEditList,
    onReorderLists,
    onToggle,
    onToggleMenu,
}: SortableFolderProps) {
    const sortable = useSortable({
        id: `folder-${folder.id}`,
        index,
        type: 'folder',
        disabled: reorderPending || itemCount < 2,
    });
    const menuKey = `folder-${folder.id}`;

    return (
        <div
            className={`folder-group is-sortable ${sortable.isDragging ? 'is-dragging' : ''} ${sortable.isDropTarget ? 'is-drop-target' : ''}`}
            ref={sortable.ref}
        >
            <div className="folder-row">
                <button
                    aria-label={`Reorder folder ${folder.name}`}
                    className="folder-drag-handle"
                    disabled={reorderPending || itemCount < 2}
                    ref={sortable.handleRef}
                    title="Drag to reorder. With a keyboard, press Space, use the arrow keys, then press Space again."
                    type="button"
                >
                    <Icon name="grip" size={15} />
                </button>
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
                    type="button"
                >
                    <Icon name="more" size={17} />
                </button>
                {openMenu === menuKey && (
                    <div className="navigation-menu">
                        <button onClick={() => onEditFolder(folder)} type="button">Rename folder…</button>
                        <button className="is-danger" onClick={() => onDeleteFolder(folder)} type="button">Delete folder</button>
                    </div>
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
    onCloseMobile,
    onOpenCreate,
    onEditFolder,
    onDeleteFolder,
    onReorderFolder,
    onEditList,
    onDeleteList,
    onReorderList,
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

    const editFolder = (folder: NavigationFolder) => {
        onEditFolder(folder);
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

    const deleteList = (list: NavigationList) => {
        onDeleteList(list);
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
                        <button aria-label="Notifications (not available yet)" className="icon-button" disabled type="button"><Icon name="bell" size={18} /></button>
                        <button aria-label="Search tasks (not available yet)" className="icon-button" disabled type="button"><Icon name="search" size={18} /></button>
                    </div>
                    {profileOpen && (
                        <div className="account-menu">
                            <p>Account</p>
                            <Link as="button" href={logout()} method="post">Sign out</Link>
                        </div>
                    )}
                </div>

                <nav className="sidebar-nav">
                    <div className="smart-list-group">
                        <Link className={`nav-row ${activeView === 'inbox' ? 'is-active' : ''}`} href={inboxRoute()} onClick={onCloseMobile}>
                            <Icon className="nav-icon nav-icon--inbox" name="inbox" size={18} />
                            <span>Inbox</span>
                            <Count value={inbox.activeTaskCount} />
                        </Link>
                        <Link className={`nav-row ${activeView === 'starred' ? 'is-active' : ''}`} href={starred()} onClick={onCloseMobile}>
                            <Icon className="nav-icon nav-icon--star" fill name="star" size={18} />
                            <span>Starred</span>
                            <Count value={starredCount} />
                        </Link>
                    </div>

                    <div className="navigation-scroll">
                        <div className="section-label"><span>Folders & lists</span></div>
                        <DragDropProvider onDragEnd={handleFolderDragEnd} onDragStart={() => setOpenMenu(null)}>
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
                                    onCloseMobile={onCloseMobile}
                                    onDeleteFolder={deleteFolder}
                                    onDeleteList={deleteList}
                                    onEditFolder={editFolder}
                                    onEditList={editList}
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
                            onCloseMobile={onCloseMobile}
                            onDelete={deleteList}
                            onEdit={editList}
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
