<x-layouts::app :title="__('Starred')">
    <div class="flex h-full w-full flex-1 flex-col">
        <livewire:tasks.starred-panel />
    </div>

    <livewire:tasks.task-details />
</x-layouts::app>
