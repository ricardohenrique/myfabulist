{{--
    Shared task row anatomy (D1, M4): checkbox, title, note indicator, due-
    date badge, star (visible on completed rows too, M6) and a row menu.
    Used identically by TaskPanel (active + completed sections) and
    StarredPanel — wire directives below target whichever Livewire
    component renders this include, so both panels only need to expose the
    same method names (completeTask/restoreTask/renameTask/toggleStar/
    deleteTask).
--}}
@props([
    'task',
    'completed' => false,
    'showListName' => false,
    'showDueDateQuickActions' => false,
    'lists' => null,
    'reorderable' => false,
    'canMoveUp' => true,
    'canMoveDown' => true,
])

@php
    $dueStatus = $task->due_date ? $task->dueDateStatus() : null;
@endphp

<div
    {{ $attributes->class([
        'wunder-task group',
        'is-completed' => $completed,
    ]) }}
    role="button"
    tabindex="0"
    aria-label="{{ __('Open details for :title', ['title' => $task->title]) }}"
    wire:click="openDetails({{ $task->id }})"
    wire:keydown.enter="openDetails({{ $task->id }})"
    wire:keydown.space="openDetails({{ $task->id }})"
    @if ($dueStatus)
        data-due-status="{{ $dueStatus }}"
    @endif
    {{-- Completion animation (frontend.md "Interaction behavior") — a row
         fades as it leaves the active section into Completed (and back, on
         restore). Alpine transitions run on any add/remove Livewire's morph
         performs, not only on x-show, so no extra wiring is needed. --}}
    x-transition:leave.opacity.duration.200ms
    x-transition:enter.opacity.duration.200ms
>
    @if ($completed)
        <button
            type="button"
            class="wunder-task-checkbox is-checked"
            role="checkbox"
            aria-checked="true"
            @if ($reorderable) wire:sort:handle @endif
            wire:click="restoreTask({{ $task->id }})"
            wire:loading.delay.attr="disabled"
            wire:target="restoreTask({{ $task->id }})"
            x-on:click.stop
            x-on:keydown.stop
            aria-label="{{ __('Restore :title', ['title' => $task->title]) }}"
        >
            <flux:icon.check class="size-4" />
        </button>
    @else
        <button
            type="button"
            class="wunder-task-checkbox"
            role="checkbox"
            aria-checked="false"
            @if ($reorderable) wire:sort:handle @endif
            wire:click="completeTask({{ $task->id }})"
            wire:loading.delay.attr="disabled"
            wire:target="completeTask({{ $task->id }})"
            x-on:click.stop
            x-on:keydown.stop
            aria-label="{{ __('Mark :title complete', ['title' => $task->title]) }}"
        >
            <span class="sr-only">{{ __('Complete') }}</span>
        </button>
    @endif

    <div class="wunder-task-body">
        @if ($completed)
            <span class="wunder-task-title">
                {{ $task->title }}
            </span>
        @else
            <input
                type="text"
                value="{{ $task->title }}"
                wire:change="renameTask({{ $task->id }}, $event.target.value)"
                class="wunder-task-title-input"
            />
        @endif
    </div>

    <div class="wunder-task-meta">
        @if ($showListName && $task->taskList)
            <span class="wunder-task-list-name">
                    @if ($task->taskList->folder)
                        {{ $task->taskList->folder->name }} / {{ $task->taskList->name }}
                    @else
                        {{ $task->taskList->name }}
                    @endif
                </span>
        @endif

        @if ($task->note)
            <span title="{{ __('Has a note') }}" data-test="note-indicator-{{ $task->id }}" class="wunder-task-note">
                <flux:icon.document-text variant="outline" class="size-4" />
                <span class="sr-only">{{ __('Has a note') }}</span>
            </span>
        @endif

        @if ($task->due_date)
            <span class="wunder-task-due" title="{{ $task->due_date->toFormattedDateString() }}">
                <flux:icon.calendar-days class="size-4" />
                <span>{{ $task->due_date->format('M j') }}</span>
            </span>
        @endif
    </div>

    <span x-on:click.stop x-on:keydown.stop>
        <flux:button
            wire:click="toggleStar({{ $task->id }})"
            wire:loading.delay.attr="disabled"
            wire:target="toggleStar({{ $task->id }})"
            icon="star"
            icon:variant="{{ $task->is_starred ? 'solid' : 'outline' }}"
            variant="ghost"
            size="sm"
            @class(['wunder-task-star', 'is-starred' => $task->is_starred])
            :aria-label="__('Toggle star')"
        />
    </span>

    <flux:dropdown x-on:click.stop x-on:keydown.stop>
        <flux:button
            icon="ellipsis-horizontal"
            variant="ghost"
            size="sm"
            class="wunder-task-menu"
            :aria-label="__('Task options')"
        />

        <flux:menu>
            <flux:menu.item
                icon="pencil-square"
                wire:click="openDetails({{ $task->id }})"
            >
                {{ __('Edit details') }}
            </flux:menu.item>

            @if ($reorderable)
                <flux:menu.item
                    icon="chevron-up"
                    wire:click="moveTaskUp({{ $task->id }})"
                    :disabled="! $canMoveUp"
                    :aria-label="__('Move :title up', ['title' => $task->title])"
                >
                    {{ __('Move up') }}
                </flux:menu.item>

                <flux:menu.item
                    icon="chevron-down"
                    wire:click="moveTaskDown({{ $task->id }})"
                    :disabled="! $canMoveDown"
                    :aria-label="__('Move :title down', ['title' => $task->title])"
                >
                    {{ __('Move down') }}
                </flux:menu.item>
            @endif

            @if ($showDueDateQuickActions)
                <flux:menu.submenu heading="{{ __('Due date') }}" icon="calendar">
                    <flux:menu.item wire:click="setDueDate({{ $task->id }}, '{{ today()->toDateString() }}')">
                        {{ __('Today') }}
                    </flux:menu.item>

                    <flux:menu.item wire:click="setDueDate({{ $task->id }}, '{{ today()->addDay()->toDateString() }}')">
                        {{ __('Tomorrow') }}
                    </flux:menu.item>

                    <flux:menu.item wire:click="setDueDate({{ $task->id }}, '{{ today()->addWeek()->toDateString() }}')">
                        {{ __('Next week') }}
                    </flux:menu.item>

                    @if ($task->due_date)
                        <flux:menu.item wire:click="setDueDate({{ $task->id }}, null)">
                            {{ __('Clear due date') }}
                        </flux:menu.item>
                    @endif
                </flux:menu.submenu>
            @endif

            @if ($lists)
                <flux:menu.submenu heading="{{ __('Move to…') }}" icon="arrow-right-circle">
                    @foreach ($lists as $list)
                        @continue($list->id === $task->task_list_id)
                        <flux:menu.item wire:click="moveTask({{ $task->id }}, {{ $list->id }})">
                            {{ $list->name }}
                        </flux:menu.item>
                    @endforeach
                </flux:menu.submenu>
            @endif

            <flux:menu.item
                icon="trash"
                variant="danger"
                wire:click="deleteTask({{ $task->id }})"
                wire:confirm="{{ __('Delete this task?') }}"
                wire:loading.delay.attr="disabled"
                wire:target="deleteTask({{ $task->id }})"
            >
                {{ __('Delete') }}
            </flux:menu.item>
        </flux:menu>
    </flux:dropdown>
</div>
