{{--
    The task details flyout (D2) — mounted once per page (inbox, starred,
    list) and opened via the `task-details-open` event. TaskService::update()
    has replace semantics (D4), so this form always submits every field.
--}}
<div>
    <flux:modal name="task-details" variant="flyout" class="space-y-6">
        <div>
            <flux:heading size="lg">{{ __('Task details') }}</flux:heading>
        </div>

        <flux:input wire:model="title" :label="__('Title')" autofocus />

        {{-- No Flux date-picker in the free tier (Architecture Review) — the
             native control is keyboard accessible and mobile-friendly. --}}
        <div class="flex items-end gap-2">
            <div class="flex-1">
                <flux:input type="date" wire:model="dueDate" :label="__('Due date')" />
            </div>

            @if ($dueDate)
                <flux:button wire:click="$set('dueDate', null)" variant="ghost" size="sm">
                    {{ __('Clear') }}
                </flux:button>
            @endif
        </div>

        {{-- R2: notes are the first multi-line free-text field in the product —
             plain {{ }} inside a <textarea> preserves line breaks safely,
             with no raw-echo of user input anywhere. --}}
        <flux:textarea wire:model="note" :label="__('Notes')" rows="4">{{ $note }}</flux:textarea>

        <flux:switch wire:model="isStarred" :label="__('Starred')" />

        {{-- Move-to-list (S6/Step 4) "deliberate path" — the row menu's
             submenu is the fast path; both call TaskService::move() with
             position: null (D5). --}}
        <flux:select wire:model="taskListId" :label="__('List')">
            @foreach ($this->lists as $list)
                <flux:select.option value="{{ $list->id }}">{{ $list->name }}</flux:select.option>
            @endforeach
        </flux:select>

        <div class="flex items-center justify-between border-t border-zinc-200 pt-4 dark:border-zinc-700">
            <flux:button
                wire:click="delete"
                wire:confirm="{{ __('Delete this task?') }}"
                variant="danger"
                icon="trash"
            >
                {{ __('Delete') }}
            </flux:button>

            <div class="flex gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>

                <flux:button wire:click="save" variant="primary" wire:loading.attr="disabled" wire:loading.delay>
                    {{ __('Save') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
