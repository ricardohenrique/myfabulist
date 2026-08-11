{{--
    The task details flyout (D2) — mounted once per page (inbox, starred,
    list) and opened via the `task-details-open` event. TaskService::update()
    has replace semantics (D4), so this form always submits every field.
--}}
<div>
    <flux:modal name="task-details" variant="flyout" class="wunder-task-detail">
        <div class="wunder-detail-header">
            <span class="wunder-task-checkbox" aria-hidden="true">
            </span>
            <div class="wunder-detail-title">
                <flux:input wire:model="title" :label="__('Title')" autofocus />
            </div>
            <button
                type="button"
                wire:click="$toggle('isStarred')"
                class="wunder-task-star {{ $isStarred ? 'is-starred' : '' }}"
                aria-label="{{ __('Toggle star') }}"
            >
                <flux:icon.star variant="{{ $isStarred ? 'solid' : 'outline' }}" class="size-5" />
            </button>
        </div>

        {{-- No Flux date-picker in the free tier (Architecture Review) — the
             native control is keyboard accessible and mobile-friendly. --}}
        <div class="wunder-detail-section">
            <flux:icon.calendar-days class="wunder-detail-icon" />
            <div class="wunder-detail-field">
                <flux:input type="date" wire:model="dueDate" :label="__('Due date')" />
            </div>

            @if ($dueDate)
                <flux:button wire:click="$set('dueDate', null)" variant="ghost" size="sm">
                    {{ __('Clear') }}
                </flux:button>
            @endif
        </div>

        <div class="wunder-detail-section">
            <flux:icon.bell-alert class="wunder-detail-icon" />
            <div class="wunder-detail-field">
                <div class="wunder-detail-label">{{ __('Reminder') }}</div>
                <div class="wunder-detail-muted">{{ __('No reminder') }}</div>
            </div>
        </div>

        {{-- R2: notes are the first multi-line free-text field in the product —
             plain {{ }} inside a <textarea> preserves line breaks safely,
             with no raw-echo of user input anywhere. --}}
        <div class="wunder-detail-section">
            <flux:icon.pencil-square class="wunder-detail-icon" />
            <div class="wunder-detail-field">
                <flux:textarea wire:model="note" :label="__('Notes')" rows="5">{{ $note }}</flux:textarea>
            </div>
        </div>

        {{-- Move-to-list (S6/Step 4) "deliberate path" — the row menu's
             submenu is the fast path; both call TaskService::move() with
             position: null (D5). --}}
        <div class="wunder-detail-section">
            <flux:icon.list-bullet class="wunder-detail-icon" />
            <div class="wunder-detail-field">
                <flux:select wire:model="taskListId" :label="__('List')">
                    @foreach ($this->lists as $list)
                        <flux:select.option value="{{ $list->id }}">{{ $list->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
        </div>

        <div class="wunder-detail-footer">
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
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>

                <flux:button wire:click="save" variant="primary" class="wunder-detail-save" icon="check" wire:loading.delay.attr="disabled">
                    {{ __('Save') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
