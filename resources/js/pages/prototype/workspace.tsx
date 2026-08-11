import { AppShell } from '@/layouts/app-shell';
import { workspaceFixture } from '@/fixtures/workspace';
import type { WorkspaceView } from '@/types';

type WorkspaceProps = {
    initialView: WorkspaceView;
};

export default function Workspace({ initialView }: WorkspaceProps) {
    return <AppShell fixture={workspaceFixture(initialView)} view={initialView} />;
}
