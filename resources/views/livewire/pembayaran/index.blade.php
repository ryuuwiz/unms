<div>
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-zinc-900 dark:text-white">Riwayat Penerimaan Pembayaran</h1>
            <p class="text-sm text-zinc-500 dark:text-zinc-400 mt-1">Daftar transaksi pembayaran masuk atas tagihan langganan internet.</p>
        </div>
    </div>

    <!-- Filter & Search Bar -->
    <div class="bg-white dark:bg-zinc-800 p-4 rounded-xl border border-zinc-200 dark:border-zinc-700 shadow-sm mb-6 flex flex-col sm:flex-row gap-4 justify-between items-center">
        <div class="w-full sm:w-80">
            <flux:input
                wire:model.live.debounce.300ms="search"
                icon="magnifying-glass"
                placeholder="Cari No. Invoice, Pelanggan, Ref..."
                clearable
            />
        </div>
        <div class="w-full sm:w-56">
            <flux:select wire:model.live="metode" placeholder="Semua Metode">
                <flux:select.option value="">Semua Metode</flux:select.option>
                @foreach($metodes as $m)
                    <flux:select.option value="{{ $m->value }}">{{ $m->label() }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>
    </div>

    <!-- Table -->
    <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-zinc-50 dark:bg-zinc-900/50 border-b border-zinc-200 dark:border-zinc-700 text-zinc-600 dark:text-zinc-400 font-medium">
                    <tr>
                        <th class="px-4 py-3">Waktu Bayar</th>
                        <th class="px-4 py-3">No. Invoice</th>
                        <th class="px-4 py-3">Pelanggan</th>
                        <th class="px-4 py-3">Metode</th>
                        <th class="px-4 py-3 text-right">Jumlah Dibayar</th>
                        <th class="px-4 py-3">Dicatat Oleh</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse($pembayarans as $bayar)
                        <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-700/30 transition-colors">
                            <td class="px-4 py-3 text-zinc-700 dark:text-zinc-300">
                                <div class="font-medium">{{ $bayar->dibayar_pada->format('d M Y') }}</div>
                                <div class="text-xs text-zinc-500">{{ $bayar->dibayar_pada->format('H:i') }} WIB</div>
                            </td>
                            <td class="px-4 py-3 font-semibold text-zinc-900 dark:text-white">
                                <a href="{{ route('invoice.show', $bayar->invoice_id) }}" wire:navigate class="hover:text-blue-600 dark:hover:text-blue-400">
                                    {{ $bayar->invoice?->no_invoice }}
                                </a>
                            </td>
                            <td class="px-4 py-3">
                                <div class="font-medium text-zinc-900 dark:text-white">{{ $bayar->invoice?->pelanggan?->nama_lengkap ?? '-' }}</div>
                                <div class="text-xs text-zinc-500">{{ $bayar->invoice?->pelanggan?->no_reg }}</div>
                            </td>
                            <td class="px-4 py-3">
                                <flux:badge size="sm" :color="$bayar->metode->color()">
                                    {{ $bayar->metode->label() }}
                                </flux:badge>
                                @if($bayar->referensi_transaksi)
                                    <div class="text-xs font-mono text-zinc-500 mt-0.5">Ref: {{ $bayar->referensi_transaksi }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right font-bold text-emerald-600 dark:text-emerald-400">
                                {{ $bayar->formattedJumlah() }}
                            </td>
                            <td class="px-4 py-3 text-xs text-zinc-600 dark:text-zinc-400">
                                {{ $bayar->dicatatOleh?->name ?? 'Sistem / Otomatis' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-zinc-500 dark:text-zinc-400">
                                Belum ada riwayat pembayaran yang tercatat.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($pembayarans->hasPages())
            <div class="p-4 border-t border-zinc-200 dark:border-zinc-700">
                {{ $pembayarans->links() }}
            </div>
        @endif
    </div>
</div>
