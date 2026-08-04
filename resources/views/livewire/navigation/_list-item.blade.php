{{--
    A single list row: drag handle (S4/Step 3, R5) + link + (except the
    Inbox, A9) a Move up/down/Rename/Move/Delete dropdown. $canMoveUp and
    $canMoveDown default true so an include site that does not care about
    reordering (none today — every caller passes them) still renders
    sensibly. wire:sort:item is set here rather than by the caller: every
    include site wants it, and it is a plain HTML attribute, not a prop this
    partial otherwise consumes.
--}}
@php
    $canMoveUp ??= true;
    $canMoveDown ??= true;
@endphp

<div wire:key="list-{{ $list->id }}" wire:sort:item="{{ $list->id }}" class="group/list relative flex items-center gap-1">
    @if (! $list->is_default)
        <span
            wire:sort:handle
            aria-hidden="true"
            title="{{ __('Drag to reorder') }}"
            class="shrink-0 cursor-grab touch-none text-zinc-300 active:cursor-grabbing dark:text-zinc-600"
        >
            <flux:icon.bars-2 variant="mini" class="size-4" />
        </span>
    @endif

    <flux:sidebar.item
        icon="list-bullet"
        :href="route('lists.show', $list)"
        :current="$currentTaskListId === $list->id"
        :badge="$list->active_tasks_count ?: null"
        wire:navigate
        class="flex-1"
    >
        {{ Str::limit($list->name, 40) }}
    </flux:sidebar.item>

    @if (! $list->is_default)
        <flux:dropdown class="absolute end-1 opacity-0 group-hover/list:opacity-100">
            <flux:button
                icon="ellipsis-horizontal"
                variant="ghost"
                size="sm"
                :aria-label="__('List options')"
            />

            <flux:menu>
                <flux:menu.item
                    icon="chevron-up"
                    wire:click="moveListUp({{ $list->id }})"
                    :disabled="! $canMoveUp"
                    :aria-label="__('Move :list up', ['list' => $list->name])"
                >
                    {{ __('Move up') }}
                </flux:menu.item>

                <flux:menu.item
                    icon="chevron-down"
                    wire:click="moveListDown({{ $list->id }})"
                    :disabled="! $canMoveDown"
                    :aria-label="__('Move :list down', ['list' => $list->name])"
                >
                    {{ __('Move down') }}
                </flux:menu.item>

                <flux:menu.item
                    icon="pencil"
                    wire:click="$dispatch('list-dialog-open', { mode: 'rename', listId: {{ $list->id }}, folderId: null })"
                >
                    {{ __('Rename / Move') }}
                </flux:menu.item>

                <flux:menu.item
                    icon="trash"
                    variant="danger"
                    wire:click="$dispatch('list-dialog-open', { mode: 'delete', listId: {{ $list->id }}, folderId: null })"
                >
                    {{ __('Delete') }}
                </flux:menu.item>
            </flux:menu>
        </flux:dropdown>
    @endif
</div>
