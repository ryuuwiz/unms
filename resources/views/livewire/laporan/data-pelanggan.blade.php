<div>
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-zinc-900 dark:text-white">Laporan Data Pelanggan</h1>
            <p class="text-sm text-zinc-500 dark:text-zinc-400 mt-1">Data pelanggan per paket layanan dan daftar client yang masa aktifnya sudah expired.</p>
        </div>
        <div class="flex items-center gap-2">
            @can('laporan.ekspor')
                <flux:button wire:click="exportPerPaket" variant="primary" icon="arrow-down-tray">
                    Ekspor Per Paket
                </flux:button>
                <flux:button wire:click="exportExpired" variant="danger" icon="arrow-down-tray">
                    Ekspor Client Expired
                </flux:button>
            @endcan
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="bg-white dark:bg-zinc-800 p-4 rounded-xl border border-zinc-200 dark:border-zinc-700 shadow-sm mb-6 flex flex-col md:flex-row gap-4 items-end justify-between">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 w-full md:w-auto">
            <div>
                <flux:select wire:model.live="paketLayananId" label="Filter Paket Layanan">
                    <flux:select.option value="">Semua Paket</flux:select.option>
                    @foreach($paketList as $paket)
                        <flux:select.option value="{{ $paket->id }}">{{ $paket->nama_paket }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
            <div>
                <flux:select wire:model.live="status" label="Filter Status Layanan">
                    <flux:select.option value="">Semua Status</flux:select.option>
                    @foreach($statuses as $st)
                        <flux:select.option value="{{ $st->value }}">{{ $st->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
        </div>
    </div>

    <!-- Metrics Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 mb-6">
        <div class="bg-white dark:bg-zinc-800 p-6 rounded-xl border border-zinc-200 dark:border-zinc-700 shadow-sm">
            <div class="text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">
                Total Layanan Sesuai Filter
            </div>
            <div class="text-2xl font-bold text-zinc-900 dark:text-white mt-2">
                {{ number_format($totalLayanan) }}
            </div>
        </div>

        <div class="bg-rose-50 dark:bg-rose-950/30 p-6 rounded-xl border border-rose-200 dark:border-rose-800 shadow-sm">
            <div class="text-xs font-semibold text-rose-700 dark:text-rose-300 uppercase tracking-wider">
                Client Expired (Aktif/Suspend, Sudah Jatuh Tempo)
            </div>
            <div class="text-2xl font-bold text-rose-600 dark:text-rose-400 mt-2">
                {{ number_format($totalExpired) }}
            </div>
        </div>
    </div>

    <!-- Preview Table (Top 50) -->
    <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 shadow-sm overflow-hidden">
        <div class="p-4 bg-zinc-50 dark:bg-zinc-900/50 border-b border-zinc-200 dark:border-zinc-700 font-semibold text-zinc-900 dark:text-white flex justify-between items-center">
            <span>Daftar Layanan Terpilih (Maks. 50 Data Terkini)</span>
            <span class="text-xs font-normal text-zinc-500">Gunakan tombol ekspor untuk unduh seluruh data</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-zinc-50 dark:bg-zinc-900/50 border-b border-zinc-200 dark:border-zinc-700 text-zinc-600 dark:text-zinc-400 font-medium">
                    <tr>
                        <th class="px-4 py-3">No. Registrasi</th>
                        <th class="px-4 py-3">Nama Pelanggan</th>
                        <th class="px-4 py-3">Paket</th>
                        <th class="px-4 py-3 text-center">Status</th>
                        <th class="px-4 py-3">Tanggal Expired</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse($layanans as $layanan)
                        <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-700/30 transition-colors">
                            <td class="px-4 py-3 font-mono">
                                <a href="{{ route('pelanggan.show', $layanan->pelanggan_id) }}" class="text-blue-600 dark:text-blue-400 hover:underline">
                                    {{ $layanan->pelanggan?->no_reg ?? '-' }}
                                </a>
                            </td>
                            <td class="px-4 py-3">
                                <div class="font-medium text-zinc-900 dark:text-white">{{ $layanan->pelanggan?->nama_lengkap ?? '-' }}</div>
                            </td>
                            <td class="px-4 py-3 text-zinc-700 dark:text-zinc-300">
                                {{ $layanan->paketLayanan?->nama_paket ?? '-' }}
                            </td>
                            <td class="px-4 py-3 text-center">
                                <flux:badge size="sm" :color="$layanan->statusBadgeColor()">
                                    {{ $layanan->statusBadgeLabel() }}
                                </flux:badge>
                            </td>
                            <td class="px-4 py-3 text-zinc-600 dark:text-zinc-400">
                                {{ $layanan->tanggal_expired?->format('d/m/Y') ?? '-' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-8 text-center text-zinc-500 dark:text-zinc-400">
                                Tidak ada data yang sesuai dengan filter.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
