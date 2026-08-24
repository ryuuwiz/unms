<div class="space-y-6">
    <!-- Welcome Header -->
    <div class="bg-gradient-to-r from-indigo-900 via-indigo-800 to-purple-900 rounded-2xl p-6 text-white shadow-xl shadow-indigo-950/20 relative overflow-hidden">
        <div class="absolute -right-8 -bottom-8 opacity-10">
            <flux:icon icon="bolt" class="size-64 text-white" />
        </div>
        <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <div class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-white/10 backdrop-blur-md text-xs font-semibold text-indigo-200 mb-2">
                    <span class="size-2 rounded-full bg-emerald-400 animate-pulse"></span>
                    No. Reg: {{ $pelanggan->no_reg }}
                </div>
                <h1 class="text-2xl font-bold">Halo, {{ $pelanggan->namaLengkap() }}!</h1>
                <p class="text-xs text-indigo-200/90 mt-1 max-w-xl">
                    Selamat datang di Portal Pelanggan {{ config('app.name', 'GOBILLING') }}. Pantau status langganan internet dan lakukan pembayaran tagihan dengan mudah dan aman.
                </p>
            </div>
            @if($unpaidInvoices->isNotEmpty())
                <div class="flex items-center">
                    <flux:button :href="route('portal.invoice.show', $unpaidInvoices->first())" variant="primary" class="bg-amber-500 hover:bg-amber-400 text-zinc-950 font-bold border-0 shadow-lg shadow-amber-500/25" wire:navigate>
                        <flux:icon icon="credit-card" class="size-4 mr-1.5" />
                        Bayar Tagihan Aktif ({{ $unpaidInvoices->count() }})
                    </flux:button>
                </div>
            @endif
        </div>
    </div>

    <!-- Alert Tagihan Menunggu Pembayaran -->
    @if($unpaidInvoices->isNotEmpty())
        <div class="space-y-3">
            <h2 class="text-sm font-bold text-zinc-900 dark:text-zinc-100 flex items-center gap-2">
                <flux:icon icon="exclamation-circle" class="size-4 text-amber-500" />
                Tagihan Menunggu Pembayaran
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                @foreach($unpaidInvoices as $inv)
                    <flux:card class="p-5 border-amber-300/60 dark:border-amber-700/50 bg-amber-50/40 dark:bg-amber-950/20">
                        <div class="flex items-start justify-between">
                            <div>
                                <div class="font-mono text-xs font-bold text-amber-900 dark:text-amber-300">
                                    {{ $inv->no_invoice }}
                                </div>
                                <div class="text-sm font-semibold text-zinc-800 dark:text-zinc-200 mt-1">
                                    {{ $inv->layananPelanggan?->paketLayanan?->nama_paket ?? 'Paket Internet' }}
                                </div>
                                <div class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">
                                    Jatuh Tempo: <span class="font-semibold text-rose-600 dark:text-rose-400">{{ \Carbon\Carbon::parse($inv->tanggal_jatuh_tempo)->translatedFormat('d F Y') }}</span>
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="text-lg font-bold text-zinc-900 dark:text-zinc-100">
                                    {{ $inv->formattedJumlahSetelahPromo() }}
                                </div>
                                @if($inv->promo_id)
                                    <div class="text-[11px] text-emerald-600 dark:text-emerald-400 font-medium">
                                        Hemat promo: Rp {{ number_format($inv->jumlah - $inv->jumlah_setelah_promo, 0, ',', '.') }}
                                    </div>
                                @endif
                            </div>
                        </div>
                        <div class="mt-4 pt-3 border-t border-amber-200/60 dark:border-amber-800/40 flex items-center justify-between">
                            <flux:badge variant="pill" color="amber">Menunggu Pembayaran</flux:badge>
                            <div class="flex items-center gap-2">
                                <flux:button :href="route('portal.invoice.show', $inv)" size="sm" variant="ghost" wire:navigate>
                                    Rincian
                                </flux:button>
                                <flux:button :href="route('portal.invoice.show', $inv)" size="sm" variant="primary" wire:navigate>
                                    Bayar Sekarang &rarr;
                                </flux:button>
                            </div>
                        </div>
                    </flux:card>
                @endforeach
            </div>
        </div>
    @endif

    <!-- Layanan Internet Aktif -->
    <div class="space-y-3">
        <h2 class="text-sm font-bold text-zinc-900 dark:text-zinc-100 flex items-center gap-2">
            <flux:icon icon="signal" class="size-4 text-indigo-500" />
            Layanan Internet Saya
        </h2>

        @if($layanans->isEmpty())
            <flux:card class="p-8 text-center text-zinc-500">
                Belum ada layanan internet terdaftar untuk akun ini.
            </flux:card>
        @else
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach($layanans as $layanan)
                    @php
                        $expired = $layanan->tanggal_expired ? \Carbon\Carbon::parse($layanan->tanggal_expired) : null;
                        $daysLeft = $expired ? (int) now()->diffInDays($expired, false) : null;
                    @endphp
                    <flux:card class="p-5 flex flex-col justify-between">
                        <div>
                            <div class="flex items-center justify-between mb-3">
                                <flux:badge variant="pill" :color="$layanan->statusBadgeColor()">
                                    {{ $layanan->statusBadgeLabel() }}
                                </flux:badge>
                                <span class="font-mono text-xs text-zinc-400">{{ $layanan->site_id }}</span>
                            </div>

                            <div class="font-bold text-base text-zinc-900 dark:text-zinc-100">
                                {{ $layanan->paketLayanan?->nama_paket }}
                            </div>
                            <div class="text-xs text-indigo-600 dark:text-indigo-400 font-medium mt-0.5">
                                {{ $layanan->paketLayanan?->profilBandwidth?->nama_bandwidth ?? 'Kecepatan Standar' }}
                                ({{ $layanan->paketLayanan?->profilBandwidth?->max_limit_rx ?? 0 }}M RX / {{ $layanan->paketLayanan?->profilBandwidth?->max_limit_tx ?? 0 }}M TX)
                            </div>

                            <div class="mt-4 space-y-1.5 text-xs text-zinc-500 dark:text-zinc-400 bg-zinc-50 dark:bg-zinc-800/60 p-3 rounded-xl">
                                <div class="flex justify-between">
                                    <span>Username PPP:</span>
                                    <span class="font-mono font-semibold text-zinc-700 dark:text-zinc-300">{{ $layanan->ppp_username }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span>Masa Aktif Sampai:</span>
                                    <span class="font-semibold {{ $daysLeft !== null && $daysLeft <= 3 ? 'text-rose-600 dark:text-rose-400' : 'text-zinc-700 dark:text-zinc-300' }}">
                                        {{ $expired ? $expired->translatedFormat('d F Y') : '-' }}
                                    </span>
                                </div>
                                @if($daysLeft !== null)
                                    <div class="flex justify-between">
                                        <span>Sisa Hari:</span>
                                        <span class="font-semibold {{ $daysLeft <= 3 ? 'text-rose-600 dark:text-rose-400 font-bold' : 'text-emerald-600 dark:text-emerald-400' }}">
                                            {{ $daysLeft > 0 ? "{$daysLeft} Hari" : ($daysLeft === 0 ? 'Hari Ini' : 'Lewat ' . abs($daysLeft) . ' Hari') }}
                                        </span>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </flux:card>
                @endforeach
            </div>
        @endif
    </div>

    <!-- Riwayat Pembayaran Terakhir -->
    <div class="space-y-3">
        <div class="flex items-center justify-between">
            <h2 class="text-sm font-bold text-zinc-900 dark:text-zinc-100 flex items-center gap-2">
                <flux:icon icon="check-circle" class="size-4 text-emerald-500" />
                Riwayat Pembayaran Terakhir
            </h2>
            <a href="{{ route('portal.invoice.index') }}" wire:navigate class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline font-medium">
                Lihat Semua &rarr;
            </a>
        </div>

        @if($recentPaidInvoices->isEmpty())
            <flux:card class="p-6 text-center text-xs text-zinc-500">
                Belum ada riwayat invoice lunas.
            </flux:card>
        @else
            <flux:card class="p-0 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-xs text-left">
                        <thead class="bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-300 font-medium">
                            <tr>
                                <th class="px-4 py-3">No. Invoice</th>
                                <th class="px-4 py-3">Layanan</th>
                                <th class="px-4 py-3">Tanggal Lunas</th>
                                <th class="px-4 py-3">Metode</th>
                                <th class="px-4 py-3 text-right">Jumlah</th>
                                <th class="px-4 py-3 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                            @foreach($recentPaidInvoices as $paidInv)
                                <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/40">
                                    <td class="px-4 py-3 font-mono font-semibold text-zinc-900 dark:text-zinc-100">
                                        {{ $paidInv->no_invoice }}
                                    </td>
                                    <td class="px-4 py-3">
                                        {{ $paidInv->layananPelanggan?->paketLayanan?->nama_paket ?? '-' }}
                                    </td>
                                    <td class="px-4 py-3 text-zinc-500">
                                        {{ $paidInv->tanggal_lunas ? \Carbon\Carbon::parse($paidInv->tanggal_lunas)->translatedFormat('d M Y') : '-' }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <flux:badge size="sm" variant="pill" color="green">
                                            {{ $paidInv->metode_pembayaran?->label() ?? 'Lunas' }}
                                        </flux:badge>
                                    </td>
                                    <td class="px-4 py-3 text-right font-semibold text-zinc-900 dark:text-zinc-100">
                                        {{ $paidInv->formattedJumlahSetelahPromo() }}
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <flux:button :href="route('portal.invoice.show', $paidInv)" size="xs" variant="ghost" wire:navigate>
                                            Lihat
                                        </flux:button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </flux:card>
        @endif
    </div>
</div>
