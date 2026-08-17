<div>
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-zinc-900 dark:text-white">Laporan Keuangan & Penagihan</h1>
            <p class="text-sm text-zinc-500 dark:text-zinc-400 mt-1">Rekapitulasi arus tagihan piutang, realisasi pembayaran, dan performa penagihan.</p>
        </div>
        <div class="flex items-center gap-2">
            @can('laporan.ekspor')
                <flux:button wire:click="exportExcel" variant="primary" icon="arrow-down-tray">
                    Ekspor ke Excel (.xlsx)
                </flux:button>
            @endcan
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="bg-white dark:bg-zinc-800 p-4 rounded-xl border border-zinc-200 dark:border-zinc-700 shadow-sm mb-6 flex flex-col md:flex-row gap-4 items-end justify-between">
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 w-full md:w-auto">
            <div>
                <flux:input type="date" wire:model.live="startDate" label="Dari Tanggal Terbit" />
            </div>
            <div>
                <flux:input type="date" wire:model.live="endDate" label="Sampai Tanggal Terbit" />
            </div>
            <div>
                <flux:select wire:model.live="status" label="Filter Status">
                    <flux:select.option value="">Semua Status</flux:select.option>
                    @foreach($statuses as $st)
                        <flux:select.option value="{{ $st->value }}">{{ $st->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
        </div>
    </div>

    <!-- Metrics Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-6 mb-6">
        <div class="bg-white dark:bg-zinc-800 p-6 rounded-xl border border-zinc-200 dark:border-zinc-700 shadow-sm">
            <div class="text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">
                Total Tagihan Terbit
            </div>
            <div class="text-2xl font-bold text-zinc-900 dark:text-white mt-2">
                Rp {{ number_format($totalTagihan, 0, ',', '.') }}
            </div>
            <div class="text-xs text-zinc-500 mt-1">
                {{ $countTotal }} total tagihan invoice
            </div>
        </div>

        <div class="bg-emerald-50 dark:bg-emerald-950/30 p-6 rounded-xl border border-emerald-200 dark:border-emerald-800 shadow-sm">
            <div class="text-xs font-semibold text-emerald-700 dark:text-emerald-300 uppercase tracking-wider">
                Realisasi Pembayaran (Kas Masuk)
            </div>
            <div class="text-2xl font-bold text-emerald-600 dark:text-emerald-400 mt-2">
                Rp {{ number_format($totalLunas, 0, ',', '.') }}
            </div>
            <div class="text-xs text-emerald-600 dark:text-emerald-400 mt-1">
                {{ $countLunas }} tagihan lunas
            </div>
        </div>

        <div class="bg-amber-50 dark:bg-amber-950/30 p-6 rounded-xl border border-amber-200 dark:border-amber-800 shadow-sm">
            <div class="text-xs font-semibold text-amber-700 dark:text-amber-300 uppercase tracking-wider">
                Sisa Piutang Berjalan
            </div>
            <div class="text-2xl font-bold text-amber-600 dark:text-amber-400 mt-2">
                Rp {{ number_format($totalPiutang, 0, ',', '.') }}
            </div>
            <div class="text-xs text-amber-600 dark:text-amber-400 mt-1">
                {{ $countMenunggu }} menunggu • {{ $countKadaluarsa }} kadaluarsa
            </div>
        </div>
    </div>

    <!-- Preview Table (Top 50) -->
    <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 shadow-sm overflow-hidden">
        <div class="p-4 bg-zinc-50 dark:bg-zinc-900/50 border-b border-zinc-200 dark:border-zinc-700 font-semibold text-zinc-900 dark:text-white flex justify-between items-center">
            <span>Daftar Tagihan Terpilih (Maks. 50 Data Terkini)</span>
            <span class="text-xs font-normal text-zinc-500">Gunakan tombol ekspor untuk unduh seluruh data</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-zinc-50 dark:bg-zinc-900/50 border-b border-zinc-200 dark:border-zinc-700 text-zinc-600 dark:text-zinc-400 font-medium">
                    <tr>
                        <th class="px-4 py-3">Tanggal Terbit</th>
                        <th class="px-4 py-3">No. Invoice</th>
                        <th class="px-4 py-3">Pelanggan</th>
                        <th class="px-4 py-3">Paket</th>
                        <th class="px-4 py-3 text-right">Nominal</th>
                        <th class="px-4 py-3 text-center">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse($invoices as $inv)
                        <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-700/30 transition-colors">
                            <td class="px-4 py-3 text-zinc-600 dark:text-zinc-400">
                                {{ $inv->tanggal_terbit->format('d/m/Y') }}
                            </td>
                            <td class="px-4 py-3 font-semibold font-mono">
                                <a href="{{ route('invoice.show', $inv) }}" class="text-blue-600 dark:text-blue-400 hover:underline">
                                    {{ $inv->no_invoice }}
                                </a>
                            </td>
                            <td class="px-4 py-3">
                                <div class="font-medium text-zinc-900 dark:text-white">{{ $inv->pelanggan?->nama_lengkap ?? '-' }}</div>
                                <div class="text-xs text-zinc-500">{{ $inv->pelanggan?->no_reg }}</div>
                            </td>
                            <td class="px-4 py-3 text-zinc-700 dark:text-zinc-300">
                                {{ $inv->layananPelanggan?->paketLayanan?->nama_paket ?? '-' }}
                            </td>
                            <td class="px-4 py-3 text-right font-bold text-zinc-900 dark:text-white">
                                {{ $inv->formattedJumlahSetelahPromo() }}
                            </td>
                            <td class="px-4 py-3 text-center">
                                <flux:badge size="sm" :color="$inv->status->color()">
                                    {{ $inv->status->label() }}
                                </flux:badge>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-zinc-500 dark:text-zinc-400">
                                Tidak ada data yang sesuai dengan filter.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
