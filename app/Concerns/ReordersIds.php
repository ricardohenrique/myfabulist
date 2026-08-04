<?php

declare(strict_types=1);

namespace App\Concerns;

/**
 * The "compute the new order" primitive shared by every reorderable
 * Livewire component (S4). `wire:sort` hands a component the moved item's
 * id and its new index (C2); the accessible move up/down actions compute
 * the same $position by searching the current order for the neighbour to
 * swap with. Both entry points funnel into reorderedIds() so there is
 * exactly one implementation of "what does a reorder mean" per id-array —
 * used identically by Tasks\TaskPanel (Steps 1/2) and Navigation\Sidebar
 * (Step 3) rather than duplicated per component (C1/C2).
 */
trait ReordersIds
{
    /**
     * Remove $movedId from $current and splice it back in at $position.
     *
     * @param  array<int, int>  $current
     * @return array<int, int>
     */
    private function reorderedIds(array $current, int $movedId, int $position): array
    {
        $without = array_values(array_filter(
            $current,
            fn (int $id): bool => $id !== $movedId,
        ));

        $position = max(0, min($position, count($without)));

        array_splice($without, $position, 0, [$movedId]);

        return $without;
    }
}
