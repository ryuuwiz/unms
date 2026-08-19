<div class="max-w-md mx-auto my-10 sm:my-16">
    <div class="text-center mb-8">
        <div class="inline-flex items-center justify-center size-14 rounded-2xl bg-gradient-to-tr from-indigo-600 to-purple-600 text-white shadow-xl shadow-indigo-500/25 mb-4">
            <flux:icon icon="bolt" class="size-7 text-white" />
        </div>
        <h1 class="text-2xl font-bold tracking-tight text-zinc-900 dark:text-zinc-100">
            Portal Pelanggan
        </h1>
        <p class="text-sm text-zinc-500 dark:text-zinc-400 mt-1">
            Masuk untuk mengecek tagihan, pembayaran, & status internet Anda
        </p>
    </div>

    <flux:card class="p-6 sm:p-8 backdrop-blur-sm bg-white/95 dark:bg-zinc-900/95 border-zinc-200/80 dark:border-zinc-800 shadow-xl shadow-zinc-950/5">
        <form wire:submit="login" class="space-y-5">
            <flux:input
                wire:model="email"
                type="email"
                label="Email Pelanggan"
                placeholder="nama@email.com"
                icon="envelope"
                required
                autofocus
            />

            <flux:input
                wire:model="password"
                type="password"
                label="Password"
                placeholder="••••••••"
                icon="key"
                viewable
                required
            />

            <div class="flex items-center justify-between text-xs">
                <flux:checkbox wire:model="remember" label="Ingat saya" />
                <a href="{{ route('portal.klaim-akun') }}" wire:navigate class="text-indigo-600 dark:text-indigo-400 hover:underline font-medium">
                    Aktivasi / Reset Akun?
                </a>
            </div>

            <flux:button type="submit" variant="primary" class="w-full justify-center">
                Masuk ke Portal
            </flux:button>
        </form>

        <div class="mt-6 pt-6 border-t border-zinc-100 dark:border-zinc-800/80 text-center text-xs text-zinc-500 dark:text-zinc-400">
            Password standar pelanggan baru: <span class="font-mono font-semibold text-zinc-700 dark:text-zinc-300">12345678</span> (disarankan segera diubah).
        </div>
    </flux:card>
</div>
