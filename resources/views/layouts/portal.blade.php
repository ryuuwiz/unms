<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-zinc-50 dark:bg-zinc-900 text-zinc-900 dark:text-zinc-100 flex flex-col">
        <x-impersonation-banner />
        @auth('pelanggan')
            <!-- Portal Customer Navigation Bar -->
            <flux:header class="border-b border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 px-4 lg:px-8">
                <div class="flex items-center gap-3">
                    <div class="flex items-center justify-center size-9 rounded-xl bg-gradient-to-br from-indigo-500 to-purple-600 text-white font-bold shadow-md shadow-indigo-500/20">
                        <flux:icon icon="bolt" class="size-5 text-white" />
                    </div>
                    <div>
                        <div class="font-bold text-sm leading-tight tracking-wide flex items-center gap-1.5">
                            {{ config('app.name', 'GOBILLING') }} <span class="text-xs px-1.5 py-0.5 rounded-md bg-indigo-50 dark:bg-indigo-950/80 text-indigo-600 dark:text-indigo-400 font-semibold border border-indigo-200/50 dark:border-indigo-800/50">PORTAL</span>
                        </div>
                        <div class="text-[11px] text-zinc-500 dark:text-zinc-400">Area Pelanggan</div>
                    </div>
                </div>

                <flux:navbar class="ms-8 hidden md:flex">
                    <flux:navbar.item
                        icon="home"
                        :href="route('portal.dashboard')"
                        :current="request()->routeIs('portal.dashboard')"
                        wire:navigate
                    >
                        {{ __('Dashboard') }}
                    </flux:navbar.item>

                    <flux:navbar.item
                        icon="document-text"
                        :href="route('portal.invoice.index')"
                        :current="request()->routeIs('portal.invoice.*')"
                        wire:navigate
                    >
                        {{ __('Tagihan & Pembayaran') }}
                    </flux:navbar.item>

                    <flux:navbar.item
                        icon="ticket"
                        :href="route('portal.tiket.index')"
                        :current="request()->routeIs('portal.tiket.*')"
                        wire:navigate
                    >
                        {{ __('Tiket Saya') }}
                    </flux:navbar.item>

                    <flux:navbar.item
                        icon="user"
                        :href="route('portal.profil')"
                        :current="request()->routeIs('portal.profil')"
                        wire:navigate
                    >
                        {{ __('Profil & Langganan') }}
                    </flux:navbar.item>
                </flux:navbar>

                <flux:spacer />

                <div class="flex items-center gap-3">
                    <livewire:portal.notification-bell />

                    <flux:dropdown position="bottom" align="end">
                        <flux:button variant="ghost" class="flex items-center gap-2 px-2 py-1.5 rounded-lg">
                            <div class="size-7 rounded-full bg-zinc-200 dark:bg-zinc-700 flex items-center justify-center font-bold text-xs">
                                {{ strtoupper(substr(auth('pelanggan')->user()->nama_lengkap ?? 'P', 0, 1)) }}
                            </div>
                            <span class="text-sm font-medium hidden sm:inline-block max-w-36 truncate">
                                {{ auth('pelanggan')->user()->nama_lengkap }}
                            </span>
                            <flux:icon icon="chevron-down" class="size-4 text-zinc-400" />
                        </flux:button>

                        <flux:menu class="w-56">
                            <div class="px-3 py-2 border-b border-zinc-100 dark:border-zinc-800">
                                <div class="font-semibold text-xs text-zinc-900 dark:text-zinc-100 truncate">
                                    {{ auth('pelanggan')->user()->nama_lengkap }}
                                </div>
                                <div class="text-[11px] text-zinc-500 truncate">
                                    {{ auth('pelanggan')->user()->email }}
                                </div>
                                @if(auth('pelanggan')->user()->pelanggan)
                                    <div class="text-[10px] text-indigo-500 font-mono mt-0.5">
                                        {{ auth('pelanggan')->user()->pelanggan->no_reg }}
                                    </div>
                                @endif
                            </div>

                            @if (app('impersonate')->isImpersonating())
                                <flux:menu.item :href="route('impersonate.leave')" icon="arrow-uturn-left" class="text-amber-600 dark:text-amber-400 font-semibold">
                                    {{ __('Kembali ke Akun Asli') }}
                                </flux:menu.item>
                            @endif

                            <flux:menu.item :href="route('portal.profil')" icon="user" wire:navigate>
                                {{ __('Profil Akun') }}
                            </flux:menu.item>

                            <flux:menu.item :href="route('portal.ganti-password')" icon="key" wire:navigate>
                                {{ __('Ganti Password') }}
                            </flux:menu.item>

                            <flux:menu.separator />

                            <form method="POST" action="{{ route('portal.logout') }}">
                                @csrf
                                <flux:menu.item
                                    as="button"
                                    type="submit"
                                    icon="arrow-right-start-on-rectangle"
                                    class="w-full text-rose-600 dark:text-rose-400 cursor-pointer"
                                >
                                    {{ __('Keluar') }}
                                </flux:menu.item>
                            </form>
                        </flux:menu>
                    </flux:dropdown>
                </div>
            </flux:header>

            <!-- Mobile Sub-Navbar -->
            <div class="md:hidden border-b border-zinc-200 dark:border-zinc-800 bg-zinc-100/80 dark:bg-zinc-900/80 px-4 py-1.5 flex justify-around">
                <a href="{{ route('portal.dashboard') }}" wire:navigate class="text-xs flex flex-col items-center gap-0.5 py-1 {{ request()->routeIs('portal.dashboard') ? 'text-indigo-600 dark:text-indigo-400 font-semibold' : 'text-zinc-500' }}">
                    <flux:icon icon="home" class="size-4" />
                    <span>Dashboard</span>
                </a>
                <a href="{{ route('portal.invoice.index') }}" wire:navigate class="text-xs flex flex-col items-center gap-0.5 py-1 {{ request()->routeIs('portal.invoice.*') ? 'text-indigo-600 dark:text-indigo-400 font-semibold' : 'text-zinc-500' }}">
                    <flux:icon icon="document-text" class="size-4" />
                    <span>Tagihan</span>
                </a>
                <a href="{{ route('portal.tiket.index') }}" wire:navigate class="text-xs flex flex-col items-center gap-0.5 py-1 {{ request()->routeIs('portal.tiket.*') ? 'text-indigo-600 dark:text-indigo-400 font-semibold' : 'text-zinc-500' }}">
                    <flux:icon icon="ticket" class="size-4" />
                    <span>Tiket</span>
                </a>
                <a href="{{ route('portal.profil') }}" wire:navigate class="text-xs flex flex-col items-center gap-0.5 py-1 {{ request()->routeIs('portal.profil') ? 'text-indigo-600 dark:text-indigo-400 font-semibold' : 'text-zinc-500' }}">
                    <flux:icon icon="user" class="size-4" />
                    <span>Profil</span>
                </a>
            </div>
        @endauth

        <main class="flex-1 max-w-7xl w-full mx-auto p-4 sm:p-6 lg:p-8">
            {{ $slot }}
        </main>

        <footer class="border-t border-zinc-200 dark:border-zinc-800 py-6 text-center text-xs text-zinc-500 dark:text-zinc-400 mt-auto">
            &copy; {{ date('Y') }} {{ config('app.name', 'GOBILLING') }} Customer Portal &bull; Layanan Internet Cepat & Stabil
        </footer>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
