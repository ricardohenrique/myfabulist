<div class="flex h-full w-full flex-1 flex-col gap-6">
    <flux:heading size="xl">{{ $this->list->name }}</flux:heading>

    {{-- Quick capture (M3) --}}
    <form wire:submit="addTask" class="flex flex-col gap-1 sm:flex-row sm:items-start sm:gap-2">
        <div class="flex-1">
            <flux:input
                wire:model="newTaskTitle"
                :placeholder="__('Add a task and press Enter…')"
                autofocus
                autocomplete="off"
            />
            @error('newTaskTitle')
                <flux:text size="sm" class="mt-1 text-red-600 dark:text-red-400">{{ $message }}</flux:text>
            @enderror
        </div>
        <flux:button type="submit" variant="primary">{{ __('Add') }}</flux:button>
    </form>

    <div class="flex flex-1 flex-col gap-6">
        @if ($this->tasks->active->isEmpty() && $this->tasks->completed->isEmpty())
            {{-- Empty state (M10) --}}
            <div class="flex flex-1 items-center justify-center rounded-xl border border-dashed border-zinc-200 dark:border-zinc-700">
                <flux:text>{{ __('Nothing here yet. Add your first task.') }}</flux:text>
            </div>
        @else
            {{-- Active tasks (M4) --}}
            <div class="flex flex-col gap-1">
                @forelse ($this->tasks->active as $task)
                    <div wire:key="active-task-{{ $task->id }}" class="group flex items-center gap-3 rounded-lg px-2 py-2 hover:bg-zinc-50 dark:hover:bg-zinc-800">
                        <flux:checkbox
                            wire:click="completeTask({{ $task->id }})"
                            :checked="false"
                            :aria-label="__('Mark :title complete', ['title' => $task->title])"
                        />

                        <input
                            type="text"
                            value="{{ $task->title }}"
                            wire:change="renameTask({{ $task->id }}, $event.target.value)"
                            class="min-w-0 flex-1 truncate border-none bg-transparent p-0 text-sm text-zinc-900 focus:ring-0 dark:text-white"
                        />

                        @if ($task->due_date)
                            <flux:text size="sm" class="shrink-0 text-zinc-400">{{ $task->due_date->format('M j') }}</flux:text>
                        @endif

                        <flux:button
                            wire:click="toggleStar({{ $task->id }})"
                            icon="star"
                            icon:variant="{{ $task->is_starred ? 'solid' : 'outline' }}"
                            variant="ghost"
                            size="sm"
                            :aria-label="__('Toggle star')"
                        />

                        <flux:button
                            wire:click="deleteTask({{ $task->id }})"
                            wire:confirm="{{ __('Delete this task?') }}"
                            icon="trash"
                            variant="ghost"
                            size="sm"
                            :aria-label="__('Delete task')"
                        />
                    </div>
                @empty
                    {{-- All done state (M10) --}}
                    <div class="flex items-center justify-center rounded-xl border border-dashed border-zinc-200 py-8 dark:border-zinc-700">
                        <flux:text>{{ __('All done.') }}</flux:text>
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

                    <div x-show="open" x-collapse class="flex flex-col gap-1">
                        @foreach ($this->tasks->completed as $task)
                            <div wire:key="completed-task-{{ $task->id }}" class="group flex items-center gap-3 rounded-lg px-2 py-2 opacity-50 hover:bg-zinc-50 hover:opacity-100 dark:hover:bg-zinc-800">
                                <flux:checkbox
                                    wire:click="restoreTask({{ $task->id }})"
                                    :checked="true"
                                    :aria-label="__('Restore :title', ['title' => $task->title])"
                                />

                                <span class="min-w-0 flex-1 truncate text-sm text-zinc-500 line-through dark:text-zinc-400">
                                    {{ $task->title }}
                                </span>

                                <flux:button
                                    wire:click="deleteTask({{ $task->id }})"
                                    wire:confirm="{{ __('Delete this task?') }}"
                                    icon="trash"
                                    variant="ghost"
                                    size="sm"
                                    :aria-label="__('Delete task')"
                                />
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        @endif
    </div>
</div>
