<x-layouts::app :title="__('Inbox')">
    <div class="wunder-page">
        <livewire:lists.list-header :task-list-id="$taskListId" />

        <livewire:tasks.task-panel :task-list-id="$taskListId" />
    </div>

    <livewire:tasks.task-details />
</x-layouts::app>
