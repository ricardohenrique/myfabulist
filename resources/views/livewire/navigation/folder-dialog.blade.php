<div>
    <flux:modal name="folder-dialog" class="max-w-md">
        @if ($mode === 'delete')
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">{{ __('Delete ":name"?', ['name' => $name]) }}</flux:heading>

                    @if ($folderHasLists)
                        <flux:subheading>
                            {{ __('This folder still has lists in it. Move them out first, or delete everything inside it.') }}
                        </flux:subheading>
                    @else
                        <flux:subheading>{{ __('This cannot be undone.') }}</flux:subheading>
                    @endif
                </div>

                <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <flux:modal.close>
                        <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                    </flux:modal.close>

                    @if ($folderHasLists)
                        <flux:button wire:click="moveListsOut" wire:loading.delay.attr="disabled">
                            {{ __('Move lists out') }}
                        </flux:button>

                        <flux:button
                            variant="danger"
                            wire:click="deleteFolderAndLists"
                            wire:confirm="{{ __('Delete this folder and everything inside it? This cannot be undone.') }}"
                            wire:loading.delay.attr="disabled"
                        >
                            {{ __('Delete folder and its lists') }}
                        </flux:button>
                    @else
                        <flux:button
                            variant="danger"
                            wire:click="deleteEmptyFolder"
                            wire:loading.delay.attr="disabled"
                        >
                            {{ __('Delete') }}
                        </flux:button>
                    @endif
                </div>
            </div>
        @else
            <form wire:submit="save" class="space-y-6">
                <flux:heading size="lg">
                    {{ $mode === 'create' ? __('New folder') : __('Rename folder') }}
                </flux:heading>

                <flux:input wire:model="name" :label="__('Name')" autofocus />

                <div class="flex justify-end gap-2">
                    <flux:modal.close>
                        <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                    </flux:modal.close>

                    <flux:button type="submit" variant="primary" wire:loading.delay.attr="disabled">
                        {{ __('Save') }}
                    </flux:button>
                </div>
            </form>
        @endif
    </flux:modal>
</div>
