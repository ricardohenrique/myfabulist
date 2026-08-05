<x-layouts::app.sidebar :title="$title ?? null">
    <flux:main class="app-main">
        <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
            {{ $slot }}
        </div>
    </flux:main>
</x-layouts::app.sidebar>
