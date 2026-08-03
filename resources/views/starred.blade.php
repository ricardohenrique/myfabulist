<x-layouts::app :title="__('Starred')">
    <div class="flex h-full w-full flex-1 flex-col gap-4">
        <flux:heading size="xl">{{ __('Starred') }}</flux:heading>

        <div class="flex flex-1 items-center justify-center rounded-xl border border-dashed border-zinc-200 dark:border-zinc-700">
            <flux:text>{{ __('No starred tasks yet.') }}</flux:text>
        </div>
    </div>
</x-layouts::app>
