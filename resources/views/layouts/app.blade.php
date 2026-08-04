{{-- frontend.md "Visual style": a light neutral page background (the
     <body>, set in layouts/app/sidebar.blade.php) with white content
     surfaces — this card is that surface for the Inbox/Starred/List pages. --}}
<x-layouts::app.sidebar :title="$title ?? null">
    <flux:main>
        <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
            {{ $slot }}
        </div>
    </flux:main>
</x-layouts::app.sidebar>
