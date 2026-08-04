<div class="flex h-full w-full flex-1 flex-col gap-6">
    <flux:heading size="xl">{{ __('Starred') }}</flux:heading>

    @if ($this->tasks->isEmpty())
        {{-- Empty state (M10) --}}
        <div class="flex flex-1 items-center justify-center rounded-xl border border-dashed border-zinc-200 dark:border-zinc-700">
            <flux:text>{{ __('No starred tasks yet.') }}</flux:text>
        </div>
    @else
        <div class="flex flex-col gap-2">
            @foreach ($this->tasks as $task)
                <x-tasks.task-row
                    :task="$task"
                    :completed="$task->is_completed"
                    :lists="$this->lists"
                    show-list-name
                    wire:key="starred-task-{{ $task->id }}"
                />
            @endforeach
        </div>
    @endif

    {{-- Undo (S7/Step 4) — complete/move/star only (C4); deletes keep
         wire:confirm and never reach here. --}}
    <x-tasks.undo-bar :action="$this->lastAction" />
</div>
