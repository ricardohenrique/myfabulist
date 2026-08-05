@php $user = auth()->user(); @endphp

<div {{ $attributes->class('wunder-profile') }}>
    <flux:dropdown position="bottom" align="start" class="wunder-profile-trigger">
        <flux:sidebar.profile
            :name="$user->name"
            :initials="$user->initials()"
            :avatar="$user->profilePhotoUrl"
            circle
            icon:trailing="chevrons-up-down"
            data-test="sidebar-menu-button"
        />

        <flux:menu>
            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                <flux:avatar
                    :name="$user->name"
                    :initials="$user->initials()"
                    :src="$user->profilePhotoUrl"
                    circle
                />
                <div class="grid flex-1 text-start text-sm leading-tight">
                    <flux:heading class="truncate">{{ $user->name }}</flux:heading>
                    <flux:text class="truncate">{{ $user->email }}</flux:text>
                </div>
            </div>
            <flux:menu.separator />
            <flux:menu.radio.group>
                <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                    {{ __('Settings') }}
                </flux:menu.item>
                <form method="POST" action="{{ route('logout') }}" class="w-full">
                    @csrf
                    <flux:menu.item
                        as="button"
                        type="submit"
                        icon="arrow-right-start-on-rectangle"
                        class="w-full cursor-pointer"
                        data-test="logout-button"
                    >
                        {{ __('Log out') }}
                    </flux:menu.item>
                </form>
            </flux:menu.radio.group>
        </flux:menu>
    </flux:dropdown>

    <div class="wunder-profile-actions">
        <span class="wunder-profile-icon" aria-label="{{ __('Notifications') }}" role="img">
            <flux:icon.bell class="size-4" />
        </span>
        <span class="wunder-profile-icon" aria-label="{{ __('Messages') }}" role="img">
            <flux:icon.chat-bubble-left class="size-4" />
        </span>
    </div>
</div>
