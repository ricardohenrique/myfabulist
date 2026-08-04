<x-layouts::app :title="__('Inbox')">
    <div class="flex h-full w-full flex-1 flex-col gap-6">
        <livewire:lists.list-header :task-list-id="$taskListId" />

        <livewire:tasks.task-panel :task-list-id="$taskListId" />
    </div>

    <livewire:tasks.task-details />
</x-layouts::app>
