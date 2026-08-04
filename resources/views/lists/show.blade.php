<x-layouts::app :title="$taskList->name">
    <div class="flex h-full w-full flex-1 flex-col gap-6">
        <livewire:lists.list-header :task-list-id="$taskList->id" :wire:key="'list-header-'.$taskList->id" />

        <livewire:tasks.task-panel :task-list-id="$taskList->id" :wire:key="'list-'.$taskList->id" />
    </div>

    <livewire:tasks.task-details />
</x-layouts::app>
