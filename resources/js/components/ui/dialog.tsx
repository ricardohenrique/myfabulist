import { useEffect, useId, useRef, type ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { Icon } from '@/components/ui/icon';

type DialogProps = {
    open: boolean;
    title: string;
    description?: string;
    children: ReactNode;
    onClose: () => void;
};

export function Dialog({ open, title, description, children, onClose }: DialogProps) {
    const titleId = useId();
    const descriptionId = useId();
    const panelRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        if (!open) {
            return;
        }

        const previouslyFocused = document.activeElement as HTMLElement | null;
        panelRef.current?.focus();

        const handleKeyDown = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                onClose();
            }
        };

        document.addEventListener('keydown', handleKeyDown);
        return () => {
            document.removeEventListener('keydown', handleKeyDown);
            previouslyFocused?.focus();
        };
    }, [onClose, open]);

    if (!open) {
        return null;
    }

    return (
        <div className="dialog-backdrop" onMouseDown={(event) => event.target === event.currentTarget && onClose()}>
            <div
                aria-describedby={description ? descriptionId : undefined}
                aria-labelledby={titleId}
                aria-modal="true"
                className="dialog-panel"
                ref={panelRef}
                role="dialog"
                tabIndex={-1}
            >
                <div className="dialog-heading">
                    <div>
                        <h2 id={titleId}>{title}</h2>
                        {description && <p id={descriptionId}>{description}</p>}
                    </div>
                    <Button aria-label="Close dialog" onClick={onClose} variant="ghost" size="sm">
                        <Icon name="close" />
                    </Button>
                </div>
                {children}
            </div>
        </div>
    );
}
