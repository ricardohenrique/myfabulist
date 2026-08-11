import { Link } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Icon } from '@/components/ui/icon';
import { Logo } from '@/components/ui/logo';
import { show as prototype } from '@/routes/prototype';
import type { NavigationFolder, NavigationList, UserSummary, WorkspaceView } from '@/types';

type SidebarProps = {
    user: UserSummary;
    inbox: NavigationList;
    starredCount: number;
    folders: NavigationFolder[];
    ungroupedLists: NavigationList[];
    activeView: WorkspaceView;
    mobileOpen: boolean;
    onCloseMobile: () => void;
    onOpenCreate: (kind: 'folder' | 'list') => void;
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
    mobileOpen,
    onCloseMobile,
    onOpenCreate,
}: SidebarProps) {
    const [expandedFolders, setExpandedFolders] = useState<number[]>(folders.map((folder) => folder.id));
    const [profileOpen, setProfileOpen] = useState(false);

    const toggleFolder = (folderId: number) => {
        setExpandedFolders((current) => current.includes(folderId)
            ? current.filter((id) => id !== folderId)
            : [...current, folderId]);
    };

    const linkClicked = () => onCloseMobile();

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
                        <span className="avatar">RM</span>
                        <span className="account-copy">
                            <strong>{user.name}</strong>
                            <small>{user.email}</small>
                        </span>
                        <Icon className="account-chevron" name="chevron-down" size={16} />
                    </button>
                    <div className="account-actions">
                        <button aria-label="Notifications" className="icon-button" type="button"><Icon name="bell" size={18} /></button>
                        <button aria-label="Search tasks" className="icon-button" type="button"><Icon name="search" size={18} /></button>
                    </div>
                    {profileOpen && (
                        <div className="account-menu">
                            <p>Static account preview</p>
                            <button onClick={() => setProfileOpen(false)} type="button">Sign out</button>
                        </div>
                    )}
                </div>

                <nav className="sidebar-nav">
                    <div className="smart-list-group">
                        <Link
                            className={`nav-row ${activeView === 'inbox' ? 'is-active' : ''}`}
                            href={prototype('inbox')}
                            onClick={linkClicked}
                        >
                            <Icon className="nav-icon nav-icon--inbox" name="inbox" size={18} />
                            <span>Inbox</span>
                            <Count value={inbox.activeTaskCount} />
                        </Link>
                        <Link
                            className={`nav-row ${activeView === 'starred' ? 'is-active' : ''}`}
                            href={prototype('starred')}
                            onClick={linkClicked}
                        >
                            <Icon className="nav-icon nav-icon--star" fill name="star" size={18} />
                            <span>Starred</span>
                            <Count value={starredCount} />
                        </Link>
                    </div>

                    <div className="navigation-scroll">
                        <div className="section-label"><span>Folders & lists</span></div>
                        {folders.map((folder) => {
                            const expanded = expandedFolders.includes(folder.id);
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
                                        <button aria-label={`More options for ${folder.name}`} className="row-more" type="button"><Icon name="more" size={17} /></button>
                                    </div>
                                    {expanded && (
                                        <div className="folder-lists">
                                            {folder.lists.map((list) => (
                                                <Link
                                                    className={`nav-row nav-row--nested ${activeView === 'list' && list.id === 11 ? 'is-active' : ''}`}
                                                    href={prototype('list')}
                                                    key={list.id}
                                                    onClick={linkClicked}
                                                >
                                                    <Icon className="nav-icon" name="list" size={16} />
                                                    <span>{list.name}</span>
                                                    <Count value={list.activeTaskCount} />
                                                </Link>
                                            ))}
                                        </div>
                                    )}
                                </div>
                            );
                        })}

                        {ungroupedLists.map((list) => (
                            <Link
                                className={`nav-row ${activeView === 'empty' ? 'is-active' : ''}`}
                                href={prototype('empty')}
                                key={list.id}
                                onClick={linkClicked}
                            >
                                <Icon className="nav-icon" name="list" size={16} />
                                <span>{list.name}</span>
                                <Count value={list.activeTaskCount} />
                            </Link>
                        ))}
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
