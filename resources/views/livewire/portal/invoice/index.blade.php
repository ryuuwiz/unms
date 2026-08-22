<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-zinc-900 dark:text-zinc-100">Tagihan & Riwayat Pembayaran</h1>
            <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">Daftar seluruh invoice tagihan layanan internet Anda</p>
        </div>

        <div class="flex items-center gap-1.5 bg-zinc-100 dark:bg-zinc-800/80 p-1 rounded-xl">
            <button
                wire:click="setStatusFilter('all')"
                type="button"
                class="px-3 py-1.5 text-xs font-semibold rounded-lg transition-colors {{ $status === 'all' ? 'bg-white dark:bg-zinc-700 shadow-xs text-zinc-900 dark:text-zinc-100' : 'text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200' }}"
            >
                Semua
            </button>
            <button
                wire:click="setStatusFilter('menunggu_pembayaran')"
                type="button"
                class="px-3 py-1.5 text-xs font-semibold rounded-lg transition-colors {{ $status === 'menunggu_pembayaran' ? 'bg-white dark:bg-zinc-700 shadow-xs text-amber-600 dark:text-amber-400' : 'text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200' }}"
            >
                Belum Lunas
            </button>
            <button
                wire:click="setStatusFilter('lunas')"
                type="button"
                class="px-3 py-1.5 text-xs font-semibold rounded-lg transition-colors {{ $status === 'lunas' ? 'bg-white dark:bg-zinc-700 shadow-xs text-emerald-600 dark:text-emerald-400' : 'text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200' }}"
            >
                Lunas
            </button>
        </div>
    </div>

    @if($invoices->isEmpty())
        <flux:card class="p-12 text-center text-zinc-500 space-y-2">
            <flux:icon icon="document-text" class="size-10 mx-auto text-zinc-400" />
            <div class="text-sm font-medium">Tidak ada tagihan yang sesuai dengan filter.</div>
        </flux:card>
    @else
        <div class="space-y-3">
            @foreach($invoices as $invoice)
                <flux:card class="p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-4 hover:border-zinc-300 dark:hover:border-zinc-700 transition-colors">
                    <div class="space-y-1">
                        <div class="flex items-center gap-2">
                            <span class="font-mono font-bold text-sm text-zinc-900 dark:text-zinc-100">{{ $invoice->no_invoice }}</span>
                            <flux:badge size="sm" variant="pill" :color="$invoice->status->color()">
                                {{ $invoice->status->label() }}
                            </flux:badge>
                        </div>
                        <div class="text-xs font-medium text-zinc-700 dark:text-zinc-300">
                            {{ $invoice->layananPelanggan?->paketLayanan?->nama_paket }} (Site ID: {{ $invoice->layananPelanggan?->site_id }})
                        </div>
                        <div class="text-[11px] text-zinc-500 flex items-center gap-3">
                            <span>Terbit: {{ \Carbon\Carbon::parse($invoice->tanggal_terbit)->translatedFormat('d M Y') }}</span>
                            <span>&bull;</span>
                            <span>Jatuh Tempo: <strong class="{{ $invoice->isMenungguPembayaran() ? 'text-rose-600 dark:text-rose-400' : '' }}">{{ \Carbon\Carbon::parse($invoice->tanggal_jatuh_tempo)->translatedFormat('d M Y') }}</strong></span>
                        </div>
                    </div>

                    <div class="flex sm:flex-col items-center sm:items-end justify-between sm:justify-center border-t sm:border-t-0 pt-3 sm:pt-0 border-zinc-100 dark:border-zinc-800 gap-2">
                        <div class="text-right">
                            <div class="text-base font-bold text-zinc-900 dark:text-zinc-100">
                                {{ $invoice->formattedJumlahSetelahPromo() }}
                            </div>
                            @if($invoice->promo_id)
                                <div class="text-[10px] text-emerald-600 dark:text-emerald-400">
                                    Diskon promo terpasang
                                </div>
                            @endif
                        </div>

                        <div class="flex items-center gap-2">
                            <flux:button :href="route('portal.invoice.show', $invoice)" size="sm" variant="ghost" wire:navigate>
                                Rincian
                            </flux:button>
                            @if($invoice->isMenungguPembayaran())
                                <flux:button :href="route('portal.invoice.show', $invoice)" size="sm" variant="primary" class="bg-indigo-600 text-white font-semibold" wire:navigate>
                                    Bayar Sekarang &rarr;
                                </flux:button>
                            @elseif($invoice->isLunas())
                                <flux:button :href="route('invoice.cetak', $invoice)" size="sm" variant="ghost" target="_blank" icon="arrow-down-tray">
                                    PDF
                                </flux:button>
                            @endif
                        </div>
                    </div>
                </flux:card>
            @endforeach

            <div class="mt-4">
                {{ $invoices->links() }}
            </div>
        </div>
    @endif
</div>
