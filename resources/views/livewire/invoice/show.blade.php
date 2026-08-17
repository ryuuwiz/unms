<div class="max-w-5xl mx-auto space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-bold text-zinc-900 dark:text-white">{{ $invoice->no_invoice }}</h1>
                <flux:badge size="md" :color="$invoice->status->color()">
                    {{ $invoice->status->label() }}
                </flux:badge>
            </div>
            <p class="text-sm text-zinc-500 dark:text-zinc-400 mt-1">
                Diterbitkan pada {{ $invoice->tanggal_terbit->format('d M Y') }} • Jatuh Tempo: {{ $invoice->tanggal_jatuh_tempo->format('d M Y') }}
            </p>
        </div>

        <div class="flex items-center gap-2">
            <flux:button href="{{ route('invoice.index') }}" variant="subtle" icon="arrow-left" wire:navigate>
                Kembali
            </flux:button>
            @can('invoice.cetak')
                <flux:button href="{{ route('invoice.cetak', $invoice) }}" target="_blank" variant="subtle" icon="printer">
                    Cetak PDF
                </flux:button>
            @endcan
            @can('pembayaran.catat')
                @if(!$invoice->isLunas())
                    <flux:button wire:click="openBayarModal" variant="primary" icon="banknotes">
                        Catat Pembayaran
                    </flux:button>
                @endif
            @endcan
        </div>
    </div>

    <!-- Info Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Customer Info -->
        <div class="bg-white dark:bg-zinc-800 p-6 rounded-xl border border-zinc-200 dark:border-zinc-700 shadow-sm space-y-3">
            <h3 class="text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">
                Informasi Pelanggan
            </h3>
            <div>
                <a href="{{ route('pelanggan.show', $invoice->pelanggan_id) }}" wire:navigate class="text-lg font-bold text-blue-600 dark:text-blue-400 hover:underline">
                    {{ $invoice->pelanggan?->nama_lengkap }}
                </a>
                <div class="text-sm text-zinc-600 dark:text-zinc-300 mt-1">
                    No. Registrasi: <span class="font-mono font-medium">{{ $invoice->pelanggan?->no_reg }}</span>
                </div>
                <div class="text-sm text-zinc-600 dark:text-zinc-300">
                    No. Handphone: {{ $invoice->pelanggan?->no_hp }}
                </div>
                <div class="text-sm text-zinc-500 mt-2">
                    {{ $invoice->pelanggan?->alamat_lengkap }}
                </div>
            </div>
        </div>

        <!-- Service & Router Info -->
        <div class="bg-white dark:bg-zinc-800 p-6 rounded-xl border border-zinc-200 dark:border-zinc-700 shadow-sm space-y-3">
            <h3 class="text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">
                Layanan Internet & Site
            </h3>
            <div>
                <div class="text-lg font-bold text-zinc-900 dark:text-white">
                    {{ $invoice->layananPelanggan?->paketLayanan?->nama_paket }}
                </div>
                <div class="text-sm text-zinc-600 dark:text-zinc-300 mt-1">
                    Site ID: <span class="font-mono font-medium">{{ $invoice->layananPelanggan?->site_id }}</span>
                </div>
                <div class="text-sm text-zinc-600 dark:text-zinc-300">
                    Username PPP: <span class="font-mono">{{ $invoice->layananPelanggan?->ppp_username }}</span>
                </div>
                <div class="text-sm text-zinc-600 dark:text-zinc-300">
                    Router Gateway: {{ $invoice->layananPelanggan?->router?->nama_router }}
                </div>
                <div class="text-xs text-zinc-500 mt-2">
                    Masa Aktif Layanan Saat Ini: <span class="font-semibold text-zinc-700 dark:text-zinc-300">{{ $invoice->layananPelanggan?->tanggal_expired ? $invoice->layananPelanggan->tanggal_expired->format('d M Y') : 'Belum aktif' }}</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Tagihan Rincian -->
    <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 shadow-sm overflow-hidden">
        <div class="p-4 bg-zinc-50 dark:bg-zinc-900/50 border-b border-zinc-200 dark:border-zinc-700 font-semibold text-zinc-900 dark:text-white">
            Rincian Tagihan
        </div>
        <div class="p-6">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-zinc-200 dark:border-zinc-700 text-zinc-500">
                        <th class="text-left pb-3">Item Layanan</th>
                        <th class="text-center pb-3">Durasi</th>
                        <th class="text-right pb-3">Jumlah (Rp)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    <tr>
                        <td class="py-3">
                            <div class="font-medium text-zinc-900 dark:text-white">
                                Langganan {{ $invoice->layananPelanggan?->paketLayanan?->nama_paket }}
                            </div>
                            <div class="text-xs text-zinc-500">
                                Kecepatan: {{ $invoice->layananPelanggan?->paketLayanan?->profilBandwidth?->nama_bandwidth }}
                            </div>
                        </td>
                        <td class="py-3 text-center text-zinc-600 dark:text-zinc-300">
                            {{ $invoice->layananPelanggan?->paketLayanan?->masa_aktif_nilai }} {{ ucfirst($invoice->layananPelanggan?->paketLayanan?->masa_aktif_satuan?->value) }}
                        </td>
                        <td class="py-3 text-right font-semibold text-zinc-900 dark:text-white">
                            {{ $invoice->formattedJumlah() }}
                        </td>
                    </tr>
                    @if($invoice->promo)
                        <tr>
                            <td class="py-3">
                                <div class="font-medium text-emerald-600 dark:text-emerald-400">
                                    Potongan Kupon Promo ({{ $invoice->promo->kode_promo }})
                                </div>
                                <div class="text-xs text-zinc-500">{{ $invoice->promo->nama_promo }}</div>
                            </td>
                            <td class="py-3 text-center">-</td>
                            <td class="py-3 text-right font-semibold text-emerald-600 dark:text-emerald-400">
                                -Rp {{ number_format((float) ($invoice->jumlah - $invoice->jumlah_setelah_promo), 0, ',', '.') }}
                            </td>
                        </tr>
                    @endif
                </tbody>
                <tfoot>
                    <tr class="border-t-2 border-zinc-900 dark:border-white font-bold text-base">
                        <td colspan="2" class="pt-4 text-right">TOTAL TAGIHAN:</td>
                        <td class="pt-4 text-right text-blue-600 dark:text-blue-400">
                            {{ $invoice->formattedJumlahSetelahPromo() }}
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <!-- Riwayat Pembayaran -->
    <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 shadow-sm overflow-hidden">
        <div class="p-4 bg-zinc-50 dark:bg-zinc-900/50 border-b border-zinc-200 dark:border-zinc-700 font-semibold text-zinc-900 dark:text-white flex justify-between items-center">
            <span>Riwayat Penerimaan Pembayaran</span>
            @if($invoice->isLunas())
                <span class="text-xs font-normal text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-950 px-2 py-1 rounded">
                    Lunas pada {{ $invoice->tanggal_lunas?->format('d M Y') }}
                </span>
            @endif
        </div>
        <div class="p-6">
            @if($invoice->pembayarans->isEmpty())
                <p class="text-sm text-zinc-500 text-center py-4">Belum ada pembayaran yang tercatat untuk invoice ini.</p>
            @else
                <div class="space-y-4">
                    @foreach($invoice->pembayarans as $bayar)
                        <div class="p-4 rounded-lg bg-zinc-50 dark:bg-zinc-900/50 border border-zinc-200 dark:border-zinc-700 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                            <div>
                                <div class="font-bold text-zinc-900 dark:text-white">
                                    {{ $bayar->formattedJumlah() }}
                                </div>
                                <div class="text-xs text-zinc-500 mt-1">
                                    Metode: <span class="font-medium text-zinc-700 dark:text-zinc-300">{{ $bayar->metode->label() }}</span>
                                    @if($bayar->referensi_transaksi)
                                        • Ref: <span class="font-mono">{{ $bayar->referensi_transaksi }}</span>
                                    @endif
                                </div>
                                @if($bayar->catatan)
                                    <div class="text-xs text-zinc-600 dark:text-zinc-400 mt-1 italic">
                                        "{{ $bayar->catatan }}"
                                    </div>
                                @endif
                            </div>
                            <div class="text-xs text-zinc-500 text-right">
                                <div>{{ $bayar->dibayar_pada->format('d M Y H:i') }}</div>
                                <div class="text-zinc-400 mt-1">Dicatat oleh: {{ $bayar->dicatatOleh?->name ?? 'Sistem' }}</div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    <!-- Modal Catat Pembayaran -->
    <flux:modal :open="$showBayarModal" wire:model.self="showBayarModal" class="max-w-lg">
        <form wire:submit="prosesBayar" class="p-6 space-y-4">
            <div class="border-b border-zinc-200 dark:border-zinc-700 pb-3">
                <h3 class="text-lg font-bold text-zinc-900 dark:text-white">Catat Pembayaran Manual</h3>
                <p class="text-xs text-zinc-500 mt-1">Konfirmasi penerimaan pembayaran untuk tagihan <strong>{{ $invoice->no_invoice }}</strong>.</p>
            </div>

            <div class="space-y-4">
                <flux:select wire:model="metode" label="Metode Pembayaran *">
                    <flux:select.option value="manual_admin">Manual (Admin/Kasir)</flux:select.option>
                    <flux:select.option value="transfer">Transfer Bank Langsung</flux:select.option>
                </flux:select>

                <flux:input type="number" wire:model="jumlah_dibayar" label="Jumlah Dibayar (Rp) *" />
                <flux:error name="jumlah_dibayar" />

                <flux:input type="datetime-local" wire:model="dibayar_pada" label="Waktu Pembayaran *" />
                <flux:error name="dibayar_pada" />

                <flux:input wire:model="referensi_transaksi" label="No. Referensi / Bukti Transfer (Opsional)" placeholder="Contoh: TF-BCA-987654..." />

                <flux:textarea wire:model="catatan" label="Catatan Tambahan (Opsional)" rows="2" placeholder="Keterangan..." />
            </div>

            <div class="bg-amber-50 dark:bg-amber-950/40 p-3 rounded-lg border border-amber-200 dark:border-amber-800 text-xs text-amber-800 dark:text-amber-300">
                ⚠️ Mencatat pembayaran akan otomatis mengubah status tagihan menjadi <strong>Lunas</strong> dan memperpanjang masa aktif layanan pelanggan terkait.
            </div>

            <div class="flex justify-end gap-2 pt-2">
                <flux:button wire:click="$set('showBayarModal', false)" variant="subtle">Batal</flux:button>
                <flux:button type="submit" variant="primary" icon="check">Simpan Pembayaran</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
