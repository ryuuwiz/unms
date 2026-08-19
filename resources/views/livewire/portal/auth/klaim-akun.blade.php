<div class="max-w-md mx-auto my-10 sm:my-16">
    <div class="text-center mb-8">
        <div class="inline-flex items-center justify-center size-14 rounded-2xl bg-gradient-to-tr from-emerald-600 to-teal-600 text-white shadow-xl shadow-emerald-500/25 mb-4">
            <flux:icon icon="shield-check" class="size-7 text-white" />
        </div>
        <h1 class="text-2xl font-bold tracking-tight text-zinc-900 dark:text-zinc-100">
            Aktivasi / Reset Akun Portal
        </h1>
        <p class="text-sm text-zinc-500 dark:text-zinc-400 mt-1">
            Verifikasi identitas Anda untuk mengatur kata sandi akun pelanggan
        </p>
    </div>

    <flux:card class="p-6 sm:p-8 backdrop-blur-sm bg-white/95 dark:bg-zinc-900/95 border-zinc-200/80 dark:border-zinc-800 shadow-xl shadow-zinc-950/5">
        @if(! $verified)
            <form wire:submit="verifikasiIdentitas" class="space-y-5">
                <flux:input
                    wire:model="no_reg"
                    type="text"
                    label="Nomor Registrasi Pelanggan"
                    placeholder="Contoh: REG-2026-000001"
                    icon="identification"
                    required
                    autofocus
                />

                <flux:input
                    wire:model="no_hp"
                    type="text"
                    label="Nomor HP Terdaftar"
                    placeholder="081234567890"
                    icon="phone"
                    required
                />

                <flux:button type="submit" variant="primary" class="w-full justify-center">
                    Verifikasi Data Pelanggan
                </flux:button>
            </form>
        @else
            <form wire:submit="simpanAkun" class="space-y-5">
                <div class="p-3 bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200/60 dark:border-emerald-800/60 rounded-xl text-xs">
                    <div class="font-bold text-emerald-800 dark:text-emerald-300">Data Terverifikasi:</div>
                    <div class="text-emerald-700 dark:text-emerald-400 mt-0.5">{{ $pelanggan->namaLengkap() }} ({{ $pelanggan->no_reg }})</div>
                </div>

                <flux:input
                    wire:model="email"
                    type="email"
                    label="Email Login"
                    placeholder="nama@email.com"
                    icon="envelope"
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
                    label="Ulangi Password"
                    placeholder="Konfirmasi password"
                    icon="check-badge"
                    viewable
                    required
                />

                <flux:button type="submit" variant="primary" class="w-full justify-center">
                    Simpan & Aktifkan Akun
                </flux:button>
            </form>
        @endif

        <div class="mt-6 pt-4 border-t border-zinc-100 dark:border-zinc-800 text-center text-xs">
            <a href="{{ route('portal.login') }}" wire:navigate class="text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200">
                &larr; Kembali ke halaman Login
            </a>
        </div>
    </flux:card>
</div>
