<x-layouts::app :title="__('Inbox')">
    <div class="flex h-full w-full flex-1 flex-col">
        <livewire:tasks.task-panel :task-list-id="$taskListId" />
    </div>
</x-layouts::app>
