import type { CSSProperties } from 'react';
import type { WorkspaceBackground, WorkspaceBackgroundConfig, WorkspaceBackgroundType } from '@/types';

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
            return flatColorStyle(background.config);
        case 'gradient':
            return gradientStyle(background.config);
        case 'image':
            return imageStyle(background.config);
        default:
            return {};
    }
}

function flatColorStyle(config: WorkspaceBackgroundConfig): WorkspaceBackgroundStyle {
    const { color, workspace_header: workspaceHeader, task_composer: taskComposer } = config;

    if (!color) {
        return {};
    }

    return {
        '--workspace-bg': color,
        '--workspace-header-bg': workspaceHeader ?? `color-mix(in srgb, ${color} 85%, black)`,
        '--workspace-composer-bg': taskComposer ?? `color-mix(in srgb, ${color} 78%, black)`,
    };
}

function gradientStyle(config: WorkspaceBackgroundConfig): WorkspaceBackgroundStyle {
    const { from, to, workspace_header: workspaceHeader, task_composer: taskComposer } = config;

    if (!from || !to) {
        return {};
    }

    return {
        '--workspace-bg': `linear-gradient(115deg, ${from} 0%, ${to} 100%)`,
        '--workspace-header-bg': workspaceHeader ?? `color-mix(in srgb, ${from} 50%, ${to} 50%)`,
        '--workspace-composer-bg': taskComposer ?? `color-mix(in srgb, ${from} 40%, ${to} 60%)`,
    };
}

function imageStyle(config: WorkspaceBackgroundConfig): WorkspaceBackgroundStyle {
    const { url, workspace_header: workspaceHeader, task_composer: taskComposer } = config;

    if (!url) {
        return {};
    }

    return {
        '--workspace-bg': `url("${cssUrl(url)}") center / cover no-repeat`,
        // A background image can land anywhere on the contrast spectrum, so
        // the header/composer fall back to a fixed, neutral dark scrim
        // (rather than a color sampled from the image) when the preset does
        // not specify its own `workspace_header`/`task_composer` — keeps
        // text legible without a full contrast-calculation engine (Plan
        // §Risk Assessment fallback).
        '--workspace-header-bg': workspaceHeader ?? 'rgba(20, 22, 26, 0.55)',
        '--workspace-composer-bg': taskComposer ?? 'rgba(20, 22, 26, 0.45)',
    };
}

function cssUrl(url: string): string {
    return url.replace(/"/g, '%22');
}

// A single CSS `background` value for a small swatch tile — used by the
// picker to show each option's actual look (color/gradient/image) before it
// is selected, rather than a plain labeled button. Falls back to a neutral
// gray for a hypothetical option with no config to preview yet (e.g. a
// future type an operator added but hasn't configured).
const SWATCH_FALLBACK = '#d8dbdc';

export function optionSwatchBackground(type: WorkspaceBackgroundType, config: WorkspaceBackgroundConfig | null): string {
    if (!config) {
        return SWATCH_FALLBACK;
    }

    switch (type) {
        case 'flat_color':
            return config.color ?? SWATCH_FALLBACK;
        case 'gradient':
            return config.from && config.to
                ? `linear-gradient(135deg, ${config.from} 0%, ${config.to} 100%)`
                : SWATCH_FALLBACK;
        case 'image':
            return config.url ? `url("${cssUrl(config.url)}") center / cover no-repeat` : SWATCH_FALLBACK;
        default:
            return SWATCH_FALLBACK;
    }
}
