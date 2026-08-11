<div>
    @if ($this->tasks->isEmpty())
        {{-- Empty state (M10) --}}
        <div class="wunder-empty-state">
            <span>{{ __('No starred tasks yet.') }}</span>
        </div>
    @else
        <div class="wunder-task-list">
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
