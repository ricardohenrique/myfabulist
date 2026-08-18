import { router } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Dialog } from '@/components/ui/dialog';
import { complete as completeOnboarding } from '@/routes/onboarding';
import type { OnboardingUseCase, OnboardingUseCaseOption } from '@/types';

type UseCaseDialogProps = {
    open: boolean;
    options: OnboardingUseCaseOption[];
};

export function UseCaseDialog({ open, options }: UseCaseDialogProps) {
    const [completed, setCompleted] = useState(false);
    const [processing, setProcessing] = useState(false);
    const [error, setError] = useState('');

    const complete = (useCase: OnboardingUseCase | null) => {
        if (processing) return;

        setError('');
        router.patch(completeOnboarding.url(), { use_case: useCase }, {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onSuccess: () => setCompleted(true),
            onError: (errors) => setError(errors.use_case ?? 'Your choice could not be saved. Please try again.'),
            onFinish: () => setProcessing(false),
        });
    };

    return (
        <Dialog
            description="Choose one if it helps us understand your needs. You can also skip this."
            onClose={() => complete(null)}
            open={open && !completed}
            panelClassName="dialog-panel--onboarding"
            title="What are you mainly planning to use Purplelist for?"
        >
            <div className="onboarding-options">
                {options.map((option) => (
                    <button
                        className="onboarding-option"
                        disabled={processing}
                        key={option.value}
                        onClick={() => complete(option.value)}
                        type="button"
                    >
                        {option.label}
                    </button>
                ))}
            </div>

            {error && <p className="field-error onboarding-error" role="alert">{error}</p>}

            <div className="dialog-actions onboarding-actions">
                <Button disabled={processing} onClick={() => complete(null)} variant="ghost">
                    {processing ? 'Saving…' : 'Skip'}
                </Button>
            </div>
        </Dialog>
    );
}
