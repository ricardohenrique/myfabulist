<div class="wunder-nav" wire:loading.class="opacity-50" wire:target="refreshTree">
    <nav aria-label="{{ __('Sidebar') }}">
        <div class="wunder-primary-nav" data-nav="primary">
            <a
                href="{{ route('inbox') }}"
                @class(['wunder-nav-item', 'is-active' => $currentRouteName === 'inbox'])
                wire:navigate
                @if ($currentRouteName === 'inbox') aria-current="page" @endif
                @if ($currentRouteName === 'inbox') data-current="data-current" @endif
            >
                <flux:icon.inbox class="wunder-icon wunder-icon-inbox" />
                <span class="wunder-nav-label">{{ __('Inbox') }}</span>
                @if ($this->tree->inbox->active_tasks_count)
                    <span class="wunder-nav-count">{{ $this->tree->inbox->active_tasks_count }}</span>
                @endif
            </a>

            <a
                href="{{ route('starred') }}"
                @class(['wunder-nav-item', 'is-active' => $currentRouteName === 'starred'])
                wire:navigate
                @if ($currentRouteName === 'starred') aria-current="page" @endif
                @if ($currentRouteName === 'starred') data-current="data-current" @endif
            >
                <flux:icon.star class="wunder-icon wunder-icon-starred" />
                <span class="wunder-nav-label">{{ __('Starred') }}</span>
                @if ($this->tree->starredCount)
                    <span class="wunder-nav-count">{{ $this->tree->starredCount }}</span>
                @endif
            </a>
        </div>

        <div class="wunder-tree-actions" data-nav="tree-actions">
            <div class="flex items-center gap-1" aria-label="{{ __('Create navigation items') }}">
                <flux:button
                    wire:click="$dispatch('list-dialog-open', { mode: 'create', listId: null, folderId: null })"
                    icon="plus"
                    variant="ghost"
                    size="sm"
                    :aria-label="__('New list')"
                    data-test="new-list-button"
                />

                <flux:button
                    wire:click="$dispatch('folder-dialog-open', { mode: 'create', folderId: null })"
                    icon="folder-plus"
                    variant="ghost"
                    size="sm"
                    :aria-label="__('New folder')"
                    data-test="new-folder-button"
                />
            </div>
        </div>

        @if ($this->tree->folders === [] && $this->tree->ungroupedLists->isEmpty())
            {{-- Empty state (M10) --}}
            <div class="mx-3 mt-3 flex flex-col items-center gap-3 border border-dashed border-zinc-300 px-4 py-8 text-center" data-test="navigation-empty-state">
                <flux:text>{{ __('Organize your work with folders and lists.') }}</flux:text>

                <div class="flex flex-col gap-2 sm:flex-row">
                    <flux:button
                        wire:click="$dispatch('list-dialog-open', { mode: 'create', listId: null, folderId: null })"
                        size="sm"
                    >
                        {{ __('Create your first list') }}
                    </flux:button>

                    <flux:button
                        wire:click="$dispatch('folder-dialog-open', { mode: 'create', folderId: null })"
                        variant="ghost"
                        size="sm"
                    >
                        {{ __('Create a folder') }}
                    </flux:button>
                </div>
            </div>
        @else
        <div class="wunder-folder-tree" data-nav="folders">
            {{-- Folder reordering (S4/Step 3): buttons + drag, both drag and
                 button entry points funnel into reorderFolder()/moveFolderUp/
                 Down(). Not .renderless — a folder row carries a nested list
                 sub-tree, so a full re-render here is cheap insurance rather
                 than the flicker-avoidance trade-off .renderless makes for a
                 single task row (C3 is about that trade-off, not a blanket
                 rule).

                 The handler is a bare method name, not
                 "reorderFolder($item, $position)": Livewire's expression
                 rewriter (contextualizeExpression) prefixes every bare
                 identifier in a call expression with "$wire." — including
                 the injected $item/$position magics — which don't exist as
                 component properties and evaluate to null, silently sending
                 reorderFolder(null, null) on every real drag (same bug
                 TaskPanel's reorderTask already works around, see there for
                 the fuller explanation). The bare form sidesteps this:
                 Livewire calls it positionally with ($id, $position). --}}
            <div wire:sort="reorderFolder" class="wunder-list-group">
                @foreach ($this->tree->folders as $navigationFolder)
                    @php $folder = $navigationFolder->folder; @endphp
                    <div wire:key="folder-{{ $folder->id }}" wire:sort:item="{{ $folder->id }}" x-data="{ open: $persist(true).as('folder-open-{{ $folder->id }}') }">
                        <div class="wunder-folder-row group">
                            <button
                                type="button"
                                x-on:click="open = !open"
                                class="wunder-folder-toggle"
                                :aria-label="open ? '{{ __('Collapse :folder', ['folder' => $folder->name]) }}' : '{{ __('Expand :folder', ['folder' => $folder->name]) }}'"
                                x-bind:aria-expanded="open.toString()"
                            >
                                <flux:icon.folder class="wunder-icon wunder-icon-folder" />
                                <span class="wunder-folder-name">{{ $folder->name }}</span>
                            </button>

                            <div class="wunder-row-tools">
                                {{-- Dedicated drag handle (R5), relocated into
                                     this hover cluster rather than leading the
                                     row: a folder has a nested sortable list
                                     group beneath it when expanded, so — unlike
                                     a plain list row — it still needs an
                                     explicit handle to keep dragging the folder
                                     from fighting with dragging one of its
                                     lists. Its position in the row is cosmetic;
                                     wire:sort only needs it present. --}}
                                <span
                                    wire:sort:handle
                                    aria-hidden="true"
                                    title="{{ __('Drag to reorder') }}"
                                    class="flex size-6 shrink-0 cursor-grab items-center justify-center touch-none text-zinc-400 active:cursor-grabbing"
                                >
                                    <flux:icon.bars-2 variant="mini" class="size-4" />
                                </span>

                                <flux:button
                                    wire:click="$dispatch('list-dialog-open', { mode: 'create', listId: null, folderId: {{ $folder->id }} })"
                                    icon="plus"
                                    variant="ghost"
                                    size="sm"
                                    :aria-label="__('New list in :folder', ['folder' => $folder->name])"
                                />

                                <flux:dropdown>
                                    <flux:button
                                        icon="ellipsis-horizontal"
                                        variant="ghost"
                                        size="sm"
                                        :aria-label="__('Folder options')"
                                    />

                                    <flux:menu>
                                        <flux:menu.item
                                            icon="chevron-up"
                                            wire:click="moveFolderUp({{ $folder->id }})"
                                            :disabled="$loop->first"
                                            :aria-label="__('Move :folder up', ['folder' => $folder->name])"
                                        >
                                            {{ __('Move up') }}
                                        </flux:menu.item>

                                        <flux:menu.item
                                            icon="chevron-down"
                                            wire:click="moveFolderDown({{ $folder->id }})"
                                            :disabled="$loop->last"
                                            :aria-label="__('Move :folder down', ['folder' => $folder->name])"
                                        >
                                            {{ __('Move down') }}
                                        </flux:menu.item>

                                        <flux:menu.item
                                            icon="pencil"
                                            wire:click="$dispatch('folder-dialog-open', { mode: 'rename', folderId: {{ $folder->id }} })"
                                        >
                                            {{ __('Rename') }}
                                        </flux:menu.item>

                                        <flux:menu.item
                                            icon="trash"
                                            variant="danger"
                                            wire:click="$dispatch('folder-dialog-open', { mode: 'delete', folderId: {{ $folder->id }} })"
                                        >
                                            {{ __('Delete') }}
                                        </flux:menu.item>
                                    </flux:menu>
                                </flux:dropdown>
                            </div>

                            {{-- Always-visible expand/collapse indicator, at
                                 the row's trailing edge (matches the reference
                                 layout: icon + name leading, controls
                                 trailing). --}}
                            <button
                                type="button"
                                x-on:click="open = !open"
                                class="shrink-0 text-zinc-400"
                                aria-hidden="true"
                                tabindex="-1"
                            >
                                <flux:icon.chevron-down x-bind:class="open ? '' : '-rotate-90'" class="size-4 transition-transform" />
                            </button>
                        </div>

                        {{-- List reordering within this folder (S4/Step 3):
                             .renderless (C3) — SortableJS already shows the
                             drop client-side. A distinct wire:sort:group per
                             folder (C6) means Sortable refuses a cross-folder
                             drop; moving a list between folders stays Phase
                             A's ListDialog "Move" action.

                             Bare method name, same reason as reorderFolder()
                             above — an explicit "reorderList($item, $position,
                             ...)" call expression gets its $item/$position
                             magics mangled to null by Livewire's expression
                             rewriter. $folderId rides along as the 3rd
                             positional arg via wire:sort:group-id, Livewire's
                             built-in mechanism for exactly this. --}}
                        <div
                            x-show="open"
                            x-collapse
                            wire:sort.renderless="reorderList"
                            wire:sort:group-id="{{ $folder->id }}"
                            wire:sort:group="lists-{{ $folder->id }}"
                            class="wunder-child-lists"
                        >
                            @foreach ($navigationFolder->lists as $list)
                                @include('livewire.navigation._list-item', [
                                    'list' => $list,
                                    'canMoveUp' => ! $loop->first,
                                    'canMoveDown' => ! $loop->last,
                                ])
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Ungrouped lists (S4/Step 3) — its own sortable group so a
                 list can never be dragged out of the folders container above
                 and be mistaken for a folder (C6); the Inbox is never
                 rendered through this partial, so it can never enter this
                 container (M2/A9).

                 Bare method name (see the folder-scoped list container
                 above) — wire:sort:group-id is deliberately omitted here
                 rather than passed as "null": reorderList()'s $folderId
                 parameter defaults to null, which is exactly what an
                 ungrouped list needs. --}}
            <div
                wire:sort.renderless="reorderList"
                wire:sort:group="lists-root"
                class="wunder-list-group"
            >
                @foreach ($this->tree->ungroupedLists as $list)
                    @include('livewire.navigation._list-item', [
                        'list' => $list,
                        'canMoveUp' => ! $loop->first,
                        'canMoveDown' => ! $loop->last,
                    ])
                @endforeach
            </div>
        </div>
        @endif
    </nav>

    <livewire:navigation.folder-dialog :current-task-list-id="$currentTaskListId" />
    <livewire:navigation.list-dialog :current-task-list-id="$currentTaskListId" />
</div>
