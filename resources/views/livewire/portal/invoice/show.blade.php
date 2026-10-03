<div class="max-w-3xl mx-auto space-y-6">
    @php($pelangganLogin = auth('pelanggan')->check())
    @if($pelangganLogin)
        <a href="{{ route('portal.invoice.index') }}" wire:navigate class="text-xs font-semibold text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200 inline-flex items-center gap-1">
            &larr; Kembali ke Daftar Tagihan
        </a>
    @endif

    @if($invoice->isDibatalkan())
        <div class="p-4 bg-zinc-100 dark:bg-zinc-800/80 border border-zinc-200 dark:border-zinc-700 rounded-xl text-xs space-y-1">
            <div class="font-bold text-zinc-900 dark:text-zinc-100 flex items-center gap-1.5">
                <flux:icon icon="x-circle" class="size-4 text-zinc-500" />
                Tagihan Ini Telah Dibatalkan
            </div>
            <p class="text-zinc-600 dark:text-zinc-400">
                {{ $invoice->keterangan_hapus ?: 'Tagihan ini telah dibatalkan oleh sistem/administrator dan tidak memerlukan pembayaran.' }}
            </p>
        </div>
    @endif

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

        <!-- Aksi Tagihan: tombol utama selebar layar di ponsel -->
        @if($invoice->isMenungguPembayaran() && $channels->isNotEmpty())
            @if($instruksi)
                @php($channelInstruksi = $channels->firstWhere('kode', $instruksi->channel_detail))
                <div wire:key="instruksi-{{ $instruksi->id }}" class="rounded-xl border border-indigo-200 dark:border-indigo-900 bg-indigo-50/60 dark:bg-indigo-950/30 p-4 space-y-3 text-center"
                    x-data="{ sisa: {{ max(0, now()->diffInSeconds($instruksi->expired_at, false)) }} }"
                    x-init="const t = setInterval(() => { sisa = Math.max(0, sisa - 1); if (sisa === 0) clearInterval(t) }, 1000)">
                    <div class="text-xs font-semibold uppercase tracking-wide text-zinc-500">
                        {{ $instruksi->channel->label() }} &bull; {{ $channelInstruksi?->keterangan ?: strtoupper($instruksi->channel_detail ?? '') }}
                    </div>

                    <div x-show="sisa > 0" class="space-y-3">
                        @if($qrSvg)
                            <div class="mx-auto w-60 rounded-lg bg-white p-2">{!! $qrSvg !!}</div>
                        @elseif($instruksi->nomor_pembayaran && ! str_starts_with($instruksi->nomor_pembayaran, 'http'))
                            <div class="text-xs text-zinc-500">{{ $instruksi->channel === \App\Enums\GatewayChannel::RetailOutlet ? 'Kode Pembayaran' : 'Nomor Virtual Account' }}</div>
                            <div class="flex items-center justify-center gap-2">
                                <span class="font-mono text-2xl font-bold tracking-wider text-zinc-900 dark:text-white">{{ $instruksi->nomor_pembayaran }}</span>
                                <flux:button size="sm" variant="ghost" icon="clipboard" x-on:click="navigator.clipboard.writeText(@js($instruksi->nomor_pembayaran))" aria-label="Salin nomor pembayaran" />
                            </div>
                        @endif
                        @if(str_starts_with((string) ($instruksi->payload_response['Url'] ?? ''), 'https://'))
                            <a href="{{ $instruksi->payload_response['Url'] }}" target="_blank" rel="noopener" class="text-xs text-indigo-600 hover:underline dark:text-indigo-400">Buka instruksi di halaman iPaymu</a>
                        @endif
                        <div class="text-sm text-zinc-600 dark:text-zinc-300">
                            Bayar tepat <strong class="text-zinc-900 dark:text-white">Rp {{ number_format((float) $instruksi->total_tagihan, 0, ',', '.') }}</strong>
                            @if((float) $instruksi->fee_gateway > 0)
                                <span class="text-xs">(termasuk biaya admin Rp {{ number_format((float) $instruksi->fee_gateway, 0, ',', '.') }})</span>
                            @endif
                        </div>
                        <div class="text-xs text-zinc-500">
                            Berlaku sampai {{ $instruksi->expired_at->timezone('Asia/Jakarta')->translatedFormat('d F Y, H:i') }} WIB
                            <span x-show="sisa < 3600" x-text="'(' + Math.floor(sisa / 60) + ':' + String(sisa % 60).padStart(2, '0') + ')'"></span>
                        </div>
                    </div>

                    <div x-show="sisa === 0" x-cloak class="space-y-2">
                        <p class="text-sm text-zinc-600 dark:text-zinc-300">Kode pembayaran ini sudah kedaluwarsa.</p>
                        @if($channelInstruksi)
                            <flux:button size="sm" variant="primary" wire:click="pilihChannel({{ $channelInstruksi->id }})" icon="arrow-path">
                                {{ $instruksi->channel === \App\Enums\GatewayChannel::Qris ? 'Buat QR baru' : 'Buat kode baru' }}
                            </flux:button>
                        @endif
                    </div>
                </div>
            @endif

            <div class="space-y-2">
                <flux:heading size="sm">{{ $instruksi ? 'Ganti metode pembayaran' : 'Pilih metode pembayaran' }}</flux:heading>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                    @foreach($channels as $channel)
                        <button type="button" wire:click="pilihChannel({{ $channel->id }})" wire:loading.attr="disabled"
                            @class([
                                'flex items-center gap-3 rounded-lg border p-3 text-left transition hover:border-indigo-400 disabled:opacity-50',
                                'border-indigo-500 ring-1 ring-indigo-500' => $instruksi?->channel_detail === $channel->kode,
                                'border-zinc-200 dark:border-zinc-700' => $instruksi?->channel_detail !== $channel->kode,
                            ])>
                            @if($channel->icon_url)
                                <img src="{{ $channel->icon_url }}" alt="" class="h-8 w-12 object-contain" loading="lazy">
                            @else
                                <flux:icon icon="credit-card" class="size-6 text-zinc-400" />
                            @endif
                            <span class="flex-1 min-w-0">
                                <span class="block text-sm font-semibold text-zinc-900 dark:text-white">{{ $channel->keterangan ?: strtoupper($channel->kode) }}</span>
                                <span class="block text-xs text-zinc-500">{{ $channel->tipe->label() }} &bull; Biaya admin {{ $channel->labelFee() }}</span>
                            </span>
                        </button>
                    @endforeach
                    {{-- Hosted Invoice iPaymu: semua metode aktif di akun iPaymu, fee mengikuti tarif iPaymu (ADR-0073). --}}
                    <button type="button" wire:click="bayar" wire:loading.attr="disabled"
                        class="flex items-center gap-3 rounded-lg border border-dashed border-zinc-300 dark:border-zinc-600 p-3 text-left transition hover:border-indigo-400 disabled:opacity-50">
                        <flux:icon icon="arrow-top-right-on-square" class="size-6 text-zinc-400" />
                        <span class="flex-1 min-w-0">
                            <span class="block text-sm font-semibold text-zinc-900 dark:text-white">
                                <span wire:loading.remove wire:target="bayar">Metode lain — pilih di halaman iPaymu</span>
                                <span wire:loading wire:target="bayar">Membuka halaman iPaymu...</span>
                            </span>
                            <span class="block text-xs text-zinc-500">Biaya admin mengikuti tarif iPaymu</span>
                        </span>
                    </button>
                </div>
            </div>

            <div class="flex sm:justify-end">
                <flux:button wire:click="cekStatusPembayaran" wire:loading.attr="disabled" variant="subtle" icon="arrow-path" class="w-full sm:w-auto">
                    <span wire:loading.remove wire:target="cekStatusPembayaran">Cek Status Pembayaran</span>
                    <span wire:loading wire:target="cekStatusPembayaran" class="animate-pulse">Mengecek...</span>
                </flux:button>
            </div>
        @elseif($invoice->isMenungguPembayaran())
            <div class="flex flex-col sm:flex-row-reverse sm:items-center gap-2">
                <flux:button wire:click="bayar" wire:loading.attr="disabled" variant="primary" class="w-full sm:w-auto bg-indigo-600 text-white">
                    <span wire:loading.remove wire:target="bayar" class="flex items-center justify-center gap-1.5">
                        <flux:icon icon="credit-card" class="size-4" />
                        Bayar Sekarang
                    </span>
                    <span wire:loading wire:target="bayar" class="flex items-center justify-center gap-1.5">
                        <flux:icon icon="arrow-path" class="size-4 animate-spin" />
                        Membuka Halaman Pembayaran...
                    </span>
                </flux:button>
                <flux:button wire:click="cekStatusPembayaran" wire:loading.attr="disabled" variant="subtle" icon="arrow-path" class="w-full sm:w-auto">
                    <span wire:loading.remove wire:target="cekStatusPembayaran">Cek Status Pembayaran</span>
                    <span wire:loading wire:target="cekStatusPembayaran" class="animate-pulse">Mengecek...</span>
                </flux:button>
            </div>
        @elseif($invoice->isLunas() && $pelangganLogin)
            <div class="flex sm:justify-end">
                <flux:button :href="route('portal.invoice.cetak', $invoice)" variant="ghost" target="_blank" icon="arrow-down-tray" class="w-full sm:w-auto">
                    Unduh Bukti Bayar (PDF)
                </flux:button>
            </div>
        @endif

        <!-- Riwayat Pembayaran Tercatat -->
        @if($invoice->pembayarans->isNotEmpty())
            <div class="pt-4 border-t border-zinc-100 dark:border-zinc-800 space-y-2">
                <h3 class="text-xs font-bold uppercase tracking-wider text-emerald-600 dark:text-emerald-400 flex items-center gap-1.5">
                    <flux:icon icon="check-circle" class="size-4" />
                    Informasi Pembayaran Lunas
                </h3>
                @foreach($invoice->pembayarans as $pembayaran)
                    <div class="p-3 bg-emerald-50/60 dark:bg-emerald-950/30 border border-emerald-200/60 dark:border-emerald-800/50 rounded-xl text-xs flex justify-between items-center gap-3">
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
