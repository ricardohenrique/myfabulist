<x-layouts::app.sidebar :title="$title ?? null">
    <flux:main class="wunder-main">
        <header class="wunder-topbar">
            <flux:sidebar.toggle class="wunder-mobile-toggle" icon="bars-2" inset="left" />
            <flux:icon.magnifying-glass class="wunder-topbar-search size-6" />

            <h1 class="wunder-view-title">{{ $title ?? config('app.name', 'My Fabulist') }}</h1>

            <div class="wunder-topbar-actions" aria-label="{{ __('View actions') }}">
                <span class="wunder-topbar-action">
                    <flux:icon.user-plus class="size-5" />
                    {{ __('Share') }}
                </span>
                <span class="wunder-topbar-action">
                    <flux:icon.arrows-up-down class="size-5" />
                    {{ __('Sort') }}
                </span>
                <span class="wunder-topbar-action">
                    <flux:icon.ellipsis-horizontal class="size-5" />
                    {{ __('More') }}
                </span>
            </div>
        </header>

        <div class="wunder-content">
            <div class="wunder-content-scroll">
                <div class="wunder-task-column">
                    {{ $slot }}
                </div>
            </div>
        </div>
    </flux:main>
</x-layouts::app.sidebar>
