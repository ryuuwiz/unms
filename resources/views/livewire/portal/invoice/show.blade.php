<div class="max-w-3xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <a href="{{ route('portal.invoice.index') }}" wire:navigate class="text-xs font-semibold text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200 flex items-center gap-1">
            &larr; Kembali ke Daftar Tagihan
        </a>

        <div class="flex items-center gap-2">
            @if($invoice->isLunas())
                <flux:button :href="route('portal.invoice.cetak', $invoice)" size="sm" variant="ghost" target="_blank" icon="arrow-down-tray">
                    Unduh Bukti Bayar (PDF)
                </flux:button>
            @elseif($invoice->isMenungguPembayaran())
                <flux:button wire:click="sinkronkanStatus" wire:loading.attr="disabled" size="sm" variant="subtle" icon="arrow-path">
                    <span wire:loading.remove wire:target="sinkronkanStatus">Cek Status</span>
                    <span wire:loading wire:target="sinkronkanStatus" class="animate-pulse">Mengecek...</span>
                </flux:button>
                <flux:button wire:click="bayar" wire:loading.attr="disabled" size="sm" variant="primary" class="bg-indigo-600 text-white">
                    <span wire:loading.remove wire:target="bayar" class="flex items-center gap-1.5">
                        <flux:icon icon="credit-card" class="size-4" />
                        Bayar Sekarang
                    </span>
                    <span wire:loading wire:target="bayar" class="flex items-center gap-1.5">
                        <flux:icon icon="arrow-path" class="size-4 animate-spin" />
                        Membuka Halaman Pembayaran...
                    </span>
                </flux:button>
            @endif
        </div>
    </div>

    <!-- Invoice Header Card -->
    <flux:card class="p-6 space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-zinc-100 dark:border-zinc-800 pb-5">
            <div>
                <span class="text-xs font-semibold text-indigo-600 dark:text-indigo-400 uppercase tracking-wider">Invoice Tagihan Internet</span>
                <h1 class="text-2xl font-bold font-mono text-zinc-900 dark:text-zinc-100 mt-0.5">{{ $invoice->no_invoice }}</h1>
                <div class="text-xs text-zinc-500 mt-1">
                    Terbit: {{ \Carbon\Carbon::parse($invoice->tanggal_terbit)->translatedFormat('d F Y') }}
                </div>
            </div>
            <div class="text-left sm:text-right">
                <flux:badge size="lg" variant="pill" :color="$invoice->status->color()">
                    {{ $invoice->status->label() }}
                </flux:badge>
                <div class="text-xs text-zinc-500 mt-1.5">
                    Jatuh Tempo: <strong class="{{ $invoice->isMenungguPembayaran() ? 'text-rose-600 dark:text-rose-400' : '' }}">{{ \Carbon\Carbon::parse($invoice->tanggal_jatuh_tempo)->translatedFormat('d F Y') }}</strong>
                </div>
            </div>
        </div>

        <!-- Info Pelanggan & Layanan -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 text-xs">
            <div class="space-y-1">
                <span class="text-zinc-400 font-semibold uppercase tracking-wider">Ditujukan Kepada:</span>
                <div class="font-bold text-sm text-zinc-900 dark:text-zinc-100">{{ $invoice->pelanggan->namaLengkap() }}</div>
                <div class="text-zinc-600 dark:text-zinc-400">No. Reg: {{ $invoice->pelanggan->no_reg }}</div>
                <div class="text-zinc-600 dark:text-zinc-400">{{ $invoice->pelanggan->alamat_lengkap }}</div>
                <div class="text-zinc-600 dark:text-zinc-400">{{ $invoice->pelanggan->no_hp }}</div>
            </div>

            <div class="space-y-1">
                <span class="text-zinc-400 font-semibold uppercase tracking-wider">Rincian Layanan:</span>
                <div class="font-bold text-sm text-zinc-900 dark:text-zinc-100">{{ $invoice->layananPelanggan?->paketLayanan?->nama_paket ?? 'Paket Internet' }}</div>
                <div class="text-zinc-600 dark:text-zinc-400">Site ID: <span class="font-mono">{{ $invoice->layananPelanggan?->site_id }}</span></div>
                <div class="text-zinc-600 dark:text-zinc-400">Username PPP: <span class="font-mono">{{ $invoice->layananPelanggan?->ppp_username }}</span></div>
                <div class="text-zinc-600 dark:text-zinc-400">Bandwidth: {{ $invoice->layananPelanggan?->paketLayanan?->profilBandwidth?->nama_bandwidth ?? '-' }}</div>
            </div>
        </div>

        <!-- Tabel Rincian Biaya -->
        <div class="border border-zinc-200 dark:border-zinc-800 rounded-xl overflow-hidden text-xs">
            <table class="w-full text-left">
                <thead class="bg-zinc-100 dark:bg-zinc-800/80 text-zinc-600 dark:text-zinc-300 font-medium">
                    <tr>
                        <th class="px-4 py-3">Deskripsi Tagihan</th>
                        <th class="px-4 py-3 text-right">Jumlah</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    <tr>
                        <td class="px-4 py-3">
                            <div class="font-semibold text-zinc-900 dark:text-zinc-100">
                                Biaya Langganan Internet &bull; {{ $invoice->layananPelanggan?->paketLayanan?->nama_paket }}
                            </div>
                            <div class="text-zinc-500 text-[11px]">
                                Masa aktif: {{ $invoice->layananPelanggan?->paketLayanan?->masa_aktif_nilai }} {{ $invoice->layananPelanggan?->paketLayanan?->masa_aktif_satuan?->label() }}
                            </div>
                        </td>
                        <td class="px-4 py-3 text-right font-medium">
                            {{ $invoice->formattedJumlah() }}
                        </td>
                    </tr>
                    @if($invoice->promo_id && $invoice->promo)
                        <tr class="bg-emerald-50/50 dark:bg-emerald-950/20 text-emerald-700 dark:text-emerald-400">
                            <td class="px-4 py-3">
                                <div class="font-semibold flex items-center gap-1">
                                    <flux:icon icon="tag" class="size-3.5" />
                                    Promo: {{ $invoice->promo->nama_promo }} ({{ $invoice->promo->kode_promo }})
                                </div>
                            </td>
                            <td class="px-4 py-3 text-right font-semibold">
                                - Rp {{ number_format($invoice->jumlah - $invoice->jumlah_setelah_promo, 0, ',', '.') }}
                            </td>
                        </tr>
                    @endif
                </tbody>
                <tfoot class="bg-zinc-50 dark:bg-zinc-800/40 border-t border-zinc-200 dark:border-zinc-800 font-bold text-sm">
                    <tr>
                        <td class="px-4 py-3 text-zinc-800 dark:text-zinc-200">Total Tagihan:</td>
                        <td class="px-4 py-3 text-right text-indigo-600 dark:text-indigo-400 text-base">
                            {{ $invoice->formattedJumlahSetelahPromo() }}
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <!-- Riwayat Pembayaran Tercatat -->
        @if($invoice->pembayarans->isNotEmpty())
            <div class="pt-4 border-t border-zinc-100 dark:border-zinc-800 space-y-2">
                <h3 class="text-xs font-bold uppercase tracking-wider text-emerald-600 dark:text-emerald-400 flex items-center gap-1.5">
                    <flux:icon icon="check-circle" class="size-4" />
                    Informasi Pembayaran Lunas
                </h3>
                @foreach($invoice->pembayarans as $pembayaran)
                    <div class="p-3 bg-emerald-50/60 dark:bg-emerald-950/30 border border-emerald-200/60 dark:border-emerald-800/50 rounded-xl text-xs flex justify-between items-center">
                        <div>
                            <div class="font-semibold text-emerald-900 dark:text-emerald-200">
                                Dibayar pada {{ \Carbon\Carbon::parse($pembayaran->dibayar_pada)->translatedFormat('d F Y, H:i') }} WIB
                            </div>
                            <div class="text-[11px] text-zinc-500 dark:text-zinc-400 mt-0.5">
                                Metode: {{ $pembayaran->metode->label() }} &bull; Ref: <span class="font-mono">{{ $pembayaran->referensi_transaksi ?? '-' }}</span>
                            </div>
                        </div>
                        <div class="font-bold text-sm text-emerald-700 dark:text-emerald-300">
                            Rp {{ number_format((float) $pembayaran->jumlah_dibayar, 0, ',', '.') }}
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </flux:card>
</div>
