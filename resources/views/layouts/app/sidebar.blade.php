<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">
        <x-impersonation-banner />
        <flux:sidebar sticky collapsible class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.header>
                <x-app-logo :sidebar="true" href="{{ route('dashboard') }}" wire:navigate />
                <flux:sidebar.collapse class="in-data-flux-sidebar-on-desktop:not-in-data-flux-sidebar-collapsed-desktop:-mr-2" />
            </flux:sidebar.header>

            <flux:sidebar.nav>
                @foreach (config('menu.standalone', []) as $item)
                    @if (!isset($item['permission']) || empty($item['permission']) || (auth()->check() && auth()->user()->can($item['permission'])))
                        <flux:sidebar.item
                            :icon="$item['icon']"
                            :href="route($item['route'])"
                            :current="request()->routeIs($item['active'] ?? $item['route'])"
                            wire:navigate
                        >
                            {{ __($item['title']) }}
                        </flux:sidebar.item>
                    @endif
                @endforeach

                @foreach (config('menu.groups', []) as $group)
                    @php
                        $visibleItems = collect($group['items'] ?? [])->filter(function ($item) {
                            return !isset($item['permission']) || empty($item['permission']) || (auth()->check() && auth()->user()->can($item['permission']));
                        });
                        $hasActiveItem = $visibleItems->contains(function ($item) {
                            return request()->routeIs($item['active'] ?? $item['route']);
                        });
                        $isExpandable = $group['expandable'] ?? false;
                        $isExpanded = $hasActiveItem || ($group['expanded'] ?? false);
                    @endphp

                    @if ($visibleItems->isNotEmpty())
                        <flux:sidebar.group
                            :heading="__($group['heading'] ?? '')"
                            :icon="$group['icon'] ?? null"
                            :expandable="$isExpandable"
                            :expanded="$isExpanded"
                            class="grid"
                        >
                            @foreach ($visibleItems as $item)
                                <flux:sidebar.item
                                    :icon="$item['icon']"
                                    :href="route($item['route'])"
                                    :current="request()->routeIs($item['active'] ?? $item['route'])"
                                    wire:navigate
                                >
                                    {{ __($item['title']) }}
                                </flux:sidebar.item>
                            @endforeach
                        </flux:sidebar.group>
                    @endif
                @endforeach
            </flux:sidebar.nav>

            <flux:spacer />

            <div class="hidden lg:block px-2"><livewire:notifikasi-lonceng /></div>

            <x-desktop-user-menu class="hidden lg:block" :name="auth()->user()->name" />
        </flux:sidebar>

        <!-- Mobile Header -->
        <flux:header class="lg:hidden border-b border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <!-- Mobile User Menu -->
            <flux:navbar class="w-full">
                <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

                <flux:spacer />

                <livewire:notifikasi-lonceng />

                <flux:dropdown position="top" align="end">
                    <flux:profile
                        :initials="auth()->user()->initials()"
                        icon-trailing="chevron-down"
                    />

                    <flux:menu>
                        <flux:menu.radio.group>
                            <div class="p-0 text-sm font-normal">
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
                            </div>
                        </flux:menu.radio.group>

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
                        </flux:menu.radio.group>

                        <flux:menu.separator />

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
                    </flux:menu>
                </flux:dropdown>
            </flux:navbar>
        </flux:header>

        {{ $slot }}

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
