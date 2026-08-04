{{-- The list page header (M2/D6): name, quiet active/completed counts, and
     (for a non-default list) rename/move/delete via Phase A's ListDialog. --}}
<div class="flex items-center justify-between gap-2">
    <div>
        <flux:heading size="xl">{{ $this->list->name }}</flux:heading>

        <flux:text size="sm" class="text-zinc-400">
            {{ __(':count active', ['count' => $this->tasks->active->count()]) }}
            @if ($this->tasks->completedCount > 0)
                &middot; {{ __(':count completed', ['count' => $this->tasks->completedCount]) }}
            @endif
        </flux:text>
    </div>

    @unless ($this->list->is_default)
        <flux:dropdown>
            <flux:button
                icon="ellipsis-horizontal"
                variant="ghost"
                size="sm"
                :aria-label="__('List options')"
            />

            <flux:menu>
                <flux:menu.item
                    icon="pencil"
                    wire:click="$dispatch('list-dialog-open', { mode: 'rename', listId: {{ $this->list->id }}, folderId: null })"
                >
                    {{ __('Rename / Move') }}
                </flux:menu.item>

                <flux:menu.item
                    icon="trash"
                    variant="danger"
                    wire:click="$dispatch('list-dialog-open', { mode: 'delete', listId: {{ $this->list->id }}, folderId: null })"
                >
                    {{ __('Delete') }}
                </flux:menu.item>
            </flux:menu>
        </flux:dropdown>
    @endunless
</div>
