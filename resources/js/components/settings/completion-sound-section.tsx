import { useForm } from '@inertiajs/react';
import { useEffect, useRef, useState, type FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Icon } from '@/components/ui/icon';
import { update as updateCompletionSound } from '@/routes/profile/completion-sound';
import type { CompletionSound, CompletionSoundOptionSummary } from '@/types';

type CompletionSoundSectionProps = {
    currentSound: CompletionSound | null;
    options: CompletionSoundOptionSummary[];
    onSaved: () => void;
};

type CompletionSoundFormData = {
    completion_sound_key: string | null;
};

export function CompletionSoundSection({ currentSound, options, onSaved }: CompletionSoundSectionProps) {
    const [selectedKey, setSelectedKey] = useState<string | null>(currentSound?.key ?? null);
    const [previewingKey, setPreviewingKey] = useState<string | null>(null);
    const [previewError, setPreviewError] = useState('');
    const previewAudioRef = useRef<HTMLAudioElement | null>(null);
    const form = useForm<CompletionSoundFormData>({ completion_sound_key: currentSound?.key ?? null });

    useEffect(() => () => {
        previewAudioRef.current?.pause();
        previewAudioRef.current = null;
    }, []);

    const stopPreview = () => {
        previewAudioRef.current?.pause();
        previewAudioRef.current = null;
        setPreviewingKey(null);
    };

    const preview = (option: CompletionSoundOptionSummary) => {
        if (previewingKey === option.key) {
            stopPreview();
            return;
        }

        stopPreview();
        setPreviewError('');

        const audio = new Audio(option.url);
        audio.preload = 'auto';
        audio.onended = () => {
            previewAudioRef.current = null;
            setPreviewingKey(null);
        };
        audio.onerror = () => {
            previewAudioRef.current = null;
            setPreviewingKey(null);
            setPreviewError(`“${option.label}” could not be played.`);
        };

        previewAudioRef.current = audio;
        setPreviewingKey(option.key);
        void audio.play().catch(() => {
            previewAudioRef.current = null;
            setPreviewingKey(null);
            setPreviewError(`“${option.label}” could not be played.`);
        });
    };

    const select = (key: string | null) => {
        setSelectedKey(key);
        form.setData('completion_sound_key', key);
        form.clearErrors();
    };

    const save = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        stopPreview();
        form.patch(updateCompletionSound.url(), {
            preserveScroll: true,
            onSuccess: onSaved,
        });
    };

    return (
        <section aria-labelledby="completion-sound-heading" className="profile-modal__section">
            <div className="profile-modal__section-heading">
                <h3 id="completion-sound-heading">Completion sound</h3>
                <p>Choose the sound that plays after a task is completed, or keep things silent.</p>
            </div>

            <form className="completion-sound-form" noValidate onSubmit={save}>
                <div className="completion-sound-options">
                    <div className={`completion-sound-option ${selectedKey === null ? 'is-selected' : ''}`}>
                        <button aria-pressed={selectedKey === null} className="completion-sound-option__select" onClick={() => select(null)} type="button">
                            <span className="completion-sound-option__visual">
                                <Icon name="volume-off" size={25} />
                                {currentSound === null && <span className="completion-sound-option__badge">Current</span>}
                            </span>
                            <span className="completion-sound-option__label">No sound</span>
                        </button>
                        <span className="completion-sound-option__preview-placeholder">Silent</span>
                    </div>

                    {options.map((option) => (
                        <div className={`completion-sound-option ${selectedKey === option.key ? 'is-selected' : ''}`} key={option.key}>
                            <button aria-pressed={selectedKey === option.key} className="completion-sound-option__select" onClick={() => select(option.key)} type="button">
                                <span className="completion-sound-option__visual">
                                    <Icon name="volume" size={25} />
                                    {currentSound?.key === option.key && <span className="completion-sound-option__badge">Current</span>}
                                </span>
                                <span className="completion-sound-option__label">
                                    {option.label}{option.isDefault ? ' · Default' : ''}
                                </span>
                            </button>
                            <button
                                aria-label={`${previewingKey === option.key ? 'Stop' : 'Preview'} ${option.label}`}
                                className="completion-sound-option__preview"
                                onClick={() => preview(option)}
                                type="button"
                            >
                                <Icon name={previewingKey === option.key ? 'pause' : 'play'} size={14} />
                                {previewingKey === option.key ? 'Stop' : 'Preview'}
                            </button>
                        </div>
                    ))}
                </div>

                {(form.errors.completion_sound_key || previewError) && (
                    <p className="field-error" role="alert">{form.errors.completion_sound_key ?? previewError}</p>
                )}

                <div className="profile-modal__actions">
                    <Button disabled={form.processing} type="submit" variant="primary">
                        {form.processing ? 'Saving…' : 'Save sound'}
                    </Button>
                </div>
            </form>
        </section>
    );
}
