import { PointerSensor } from '@dnd-kit/react';

export const wholeItemPointerSensor = PointerSensor.configure({
    preventActivation: () => false,
});

export function moveItem<T>(items: T[], fromIndex: number, toIndex: number): T[] {
    if (
        fromIndex === toIndex
        || fromIndex < 0
        || toIndex < 0
        || fromIndex >= items.length
        || toIndex >= items.length
    ) {
        return items;
    }

    const reordered = [...items];
    const [moved] = reordered.splice(fromIndex, 1);

    if (moved === undefined) {
        return items;
    }

    reordered.splice(toIndex, 0, moved);

    return reordered;
}

export function orderByIds<T extends { id: number }>(items: T[], orderedIds: number[]): T[] {
    const positions = new Map(orderedIds.map((id, index) => [id, index]));

    return [...items].sort((left, right) => {
        const leftPosition = positions.get(left.id) ?? Number.MAX_SAFE_INTEGER;
        const rightPosition = positions.get(right.id) ?? Number.MAX_SAFE_INTEGER;

        return leftPosition - rightPosition;
    });
}
