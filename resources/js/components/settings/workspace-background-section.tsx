import { router, useForm } from '@inertiajs/react';
import { useState, type ChangeEvent, type FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { update as updateBackground } from '@/routes/profile/background';
import type { WorkspaceBackground, WorkspaceBackgroundOptionSummary } from '@/types';

type WorkspaceBackgroundSectionProps = {
    currentBackground: WorkspaceBackground | null;
    options: WorkspaceBackgroundOptionSummary[];
};

type WorkspaceBackgroundFormData = {
    option_key: string | null;
    color: string;
    gradient_from: string;
    gradient_to: string;
    image: File | null;
};

const DEFAULT_COLOR = '#47976f';
const DEFAULT_GRADIENT_FROM = '#78b691';
const DEFAULT_GRADIENT_TO = '#e8dca1';

function initialFormData(currentBackground: WorkspaceBackground | null): WorkspaceBackgroundFormData {
    return {
        option_key: currentBackground?.optionKey ?? null,
        color: currentBackground?.type === 'flat_color' ? (currentBackground.config.color ?? DEFAULT_COLOR) : DEFAULT_COLOR,
        gradient_from: currentBackground?.type === 'gradient' ? (currentBackground.config.from ?? DEFAULT_GRADIENT_FROM) : DEFAULT_GRADIENT_FROM,
        gradient_to: currentBackground?.type === 'gradient' ? (currentBackground.config.to ?? DEFAULT_GRADIENT_TO) : DEFAULT_GRADIENT_TO,
        image: null,
    };
}

export function WorkspaceBackgroundSection({ currentBackground, options }: WorkspaceBackgroundSectionProps) {
    const [selectedKey, setSelectedKey] = useState<string | null>(currentBackground?.optionKey ?? null);
    const [successMessage, setSuccessMessage] = useState('');
    const [imagePreview, setImagePreview] = useState<string | null>(
        currentBackground?.type === 'image' ? (currentBackground.config.url ?? null) : null,
    );
    const form = useForm<WorkspaceBackgroundFormData>(initialFormData(currentBackground));

    const selectedOption = options.find((option) => option.key === selectedKey) ?? null;

    const selectType = (option: WorkspaceBackgroundOptionSummary) => {
        setSelectedKey(option.key);
        form.setData('option_key', option.key);
        form.clearErrors();
        setSuccessMessage('');

        // Prefill from the preset's own curated value when switching to a
        // fresh option — not when re-opening the user's own already-
        // customized selection, whose form state (from `initialFormData`)
        // must win instead.
        const isCurrentSelection = currentBackground?.optionKey === option.key;
        if (isCurrentSelection || !option.defaultConfig) {
            return;
        }

        if (option.type === 'flat_color' && option.defaultConfig.color) {
            form.setData('color', option.defaultConfig.color);
        } else if (option.type === 'gradient') {
            if (option.defaultConfig.from) {
                form.setData('gradient_from', option.defaultConfig.from);
            }
            if (option.defaultConfig.to) {
                form.setData('gradient_to', option.defaultConfig.to);
            }
        } else if (option.type === 'image' && option.defaultConfig.url) {
            form.setData('image', null);
            setImagePreview(option.defaultConfig.url);
        }
    };

    const onImageChange = (event: ChangeEvent<HTMLInputElement>) => {
        const file = event.target.files?.[0] ?? null;
        form.setData('image', file);
        if (file) {
            setImagePreview(URL.createObjectURL(file));
        }
    };

    const save = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        setSuccessMessage('');

        // Only the fields relevant to the selected option's type are ever
        // submitted. Critically, an `image` selection with no newly chosen
        // file sends nothing but `option_key` — never the leftover
        // color/gradient defaults sitting in form state — so the backend
        // sees a genuinely empty config and adopts the option's own
        // `default_config` (WorkspaceBackgroundService::updateSelection()).
        form.transform((data) => {
            if (selectedOption?.type === 'flat_color') {
                return { option_key: data.option_key, color: data.color };
            }
            if (selectedOption?.type === 'gradient') {
                return { option_key: data.option_key, gradient_from: data.gradient_from, gradient_to: data.gradient_to };
            }
            if (selectedOption?.type === 'image') {
                return data.image ? { option_key: data.option_key, image: data.image } : { option_key: data.option_key };
            }

            return { option_key: data.option_key };
        });

        form.patch(updateBackground.url(), {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => setSuccessMessage('Workspace background updated.'),
        });
    };

    const reset = () => {
        setSuccessMessage('');
        router.patch(updateBackground.url(), { option_key: null }, {
            preserveScroll: true,
            onSuccess: () => {
                setSelectedKey(null);
                setSuccessMessage('Workspace background reset to default.');
            },
        });
    };

    return (
        <section aria-labelledby="workspace-background-heading" className="profile-modal__section profile-modal__section--full">
            <div className="profile-modal__section-heading">
                <h3 id="workspace-background-heading">Workspace background</h3>
                <p>Personalize the color, image, or gradient behind your tasks.</p>
            </div>

            {successMessage && <p className="profile-modal__success" role="status">{successMessage}</p>}

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
                            {option.label}
                            {currentBackground?.optionKey === option.key && (
                                <span className="workspace-background-option__badge">Current</span>
                            )}
                        </button>
                    ))}
                </div>

                {selectedOption?.type === 'flat_color' && (
                    <div className="workspace-background-field">
                        <label className="field-label" htmlFor="background-color">Color</label>
                        <input
                            id="background-color"
                            onChange={(event) => form.setData('color', event.target.value)}
                            type="color"
                            value={form.data.color}
                        />
                        {form.errors.color && <p className="field-error">{form.errors.color}</p>}
                    </div>
                )}

                {selectedOption?.type === 'gradient' && (
                    <div className="workspace-background-field workspace-background-field--gradient">
                        <div>
                            <label className="field-label" htmlFor="background-gradient-from">From</label>
                            <input
                                id="background-gradient-from"
                                onChange={(event) => form.setData('gradient_from', event.target.value)}
                                type="color"
                                value={form.data.gradient_from}
                            />
                        </div>
                        <div>
                            <label className="field-label" htmlFor="background-gradient-to">To</label>
                            <input
                                id="background-gradient-to"
                                onChange={(event) => form.setData('gradient_to', event.target.value)}
                                type="color"
                                value={form.data.gradient_to}
                            />
                        </div>
                        {(form.errors.gradient_from || form.errors.gradient_to) && (
                            <p className="field-error">{form.errors.gradient_from ?? form.errors.gradient_to}</p>
                        )}
                    </div>
                )}

                {selectedOption?.type === 'image' && (
                    <div className="workspace-background-field">
                        <label className="field-label" htmlFor="background-image">Image</label>
                        <input accept="image/*" id="background-image" onChange={onImageChange} type="file" />
                        {imagePreview && <img alt="Background preview" className="workspace-background-preview" src={imagePreview} />}
                        {form.errors.image && <p className="field-error">{form.errors.image}</p>}
                    </div>
                )}

                <div className="profile-modal__actions">
                    {currentBackground && (
                        <Button disabled={form.processing} onClick={reset} type="button" variant="ghost">
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
