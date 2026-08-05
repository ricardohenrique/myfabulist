{{--
    A single list row: link + (except the Inbox, A9) a Move up/down/Rename/
    Move/Delete dropdown. $canMoveUp and $canMoveDown default true so an
    include site that does not care about reordering (none today — every
    caller passes them) still renders sensibly. wire:sort:item is set here
    rather than by the caller: every include site wants it, and it is a
    plain HTML attribute, not a prop this partial otherwise consumes.

    No dedicated drag handle here (unlike the folder row): a list is a leaf
    node with no nested sortable group beneath it, so the whole row can
    safely be the drag surface — this also keeps its icon flush with every
    other row's icon column (Inbox/Starred/folders all start at the same
    x), which a leading handle element would otherwise offset.
--}}
@php
    $canMoveUp ??= true;
    $canMoveDown ??= true;
@endphp

<div wire:key="list-{{ $list->id }}" wire:sort:item="{{ $list->id }}" class="wunder-list-wrap group/list relative">
    <a
        href="{{ route('lists.show', $list) }}"
        @class(['wunder-list-row', 'is-active' => $currentTaskListId === $list->id])
        wire:navigate
        @if ($currentTaskListId === $list->id) aria-current="page" @endif
        @if ($currentTaskListId === $list->id) data-current="data-current" @endif
    >
        <flux:icon.list-bullet class="wunder-icon wunder-icon-list" />
        <span class="wunder-list-name">
        {{ Str::limit($list->name, 40) }}
        </span>
        @if ($list->active_tasks_count)
            <span class="wunder-list-count">{{ $list->active_tasks_count }}</span>
        @endif
    </a>

    @if (! $list->is_default)
        {{-- Swaps into the exact slot the active-task badge occupies above,
             rather than floating on top of it — the two never fight for the
             same pixels (previously the source of a broken-looking hover). --}}
        <flux:dropdown class="wunder-row-tools absolute inset-y-0 end-1.5 flex items-center">
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
