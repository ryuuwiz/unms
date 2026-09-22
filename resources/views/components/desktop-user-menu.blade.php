<flux:dropdown position="bottom" align="start">
    <flux:sidebar.profile
        :name="auth()->user()->name"
        :initials="auth()->user()->initials()"
        icon:trailing="chevrons-up-down"
        data-test="sidebar-menu-button"
    />

    <flux:menu>
        <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
            <flux:avatar
                :name="auth()->user()->name"
                :initials="auth()->user()->initials()"
            />
            <div class="grid flex-1 text-start text-sm leading-tight">
                <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
            </div>
        </div>
        <flux:menu.separator />
        <flux:menu.radio.group>
            @if (app('impersonate')->isImpersonating())
                <flux:menu.item :href="route('impersonate.leave')" icon="arrow-uturn-left" class="text-amber-600 dark:text-amber-400 font-semibold">
                    {{ __('Kembali ke Akun Asli') }}
                </flux:menu.item>
                <flux:menu.separator />
            @endif
            <flux:menu.item :href="route('profile.edit')" icon="user" wire:navigate>
                {{ __('Profile') }}
            </flux:menu.item>
            <flux:menu.item :href="route('security.edit')" icon="lock-closed" wire:navigate>
                {{ __('Security') }}
            </flux:menu.item>
            <flux:menu.item :href="route('appearance.edit')" icon="swatch" wire:navigate>
                {{ __('Appearance') }}
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
