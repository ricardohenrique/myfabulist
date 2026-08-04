<div class="flex h-full w-full flex-1 flex-col gap-6">
    {{-- Quick capture (M3): add immediately, keep the input focused, clear
         it. addTask() already clears newTaskTitle server-side; the
         x-ref/$nextTick pair below is the explicit guarantee that focus
         itself survives the Livewire round trip rather than relying on
         implicit morph behaviour (frontend.md "Interaction behavior"). --}}
    <form
        wire:submit="addTask"
        x-data
        x-on:submit="$nextTick(() => $refs.quickAddInput.focus())"
        class="flex flex-col gap-1 sm:flex-row sm:items-start sm:gap-2"
    >
        <div class="flex-1">
            <flux:input
                x-ref="quickAddInput"
                wire:model="newTaskTitle"
                :placeholder="__('Add a task and press Enter…')"
                autofocus
                autocomplete="off"
            />
            @error('newTaskTitle')
                <flux:text size="sm" class="mt-1 text-red-600 dark:text-red-400">{{ $message }}</flux:text>
            @enderror
        </div>
        <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:loading.delay wire:target="addTask">
            {{ __('Add') }}
        </flux:button>
    </form>

    <div class="flex flex-1 flex-col gap-6">
        @if ($this->tasks->active->isEmpty() && $this->tasks->completed->isEmpty())
            {{-- Empty state (M10) — the Inbox gets its own copy, distinct
                 from a regular empty list (frontend.md). --}}
            <div class="flex flex-1 items-center justify-center rounded-xl border border-dashed border-zinc-200 dark:border-zinc-700">
                <flux:text>
                    {{ $this->list->is_default ? __('Your Inbox is clear.') : __('Nothing here yet. Add your first task.') }}
                </flux:text>
            </div>
        @else
            {{-- Active tasks (M4). Drag-and-drop reordering (S4/Step 2): the
                 container is .renderless (C3) — SortableJS already shows the
                 drop result client-side, so a normal re-render would only
                 cause a flicker on the happy path; reorderTask() forces a
                 refresh itself on a caught DomainException so a failed drag
                 still snaps back to the persisted order. Completed rows are
                 never inside this container — they are not sortable. --}}
            <div wire:sort.renderless="reorderTask($item, $position)" class="flex flex-col gap-2">
                @forelse ($this->tasks->active as $task)
                    <x-tasks.task-row
                        :task="$task"
                        :lists="$this->lists"
                        show-due-date-quick-actions
                        reorderable
                        :can-move-up="! $loop->first"
                        :can-move-down="! $loop->last"
                        wire:key="active-task-{{ $task->id }}"
                        wire:sort:item="{{ $task->id }}"
                    />
                @empty
                    {{-- All-done state (M10), copy matched to frontend.md --}}
                    <div class="flex items-center justify-center rounded-xl border border-dashed border-zinc-200 py-8 dark:border-zinc-700">
                        <flux:text>{{ __('Everything is done.') }}</flux:text>
                    </div>
                @endforelse
            </div>

            {{-- Completed section (M6) --}}
            @if ($this->tasks->completedCount > 0)
                <div x-data="{ open: $persist(true).as('task-panel-completed-open-{{ $this->taskListId }}') }" class="flex flex-col gap-1">
                    <button
                        type="button"
                        x-on:click="open = !open"
                        class="flex items-center gap-2 self-start text-sm font-medium text-zinc-500 dark:text-zinc-400"
                    >
                        <flux:icon.chevron-down x-bind:class="open ? 'rotate-0' : '-rotate-90'" class="size-4 transition-transform" />
                        {{ __('Completed') }} ({{ $this->tasks->completedCount }})
                    </button>

                    <div x-show="open" x-collapse class="flex flex-col gap-2">
                        @foreach ($this->tasks->completed as $task)
                            <x-tasks.task-row
                                :task="$task"
                                :lists="$this->lists"
                                completed
                                wire:key="completed-task-{{ $task->id }}"
                            />
                        @endforeach
                    </div>
                </div>
            @endif
        @endif
    </div>

    {{-- Undo (S7/Step 4) — complete/move/star only (C4); deletes keep
         wire:confirm and never reach here. --}}
    <x-tasks.undo-bar :action="$this->lastAction" />
</div>
