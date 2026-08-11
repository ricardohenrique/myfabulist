import { AppShell } from '@/layouts/app-shell';
import { usePage } from '@inertiajs/react';
import type { SharedPageProps, WorkspaceData } from '@/types';

type WorkspacePageProps = SharedPageProps & {
    workspace: WorkspaceData;
};

export default function WorkspacePage({ workspace }: WorkspacePageProps) {
    const { auth } = usePage<WorkspacePageProps>().props;

    if (!auth.user) {
        return null;
    }

    return <AppShell user={auth.user} workspace={workspace} />;
}
