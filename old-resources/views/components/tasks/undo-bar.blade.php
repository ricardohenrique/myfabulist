{{--
    The undo affordance for a reversible action (S7/Step 4, C4/C5) —
    complete, move or star, never a delete (M7 keeps its wire:confirm
    instead). A component-owned inline bar, deliberately not a Flux toast:
    Flux::toast() has no slot for an action button. Auto-dismisses client-
    side via an Alpine timer after ~8s, cleared early by either button —
    both are keyboard-reachable, ordinary <flux:button> elements.

    wire:key includes $action['at'] (a render-time uniqueness token set by
    rememberLastAction()) so Alpine remounts and the timer restarts on every
    fresh action rather than a stale timer from a previous one still
    counting down.
--}}
@props(['action'])

@if ($action)
    <div
        wire:key="undo-bar-{{ $action['type'] }}-{{ $action['taskId'] }}-{{ $action['at'] }}"
        x-data="{ timer: null }"
        x-init="timer = setTimeout(() => $wire.dismissUndo(), 8000)"
        x-transition
        role="status"
        data-test="undo-bar"
        class="fixed inset-x-4 bottom-4 z-40 flex items-center justify-between gap-4 rounded-lg border border-zinc-200 bg-white px-4 py-3 shadow-lg sm:inset-x-auto sm:end-4 sm:w-auto dark:border-zinc-700 dark:bg-zinc-800"
    >
        <flux:text>
            {{ match ($action['type']) {
                'complete' => __('Task completed.'),
                'move' => __('Task moved.'),
                'star' => __('Star updated.'),
                default => __('Done.'),
            } }}
        </flux:text>

        <div class="flex items-center gap-1">
            <flux:button size="sm" variant="ghost" wire:click="undo" x-on:click="clearTimeout(timer)">
                {{ __('Undo') }}
            </flux:button>

            <flux:button
                size="sm"
                variant="ghost"
                icon="x-mark"
                wire:click="dismissUndo"
                x-on:click="clearTimeout(timer)"
                :aria-label="__('Dismiss')"
            />
        </div>
    </div>
@endif
