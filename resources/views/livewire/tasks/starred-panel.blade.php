<div class="flex h-full w-full flex-1 flex-col gap-6">
    <flux:heading size="xl">{{ __('Starred') }}</flux:heading>

    @if ($this->tasks->isEmpty())
        {{-- Empty state (M10) --}}
        <div class="flex flex-1 items-center justify-center rounded-xl border border-dashed border-zinc-200 dark:border-zinc-700">
            <flux:text>{{ __('No starred tasks yet.') }}</flux:text>
        </div>
    @else
        <div class="flex flex-col gap-1">
            @foreach ($this->tasks as $task)
                <div
                    wire:key="starred-task-{{ $task->id }}"
                    class="group flex items-center gap-3 rounded-lg px-2 py-2 hover:bg-zinc-50 dark:hover:bg-zinc-800 {{ $task->is_completed ? 'opacity-50' : '' }}"
                >
                    @if ($task->is_completed)
                        <flux:checkbox
                            wire:click="restoreTask({{ $task->id }})"
                            :checked="true"
                            :aria-label="__('Restore :title', ['title' => $task->title])"
                        />
                    @else
                        <flux:checkbox
                            wire:click="completeTask({{ $task->id }})"
                            :checked="false"
                            :aria-label="__('Mark :title complete', ['title' => $task->title])"
                        />
                    @endif

                    <div class="min-w-0 flex-1">
                        <div class="truncate text-sm {{ $task->is_completed ? 'text-zinc-500 line-through dark:text-zinc-400' : 'text-zinc-900 dark:text-white' }}">
                            {{ $task->title }}
                        </div>

                        @if ($task->taskList)
                            <flux:text size="sm" class="text-zinc-400">
                                @if ($task->taskList->folder)
                                    {{ $task->taskList->folder->name }} / {{ $task->taskList->name }}
                                @else
                                    {{ $task->taskList->name }}
                                @endif
                            </flux:text>
                        @endif
                    </div>

                    <flux:button
                        wire:click="unstarTask({{ $task->id }})"
                        icon="star"
                        icon:variant="solid"
                        variant="ghost"
                        size="sm"
                        :aria-label="__('Unstar task')"
                    />
                </div>
            @endforeach
        </div>
    @endif
</div>
