{{-- The list page header (M2/D6): name, quiet active/completed counts, and
     (for a non-default list) rename/move/delete via Phase A's ListDialog. --}}
<div class="wunder-panel-head">
    <div>
        <h2 class="sr-only">{{ $this->list->name }}</h2>

        <flux:text size="sm" class="wunder-list-meta">
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
            class="text-white/80 hover:text-white"
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
