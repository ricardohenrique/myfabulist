import type { CSSProperties } from 'react';
import type { WorkspaceBackground } from '@/types';

// The exact three CSS custom properties `.workspace-header`/`.task-composer`
// (and the task canvas itself) read, with today's hard-coded colors as their
// fallback — see resources/css/app.css. Returning an empty object for "no
// preference" means those `var(--x, <fallback>)` declarations resolve to the
// fallback, so a user with no preference sees zero visual change.
//
// Typed as CSSProperties (rather than a plain custom-property map) so the
// result can be spread straight into a React `style` prop — TypeScript's
// bundled csstype definitions don't structurally recognize an object of only
// `--custom-property` keys as assignable to `style` without this.
export type WorkspaceBackgroundStyle = CSSProperties & {
    '--workspace-bg'?: string;
    '--workspace-header-bg'?: string;
    '--workspace-composer-bg'?: string;
};

export function workspaceBackgroundStyle(background: WorkspaceBackground | null): WorkspaceBackgroundStyle {
    if (!background) {
        return {};
    }

    switch (background.type) {
        case 'flat_color':
            return flatColorStyle(background.config.color);
        case 'gradient':
            return gradientStyle(background.config.from, background.config.to);
        case 'image':
            return imageStyle(background.config.url);
        default:
            return {};
    }
}

function flatColorStyle(color?: string): WorkspaceBackgroundStyle {
    if (!color) {
        return {};
    }

    return {
        '--workspace-bg': color,
        '--workspace-header-bg': `color-mix(in srgb, ${color} 85%, black)`,
        '--workspace-composer-bg': `color-mix(in srgb, ${color} 78%, black)`,
    };
}

function gradientStyle(from?: string, to?: string): WorkspaceBackgroundStyle {
    if (!from || !to) {
        return {};
    }

    return {
        '--workspace-bg': `linear-gradient(115deg, ${from} 0%, ${to} 100%)`,
        '--workspace-header-bg': `color-mix(in srgb, ${from} 50%, ${to} 50%)`,
        '--workspace-composer-bg': `color-mix(in srgb, ${from} 40%, ${to} 60%)`,
    };
}

function imageStyle(url?: string): WorkspaceBackgroundStyle {
    if (!url) {
        return {};
    }

    return {
        '--workspace-bg': `url("${cssUrl(url)}") center / cover no-repeat`,
        // A background image can land anywhere on the contrast spectrum, so
        // the header/composer get a fixed, neutral dark scrim rather than a
        // color sampled from the image — keeps text legible without a full
        // contrast-calculation engine (Plan §Risk Assessment fallback).
        '--workspace-header-bg': 'rgba(20, 22, 26, 0.55)',
        '--workspace-composer-bg': 'rgba(20, 22, 26, 0.45)',
    };
}

function cssUrl(url: string): string {
    return url.replace(/"/g, '%22');
}
