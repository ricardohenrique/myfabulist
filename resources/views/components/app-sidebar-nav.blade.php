<flux:sidebar.nav>
    <div data-nav="primary">
        <flux:sidebar.item icon="inbox" :href="route('inbox')" :current="request()->routeIs('inbox')" wire:navigate>
            {{ __('Inbox') }}
        </flux:sidebar.item>

        <flux:sidebar.item icon="star" :href="route('starred')" :current="request()->routeIs('starred')" wire:navigate>
            {{ __('Starred') }}
        </flux:sidebar.item>
    </div>
</flux:sidebar.nav>
