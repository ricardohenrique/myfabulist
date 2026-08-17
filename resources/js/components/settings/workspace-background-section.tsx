import { router, useForm } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { optionSwatchBackground } from '@/lib/workspace-background';
import { update as updateBackground } from '@/routes/profile/background';
import type { WorkspaceBackground, WorkspaceBackgroundOptionSummary } from '@/types';

type WorkspaceBackgroundSectionProps = {
    currentBackground: WorkspaceBackground | null;
    options: WorkspaceBackgroundOptionSummary[];
};

type WorkspaceBackgroundFormData = {
    option_key: string | null;
};

export function WorkspaceBackgroundSection({ currentBackground, options }: WorkspaceBackgroundSectionProps) {
    const [selectedKey, setSelectedKey] = useState<string | null>(currentBackground?.optionKey ?? null);
    const form = useForm<WorkspaceBackgroundFormData>({ option_key: currentBackground?.optionKey ?? null });

    // The already-applied option previews the user's own resolved look
    // (which may carry a personal override from before this UI dropped
    // customization) rather than the catalog's generic default — every
    // other option previews its own default_config.
    const previewConfigFor = (option: WorkspaceBackgroundOptionSummary) =>
        currentBackground?.optionKey === option.key ? currentBackground.config : option.defaultConfig;

    // The platform default (Twilight, matches the brand identity) — read
    // from the catalog rather than hard-coded, so an admin can move which
    // preset "Use default" reverts to without a frontend change.
    const defaultOption = options.find((option) => option.isDefault) ?? null;

    const selectType = (option: WorkspaceBackgroundOptionSummary) => {
        setSelectedKey(option.key);
        form.setData('option_key', option.key);
        form.clearErrors();
    };

    const save = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        // Every selection here is just `option_key` — no per-type config is
        // ever submitted from this UI, so the backend always adopts (and
        // stays live-linked to) the preset's own `default_config`, including
        // its curated workspace_header/task_composer colors
        // (WorkspaceBackgroundService::updateSelection()).
        form.patch(updateBackground.url(), { preserveScroll: true });
    };

    // "Use default" now means "the platform default" (Twilight) rather than
    // clearing to no preference — it submits through the exact same
    // endpoint a tile click would, with an empty config, so the backend
    // adopts (and stays live-linked to) the default option's own
    // `default_config`.
    const reset = () => {
        if (!defaultOption) {
            return;
        }

        router.patch(updateBackground.url(), { option_key: defaultOption.key }, {
            preserveScroll: true,
            onSuccess: () => setSelectedKey(defaultOption.key),
        });
    };

    return (
        <section aria-labelledby="workspace-background-heading" className="profile-modal__section">
            <div className="profile-modal__section-heading">
                <h3 id="workspace-background-heading">Workspace background</h3>
                <p>Personalize the color, image, or gradient behind your tasks.</p>
            </div>

            <form className="workspace-background-form" noValidate onSubmit={save}>
                <div className="workspace-background-options">
                    {options.map((option) => (
                        <button
                            aria-pressed={selectedKey === option.key}
                            className={`workspace-background-option ${selectedKey === option.key ? 'is-selected' : ''}`}
                            key={option.key}
                            onClick={() => selectType(option)}
                            type="button"
                        >
                            <span
                                className="workspace-background-option__preview"
                                style={{ background: optionSwatchBackground(option.type, previewConfigFor(option)) }}
                            >
                                {currentBackground?.optionKey === option.key && (
                                    <span className="workspace-background-option__badge">Current</span>
                                )}
                            </span>
                            <span className="workspace-background-option__label">{option.label}</span>
                        </button>
                    ))}
                </div>

                <div className="profile-modal__actions">
                    {currentBackground && (
                        <Button disabled={form.processing || !defaultOption} onClick={reset} type="button" variant="ghost">
                            Use default
                        </Button>
                    )}
                    <Button disabled={form.processing || !selectedKey} type="submit" variant="primary">
                        {form.processing ? 'Saving…' : 'Save background'}
                    </Button>
                </div>
            </form>
        </section>
    );
}
