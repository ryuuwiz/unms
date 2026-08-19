<div class="max-w-3xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-bold text-zinc-900 dark:text-zinc-100">Profil Pelanggan</h1>
            <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">Informasi data diri dan lokasi pemasangan internet Anda</p>
        </div>

        <flux:button :href="route('portal.ganti-password')" size="sm" variant="ghost" icon="key" wire:navigate>
            Ganti Password
        </flux:button>
    </div>

    <!-- Data Pelanggan -->
    <flux:card class="p-6 space-y-4">
        <div class="flex items-center gap-3 border-b border-zinc-100 dark:border-zinc-800 pb-4">
            <div class="size-12 rounded-2xl bg-indigo-50 dark:bg-indigo-950/60 flex items-center justify-center font-bold text-lg text-indigo-600 dark:text-indigo-400">
                {{ strtoupper(substr($pelanggan->nama_depan, 0, 1)) }}
            </div>
            <div>
                <h2 class="text-base font-bold text-zinc-900 dark:text-zinc-100">{{ $pelanggan->namaLengkap() }}</h2>
                <div class="flex items-center gap-2 text-xs text-zinc-500 mt-0.5">
                    <span class="font-mono font-semibold text-indigo-600 dark:text-indigo-400">{{ $pelanggan->no_reg }}</span>
                    <span>&bull;</span>
                    <span>Status: <strong class="text-emerald-600">{{ $pelanggan->status->label() }}</strong></span>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
            <div>
                <span class="text-zinc-400">Alamat Email (Login):</span>
                <div class="font-semibold text-zinc-800 dark:text-zinc-200">{{ $akun->email }}</div>
            </div>
            <div>
                <span class="text-zinc-400">Nomor Telepon / WhatsApp:</span>
                <div class="font-semibold text-zinc-800 dark:text-zinc-200">{{ $pelanggan->no_hp }}</div>
            </div>
            <div>
                <span class="text-zinc-400">Tipe Pelanggan:</span>
                <div class="font-semibold text-zinc-800 dark:text-zinc-200">{{ $pelanggan->tipe_pelanggan->label() }}</div>
            </div>
            <div>
                <span class="text-zinc-400">Kode Pembayaran Tetap:</span>
                <div class="font-mono font-semibold text-zinc-800 dark:text-zinc-200">{{ $pelanggan->kode_pembayaran }}</div>
            </div>
        </div>

        <div class="pt-4 border-t border-zinc-100 dark:border-zinc-800 text-xs">
            <span class="text-zinc-400">Alamat Pemasangan:</span>
            <div class="font-medium text-zinc-800 dark:text-zinc-200 mt-0.5">{{ $pelanggan->alamat_lengkap }}</div>
            @if($pelanggan->perumahan)
                <div class="text-[11px] text-zinc-500 mt-1">
                    {{ $pelanggan->perumahan->nama_perumahan }}, Kel. {{ $pelanggan->perumahan->kelurahan?->nama_kelurahan }}, Kec. {{ $pelanggan->perumahan->kelurahan?->kecamatan?->nama_kecamatan }}, {{ $pelanggan->perumahan->kelurahan?->kecamatan?->kota?->nama_kota }}
                </div>
            @endif
        </div>
    </flux:card>

    <!-- Daftar Layanan Aktif -->
    <flux:card class="p-6 space-y-4">
        <h3 class="text-sm font-bold text-zinc-900 dark:text-zinc-100 flex items-center gap-2">
            <flux:icon icon="signal" class="size-4 text-indigo-500" />
            Detail Layanan Terpasang
        </h3>

        <div class="space-y-3">
            @foreach($pelanggan->layanans as $layanan)
                <div class="p-4 bg-zinc-50 dark:bg-zinc-800/50 rounded-xl border border-zinc-200 dark:border-zinc-700/60 text-xs space-y-2">
                    <div class="flex justify-between items-start">
                        <div>
                            <div class="font-bold text-sm text-zinc-900 dark:text-zinc-100">
                                {{ $layanan->paketLayanan?->nama_paket }}
                            </div>
                            <div class="text-indigo-600 dark:text-indigo-400 font-semibold mt-0.5">
                                {{ $layanan->paketLayanan?->profilBandwidth?->nama_bandwidth }}
                            </div>
                        </div>
                        <flux:badge size="sm" variant="pill" :color="$layanan->status->color()">
                            {{ $layanan->status->label() }}
                        </flux:badge>
                    </div>

                    <div class="grid grid-cols-2 gap-2 pt-2 border-t border-zinc-200/60 dark:border-zinc-700/40 text-[11px] text-zinc-600 dark:text-zinc-400">
                        <div>Site ID: <span class="font-mono font-semibold">{{ $layanan->site_id }}</span></div>
                        <div>PPP Username: <span class="font-mono font-semibold">{{ $layanan->ppp_username }}</span></div>
                        <div>Masa Aktif: <span>{{ $layanan->tanggal_expired ? \Carbon\Carbon::parse($layanan->tanggal_expired)->translatedFormat('d M Y') : '-' }}</span></div>
                        <div>Harga Paket: <span>Rp {{ number_format((float) $layanan->paketLayanan?->harga, 0, ',', '.') }}/bulan</span></div>
                    </div>
                </div>
            @endforeach
        </div>
    </flux:card>
</div>
