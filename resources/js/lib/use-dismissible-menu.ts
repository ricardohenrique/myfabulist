import { useEffect, type RefObject } from 'react';

export function useDismissibleMenu(
    open: boolean,
    triggerRef: RefObject<HTMLElement | null>,
    menuRef: RefObject<HTMLElement | null>,
    onDismiss: () => void,
) {
    useEffect(() => {
        if (!open) return;

        const handlePointerDown = (event: PointerEvent) => {
            const target = event.target;

            if (!(target instanceof Node)) return;
            if (triggerRef.current?.contains(target) || menuRef.current?.contains(target)) return;

            onDismiss();
        };

        const handleKeyDown = (event: KeyboardEvent) => {
            if (event.key !== 'Escape') return;

            onDismiss();
            triggerRef.current?.focus();
        };

        document.addEventListener('pointerdown', handlePointerDown);
        document.addEventListener('keydown', handleKeyDown);

        return () => {
            document.removeEventListener('pointerdown', handlePointerDown);
            document.removeEventListener('keydown', handleKeyDown);
        };
    }, [menuRef, onDismiss, open, triggerRef]);
}
