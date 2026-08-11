import { Link } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Icon } from '@/components/ui/icon';
import { Logo } from '@/components/ui/logo';
import { inbox as inboxRoute, logout, starred } from '@/routes';
import { show as showList } from '@/routes/lists';
import { show as prototypeRoute } from '@/routes/prototype';
import type { NavigationFolder, NavigationList, UserSummary, WorkspaceView } from '@/types';

type Direction = 'up' | 'down';

type SidebarProps = {
    user: UserSummary;
    inbox: NavigationList;
    starredCount: number;
    folders: NavigationFolder[];
    ungroupedLists: NavigationList[];
    activeView: WorkspaceView;
    currentListId: number | null;
    mobileOpen: boolean;
    prototype?: boolean;
    onCloseMobile: () => void;
    onOpenCreate: (kind: 'folder' | 'list') => void;
    onEditFolder: (folder: NavigationFolder) => void;
    onDeleteFolder: (folder: NavigationFolder) => void;
    onReorderFolder: (folder: NavigationFolder, direction: Direction) => void;
    onEditList: (list: NavigationList) => void;
    onDeleteList: (list: NavigationList) => void;
    onReorderList: (list: NavigationList, siblings: NavigationList[], direction: Direction) => void;
};

function Count({ value }: { value: number }) {
    return value > 0 ? <span className="nav-count">{value}</span> : null;
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
    prototype = false,
    onCloseMobile,
    onOpenCreate,
    onEditFolder,
    onDeleteFolder,
    onReorderFolder,
    onEditList,
    onDeleteList,
    onReorderList,
}: SidebarProps) {
    const [expandedFolders, setExpandedFolders] = useState<number[]>(folders.map((folder) => folder.id));
    const [profileOpen, setProfileOpen] = useState(false);
    const [openMenu, setOpenMenu] = useState<string | null>(null);

    const toggleFolder = (folderId: number) => {
        setExpandedFolders((current) => current.includes(folderId)
            ? current.filter((id) => id !== folderId)
            : [...current, folderId]);
    };

    const linkClicked = () => onCloseMobile();
    const initials = user.name
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase())
        .join('');

    const listRow = (list: NavigationList, siblings: NavigationList[], nested = false) => {
        const index = siblings.findIndex((sibling) => sibling.id === list.id);
        const menuKey = `list-${list.id}`;

        return (
            <div className="nav-item-wrap" key={list.id}>
                <Link
                    className={`nav-row ${nested ? 'nav-row--nested' : ''} ${activeView === 'list' && currentListId === list.id ? 'is-active' : ''}`}
                    href={prototype ? prototypeRoute(nested ? 'list' : 'empty') : showList(list.id)}
                    onClick={linkClicked}
                >
                    <Icon className="nav-icon" name="list" size={16} />
                    <span>{list.name}</span>
                    <Count value={list.activeTaskCount} />
                </Link>
                <button
                    aria-expanded={openMenu === menuKey}
                    aria-label={`More options for ${list.name}`}
                    className="row-more nav-item-more"
                    onClick={() => setOpenMenu((current) => current === menuKey ? null : menuKey)}
                    type="button"
                >
                    <Icon name="more" size={17} />
                </button>
                {openMenu === menuKey && (
                    <div className="navigation-menu">
                        <button onClick={() => { onEditList(list); setOpenMenu(null); }} type="button">Rename or move…</button>
                        <button disabled={index <= 0} onClick={() => { onReorderList(list, siblings, 'up'); setOpenMenu(null); }} type="button">Move up</button>
                        <button disabled={index === siblings.length - 1} onClick={() => { onReorderList(list, siblings, 'down'); setOpenMenu(null); }} type="button">Move down</button>
                        <button className="is-danger" onClick={() => { onDeleteList(list); setOpenMenu(null); }} type="button">Delete list</button>
                    </div>
                )}
            </div>
        );
    };

    return (
        <>
            {mobileOpen && <button aria-label="Close navigation" className="sidebar-scrim" onClick={onCloseMobile} type="button" />}
            <aside className={`sidebar ${mobileOpen ? 'is-mobile-open' : ''}`} aria-label="Task navigation">
                <div className="sidebar-mobile-heading">
                    <div className="sidebar-mobile-brand"><Logo size={32} /><span>My Fabulist</span></div>
                    <Button aria-label="Close navigation" onClick={onCloseMobile} size="sm" variant="ghost"><Icon name="close" /></Button>
                </div>

                <div className="account-panel">
                    <button
                        aria-expanded={profileOpen}
                        className="account-trigger"
                        onClick={() => setProfileOpen((open) => !open)}
                        type="button"
                    >
                        <span className="avatar">{initials || 'MF'}</span>
                        <span className="account-copy">
                            <strong>{user.name}</strong>
                            <small>{user.email}</small>
                        </span>
                        <Icon className="account-chevron" name="chevron-down" size={16} />
                    </button>
                    <div className="account-actions">
                        <button aria-label="Notifications (not available yet)" className="icon-button" disabled type="button"><Icon name="bell" size={18} /></button>
                        <button aria-label="Search tasks (not available yet)" className="icon-button" disabled type="button"><Icon name="search" size={18} /></button>
                    </div>
                    {profileOpen && (
                        <div className="account-menu">
                            <p>{prototype ? 'Static account preview' : 'Account'}</p>
                            {prototype ? (
                                <button onClick={() => setProfileOpen(false)} type="button">Close preview</button>
                            ) : (
                                <Link as="button" href={logout()} method="post">Sign out</Link>
                            )}
                        </div>
                    )}
                </div>

                <nav className="sidebar-nav">
                    <div className="smart-list-group">
                        <Link
                            className={`nav-row ${activeView === 'inbox' ? 'is-active' : ''}`}
                            href={prototype ? prototypeRoute('inbox') : inboxRoute()}
                            onClick={linkClicked}
                        >
                            <Icon className="nav-icon nav-icon--inbox" name="inbox" size={18} />
                            <span>Inbox</span>
                            <Count value={inbox.activeTaskCount} />
                        </Link>
                        <Link
                            className={`nav-row ${activeView === 'starred' ? 'is-active' : ''}`}
                            href={prototype ? prototypeRoute('starred') : starred()}
                            onClick={linkClicked}
                        >
                            <Icon className="nav-icon nav-icon--star" fill name="star" size={18} />
                            <span>Starred</span>
                            <Count value={starredCount} />
                        </Link>
                    </div>

                    <div className="navigation-scroll">
                        <div className="section-label"><span>Folders & lists</span></div>
                        {folders.map((folder, folderIndex) => {
                            const expanded = expandedFolders.includes(folder.id);
                            const menuKey = `folder-${folder.id}`;

                            return (
                                <div className="folder-group" key={folder.id}>
                                    <div className="folder-row">
                                        <button
                                            aria-expanded={expanded}
                                            className="folder-toggle"
                                            onClick={() => toggleFolder(folder.id)}
                                            type="button"
                                        >
                                            <Icon name={expanded ? 'chevron-down' : 'chevron-right'} size={15} />
                                            <Icon className="folder-icon" name="folder" size={17} />
                                            <span>{folder.name}</span>
                                        </button>
                                        <button
                                            aria-expanded={openMenu === menuKey}
                                            aria-label={`More options for ${folder.name}`}
                                            className="row-more"
                                            onClick={() => setOpenMenu((current) => current === menuKey ? null : menuKey)}
                                            type="button"
                                        >
                                            <Icon name="more" size={17} />
                                        </button>
                                        {openMenu === menuKey && (
                                            <div className="navigation-menu">
                                                <button onClick={() => { onEditFolder(folder); setOpenMenu(null); }} type="button">Rename folder…</button>
                                                <button disabled={folderIndex <= 0} onClick={() => { onReorderFolder(folder, 'up'); setOpenMenu(null); }} type="button">Move up</button>
                                                <button disabled={folderIndex === folders.length - 1} onClick={() => { onReorderFolder(folder, 'down'); setOpenMenu(null); }} type="button">Move down</button>
                                                <button className="is-danger" onClick={() => { onDeleteFolder(folder); setOpenMenu(null); }} type="button">Delete folder</button>
                                            </div>
                                        )}
                                    </div>
                                    {expanded && <div className="folder-lists">{folder.lists.map((list) => listRow(list, folder.lists, true))}</div>}
                                </div>
                            );
                        })}

                        {ungroupedLists.map((list) => listRow(list, ungroupedLists))}
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
