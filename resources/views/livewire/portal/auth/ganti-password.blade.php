<div class="max-w-xl mx-auto py-6">
    <div class="mb-6">
        <h1 class="text-xl font-bold text-zinc-900 dark:text-zinc-100">Ganti Password Akun Portal</h1>
        <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">Amankan akun Anda dengan mengganti kata sandi secara berkala</p>
    </div>

    <flux:card class="p-6">
        <form wire:submit="updatePassword" class="space-y-4">
            <flux:input
                wire:model="current_password"
                type="password"
                label="Password Saat Ini"
                placeholder="••••••••"
                icon="lock-closed"
                viewable
                required
            />

            <flux:input
                wire:model="password"
                type="password"
                label="Password Baru"
                placeholder="Minimal 6 karakter"
                icon="key"
                viewable
                required
            />

            <flux:input
                wire:model="password_confirmation"
                type="password"
                label="Konfirmasi Password Baru"
                placeholder="Ulangi password baru"
                icon="check-badge"
                viewable
                required
            />

            <div class="flex items-center justify-end gap-3 pt-4 border-t border-zinc-100 dark:border-zinc-800">
                <flux:button :href="route('portal.dashboard')" variant="ghost" wire:navigate>
                    Kembali
                </flux:button>
                <flux:button type="submit" variant="primary">
                    Simpan Password Baru
                </flux:button>
            </div>
        </form>
    </flux:card>
</div>
