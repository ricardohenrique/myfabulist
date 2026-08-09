<div>
    {{-- Quick capture (M3): add immediately, keep the input focused, clear
         it. addTask() already clears newTaskTitle server-side; the
         x-ref/$nextTick pair below is the explicit guarantee that focus
         itself survives the Livewire round trip rather than relying on
         implicit morph behaviour (frontend.md "Interaction behavior"). --}}
    <form
        wire:submit="addTask"
        x-data
        x-on:submit="$nextTick(() => $refs.quickAddInput.focus())"
        class="wunder-task-composer"
    >
        <div class="wunder-composer-row">
            <flux:icon.plus class="size-5 shrink-0" />
            <input
                type="text"
                x-ref="quickAddInput"
                wire:model="newTaskTitle"
                class="wunder-composer-input"
                placeholder="{{ __('Add a to-do in \':list\'...', ['list' => $this->list->name]) }}"
                autofocus
                autocomplete="off"
            />
            <button type="submit" class="wunder-composer-submit" wire:loading.delay.attr="disabled" wire:target="addTask">
                <span class="sr-only">{{ __('Add') }}</span>
                <flux:icon.arrow-turn-down-left class="size-5" />
            </button>
        </div>
        @error('newTaskTitle')
            <div class="wunder-composer-error">{{ $message }}</div>
        @enderror
    </form>

    <div>
        @if ($this->tasks->active->isEmpty() && $this->tasks->completed->isEmpty())
            {{-- Empty state (M10) — the Inbox gets its own copy, distinct
                 from a regular empty list (frontend.md). --}}
            <div class="wunder-empty-state">
                <span>
                    {{ $this->list->is_default ? __('Your Inbox is clear.') : __('Nothing here yet. Add your first task.') }}
                </span>
            </div>
        @else
            {{-- Active tasks (M4). Drag-and-drop reordering (S4/Step 2): the
                 container is .renderless (C3) — SortableJS already shows the
                 drop result client-side, so a normal re-render would only
                 cause a flicker on the happy path; reorderTask() forces a
                 refresh itself on a caught DomainException so a failed drag
                 still snaps back to the persisted order. Completed rows are
                 never inside this container — they are not sortable.

                 The handler is a bare method name, not
                 "reorderTask($item, $position)": Livewire's own expression
                 rewriter (contextualizeExpression) prefixes every bare
                 identifier it finds — including the injected $item/$position
                 magics themselves — with "$wire.", which don't exist as
                 component properties and evaluate to null, silently sending
                 reorderTask(null, null) on every real drag. The bare form
                 sidesteps this: Livewire calls it positionally with
                 ($id, $position) itself (matching reorderTask's signature),
                 which is also the form the current wire:sort docs use. --}}
            <div
                wire:sort.renderless="reorderTask"
                wire:sort:config="{ distance: 6 }"
                class="wunder-task-list"
            >
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
                    <div class="wunder-empty-state">
                        <span>{{ __('Everything is done.') }}</span>
                    </div>
                @endforelse
            </div>

            {{-- Completed section (M6) --}}
            @if ($this->tasks->completedCount > 0)
                <div x-data="{ open: $persist(true).as('task-panel-completed-open-{{ $this->taskListId }}') }" class="wunder-completed-section">
                    <button
                        type="button"
                        x-on:click="open = !open"
                        class="wunder-group-label"
                        x-bind:aria-expanded="open.toString()"
                    >
                        <flux:icon.chevron-down x-bind:class="open ? 'rotate-0' : '-rotate-90'" class="size-4 transition-transform" />
                        {{ __('Completed') }} ({{ $this->tasks->completedCount }})
                    </button>

                    <div x-show="open" x-collapse class="wunder-task-list">
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
