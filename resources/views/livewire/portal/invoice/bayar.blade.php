<div class="max-w-2xl mx-auto space-y-6">
    <!-- Header Back -->
    <div class="flex items-center justify-between">
        <a href="{{ route('portal.invoice.show', $invoice) }}" wire:navigate class="text-xs font-semibold text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200 flex items-center gap-1">
            &larr; Kembali ke Rincian Tagihan
        </a>
        <div class="font-mono text-xs font-bold text-zinc-400">
            {{ $invoice->no_invoice }}
        </div>
    </div>

    <!-- Ringkasan Singkat -->
    <flux:card class="p-4 bg-indigo-50/50 dark:bg-indigo-950/30 border-indigo-200/60 dark:border-indigo-800/40 flex justify-between items-center">
        <div>
            <div class="text-xs text-indigo-700 dark:text-indigo-300 font-semibold">Total yang Harus Dibayar:</div>
            <div class="text-xl font-bold text-indigo-950 dark:text-indigo-100 mt-0.5">
                {{ $invoice->formattedJumlahSetelahPromo() }}
            </div>
        </div>
        <div class="text-right text-xs text-zinc-500">
            <div>{{ $invoice->layananPelanggan?->paketLayanan?->nama_paket }}</div>
            <div class="text-[11px] text-zinc-400">Jatuh Tempo: {{ \Carbon\Carbon::parse($invoice->tanggal_jatuh_tempo)->translatedFormat('d M Y') }}</div>
        </div>
    </flux:card>

    <flux:card class="p-6 space-y-6">
        <div>
            <h2 class="text-lg font-bold text-zinc-900 dark:text-zinc-100">Estimasi Biaya per Channel</h2>
            <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">Pilihan channel pembayaran (Virtual Account, QRIS, E-Wallet) tersedia di halaman payment gateway setelah Anda melanjutkan.</p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div class="p-4 rounded-xl border border-zinc-200 dark:border-zinc-800">
                <div class="flex items-center gap-2">
                    <flux:icon icon="building-library" class="size-5 text-indigo-600 dark:text-indigo-400" />
                    <span class="font-bold text-sm text-zinc-900 dark:text-zinc-100">Virtual Account</span>
                </div>
                <p class="text-[11px] text-zinc-500 mt-1">BCA, BNI, BRI, Mandiri, Permata</p>
                <div class="text-[11px] text-zinc-500 mt-2">
                    {{ $feeVa > 0 ? '+ Biaya admin: Rp '.number_format($feeVa, 0, ',', '.') : 'Tanpa biaya admin' }}
                </div>
            </div>

            <div class="p-4 rounded-xl border border-zinc-200 dark:border-zinc-800">
                <div class="flex items-center gap-2">
                    <flux:icon icon="qr-code" class="size-5 text-emerald-600 dark:text-emerald-400" />
                    <span class="font-bold text-sm text-zinc-900 dark:text-zinc-100">QRIS</span>
                </div>
                <p class="text-[11px] text-zinc-500 mt-1">Gopay, OVO, Dana, ShopeePay, m-Banking</p>
                <div class="text-[11px] text-zinc-500 mt-2">
                    {{ $feeQris > 0 ? '+ Biaya admin: Rp '.number_format($feeQris, 0, ',', '.') : 'Tanpa biaya admin' }}
                </div>
            </div>

            <div class="p-4 rounded-xl border border-zinc-200 dark:border-zinc-800">
                <div class="flex items-center gap-2">
                    <flux:icon icon="device-phone-mobile" class="size-5 text-purple-600 dark:text-purple-400" />
                    <span class="font-bold text-sm text-zinc-900 dark:text-zinc-100">E-Wallet</span>
                </div>
                <p class="text-[11px] text-zinc-500 mt-1">OVO, Dana, LinkAja, ShopeePay</p>
                <div class="text-[11px] text-zinc-500 mt-2">Biaya admin sesuai info di halaman pembayaran</div>
            </div>
        </div>

        <div class="pt-2 border-t border-zinc-100 dark:border-zinc-800">
            <flux:button wire:click="lanjutkanPembayaran" wire:loading.attr="disabled" wire:target="lanjutkanPembayaran" variant="primary" class="w-full justify-center bg-indigo-600 hover:bg-indigo-500 text-white font-bold py-2.5 shadow-lg shadow-indigo-600/20">
                <flux:icon icon="arrow-right" class="size-4 mr-1.5" />
                <span wire:loading.remove wire:target="lanjutkanPembayaran">Lanjutkan Pembayaran</span>
                <span wire:loading wire:target="lanjutkanPembayaran">Memuat...</span>
            </flux:button>
        </div>
    </flux:card>
</div>
