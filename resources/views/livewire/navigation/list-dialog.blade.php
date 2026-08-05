<div>
    <flux:modal name="list-dialog" class="max-w-md">
        @if ($mode === 'delete')
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">{{ __('Delete ":name"?', ['name' => $name]) }}</flux:heading>
                    <flux:subheading>{{ __('Its tasks will be deleted too. This cannot be undone.') }}</flux:subheading>
                </div>

                <div class="flex justify-end gap-2">
                    <flux:modal.close>
                        <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                    </flux:modal.close>

                    <flux:button variant="danger" wire:click="delete" wire:loading.delay.attr="disabled">
                        {{ __('Delete') }}
                    </flux:button>
                </div>
            </div>
        @else
            <form wire:submit="save" class="space-y-6">
                <flux:heading size="lg">
                    {{ $mode === 'create' ? __('New list') : __('Rename / move list') }}
                </flux:heading>

                <flux:input wire:model="name" :label="__('Name')" autofocus />

                <flux:select wire:model="folderId" :label="__('Folder')">
                    <flux:select.option value="">{{ __('No folder') }}</flux:select.option>
                    @foreach ($this->folders as $folder)
                        <flux:select.option value="{{ $folder->id }}">{{ $folder->name }}</flux:select.option>
                    @endforeach
                </flux:select>

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
