<div>
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-zinc-900 dark:text-white">Laporan Data Layanan</h1>
            <p class="text-sm text-zinc-500 dark:text-zinc-400 mt-1">Data layanan pelanggan per paket, termasuk client yang masa aktifnya sudah lewat.</p>
        </div>
        @can('laporan.ekspor')
            <flux:button wire:click="exportExcel" variant="primary" icon="arrow-down-tray">
                Ekspor ke Excel (.xlsx)
            </flux:button>
        @endcan
    </div>

    <div class="bg-white dark:bg-zinc-800 p-4 rounded-xl border border-zinc-200 dark:border-zinc-700 shadow-sm mb-6 grid grid-cols-1 sm:grid-cols-3 gap-4 items-end">
        <flux:select wire:model.live="paketLayananId" label="Paket Layanan">
            <flux:select.option value="">Semua Paket</flux:select.option>
            @foreach($pakets as $paket)
                <flux:select.option value="{{ $paket->id }}">{{ $paket->nama_paket }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="status" label="Status Layanan">
            <flux:select.option value="">Semua Status</flux:select.option>
            @foreach($statuses as $st)
                <flux:select.option value="{{ $st->value }}">{{ $st->label() }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:checkbox wire:model.live="hanyaExpired" label="Hanya client expired (masa aktif sudah lewat)" />
    </div>

    <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 shadow-sm overflow-hidden">
        <div class="p-4 bg-zinc-50 dark:bg-zinc-900/50 border-b border-zinc-200 dark:border-zinc-700 font-semibold text-zinc-900 dark:text-white flex justify-between items-center gap-2">
            <span>{{ number_format($total, 0, ',', '.') }} layanan sesuai filter</span>
            <span class="text-xs font-normal text-zinc-500">Pratinjau maks. 50 data; gunakan ekspor untuk seluruh data</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-zinc-50 dark:bg-zinc-900/50 border-b border-zinc-200 dark:border-zinc-700 text-zinc-600 dark:text-zinc-400 font-medium">
                    <tr>
                        <th class="px-4 py-3">Pelanggan</th>
                        <th class="px-4 py-3">Site ID</th>
                        <th class="px-4 py-3">Paket</th>
                        <th class="px-4 py-3 text-center">Status</th>
                        <th class="px-4 py-3">Tanggal Expired</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse($layanans as $layanan)
                        <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-700/30 transition-colors">
                            <td class="px-4 py-3">
                                @if ($layanan->pelanggan)
                                    <a href="{{ route('pelanggan.show', $layanan->pelanggan) }}" class="font-medium text-blue-600 dark:text-blue-400 hover:underline" wire:navigate>
                                        {{ $layanan->pelanggan->identitasLengkap() }}
                                    </a>
                                @else
                                    -
                                @endif
                            </td>
                            <td class="px-4 py-3 font-mono text-zinc-600 dark:text-zinc-400">{{ $layanan->site_id }}</td>
                            <td class="px-4 py-3 text-zinc-700 dark:text-zinc-300">{{ $layanan->paketLayanan->nama_paket ?? '-' }}</td>
                            <td class="px-4 py-3 text-center">
                                <flux:badge size="sm" :color="$layanan->status->color()">{{ $layanan->status->label() }}</flux:badge>
                            </td>
                            <td class="px-4 py-3 {{ $layanan->isExpired() ? 'text-rose-600 dark:text-rose-400 font-semibold' : 'text-zinc-600 dark:text-zinc-400' }}">
                                {{ $layanan->tanggal_expired?->format('d/m/Y') ?? '-' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-8 text-center text-zinc-500 dark:text-zinc-400">Tidak ada data yang sesuai dengan filter.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
