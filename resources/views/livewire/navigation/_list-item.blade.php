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

<div wire:key="list-{{ $list->id }}" wire:sort:item="{{ $list->id }}" class="group/list relative">
    <flux:sidebar.item
        icon="list-bullet"
        :href="route('lists.show', $list)"
        :current="$currentTaskListId === $list->id"
        :badge="$list->active_tasks_count ?: null"
        wire:navigate
        :class="! $list->is_default ? 'group-hover/list:[&_[data-flux-navlist-badge]]:invisible' : ''"
    >
        {{ Str::limit($list->name, 40) }}
    </flux:sidebar.item>

    @if (! $list->is_default)
        {{-- Swaps into the exact slot the active-task badge occupies above,
             rather than floating on top of it — the two never fight for the
             same pixels (previously the source of a broken-looking hover). --}}
        <flux:dropdown class="absolute inset-y-0 end-1.5 flex items-center opacity-0 group-hover/list:opacity-100">
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
