<x-layouts::app :title="$taskList->name">
    <div class="wunder-page">
        <livewire:lists.list-header :task-list-id="$taskList->id" :wire:key="'list-header-'.$taskList->id" />

        <livewire:tasks.task-panel :task-list-id="$taskList->id" :wire:key="'list-'.$taskList->id" />
    </div>

    <livewire:tasks.task-details />
</x-layouts::app>
