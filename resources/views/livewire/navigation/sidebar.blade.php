<div wire:loading.class="opacity-50" wire:target="refreshTree">
    <flux:sidebar.nav>
        <div data-nav="primary">
            <flux:sidebar.item
                icon="inbox"
                :href="route('inbox')"
                :current="$currentRouteName === 'inbox'"
                :badge="$this->tree->inbox->active_tasks_count ?: null"
                wire:navigate
            >
                {{ __('Inbox') }}
            </flux:sidebar.item>

            <flux:sidebar.item icon="star" :href="route('starred')" :current="$currentRouteName === 'starred'" wire:navigate>
                {{ __('Starred') }}
            </flux:sidebar.item>
        </div>

        <div class="mt-4 flex items-center justify-between px-3" data-nav="tree-actions">
            <flux:text size="sm" class="font-medium text-zinc-500 dark:text-zinc-400">{{ __('Folders and lists') }}</flux:text>

            <div class="flex items-center gap-1">
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
            <div class="mt-2 flex flex-col items-center gap-3 rounded-xl border border-dashed border-zinc-200 px-4 py-8 text-center dark:border-zinc-700" data-test="navigation-empty-state">
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
        <div class="mt-2 flex flex-col gap-0.5" data-nav="folders">
            {{-- Folder reordering (S4/Step 3): buttons + drag, both drag and
                 button entry points funnel into reorderFolder()/moveFolderUp/
                 Down(). Not .renderless — a folder row carries a nested list
                 sub-tree, so a full re-render here is cheap insurance rather
                 than the flicker-avoidance trade-off .renderless makes for a
                 single task row (C3 is about that trade-off, not a blanket
                 rule). --}}
            <div wire:sort="reorderFolder($item, $position)" class="flex flex-col gap-0.5">
                @foreach ($this->tree->folders as $navigationFolder)
                    @php $folder = $navigationFolder->folder; @endphp
                    <div wire:key="folder-{{ $folder->id }}" wire:sort:item="{{ $folder->id }}" x-data="{ open: $persist(true).as('folder-open-{{ $folder->id }}') }">
                        {{-- h-8/gap-3/px-3 mirror flux:sidebar.item exactly (see
                             vendor/livewire/flux/.../sidebar/item.blade.php) so a
                             folder's icon lands in the same column as Inbox,
                             Starred and every list row — no drag handle sits in
                             front of it to shift it over. The row itself carries
                             the hover background so highlighting covers its full
                             width, not just the toggle button's. --}}
                        <div class="group flex h-8 items-center gap-3 rounded-lg px-3 text-zinc-500 hover:bg-zinc-800/5 hover:text-zinc-800 dark:text-white/80 dark:hover:bg-white/[7%] dark:hover:text-white">
                            <button
                                type="button"
                                x-on:click="open = !open"
                                class="flex min-w-0 flex-1 items-center gap-3 text-start"
                                :aria-label="open ? '{{ __('Collapse :folder', ['folder' => $folder->name]) }}' : '{{ __('Expand :folder', ['folder' => $folder->name]) }}'"
                            >
                                {{-- A folder icon, distinct from a list's
                                     list-bullet icon, so the two are
                                     recognisable at a glance. --}}
                                <flux:icon.folder class="size-4 shrink-0 text-zinc-400 dark:text-zinc-500" />
                                <span class="flex-1 truncate text-sm font-medium">{{ $folder->name }}</span>
                            </button>

                            <div class="flex shrink-0 items-center gap-0.5 opacity-0 group-hover:opacity-100">
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
                                    class="flex size-6 shrink-0 cursor-grab items-center justify-center touch-none text-zinc-300 active:cursor-grabbing dark:text-zinc-600"
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
                                class="shrink-0 text-zinc-400 dark:text-zinc-500"
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
                             A's ListDialog "Move" action. --}}
                        <div
                            x-show="open"
                            x-collapse
                            wire:sort.renderless="reorderList($item, $position, {{ $folder->id }})"
                            wire:sort:group="lists-{{ $folder->id }}"
                            class="flex flex-col gap-0.5 ps-4"
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
                 container (M2/A9). --}}
            <div
                wire:sort.renderless="reorderList($item, $position, null)"
                wire:sort:group="lists-root"
                class="flex flex-col gap-0.5"
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
    </flux:sidebar.nav>

    <livewire:navigation.folder-dialog :current-task-list-id="$currentTaskListId" />
    <livewire:navigation.list-dialog :current-task-list-id="$currentTaskListId" />
</div>
