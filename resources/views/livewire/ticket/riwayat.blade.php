<div>
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-zinc-900 dark:text-white">Riwayat Tiket</h1>
            <p class="text-sm text-zinc-500 dark:text-zinc-400 mt-1">Seluruh perubahan status dan catatan penanganan dari semua tiket, terbaru di atas.</p>
        </div>
    </div>

    <div class="bg-white dark:bg-zinc-800 p-4 rounded-xl border border-zinc-200 dark:border-zinc-700 shadow-sm mb-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 items-end">
        <flux:input wire:model.live.debounce.300ms="search" label="Cari Tiket" placeholder="No. tiket / pelanggan..." icon="magnifying-glass" />

        <flux:select wire:model.live="status" label="Status">
            <flux:select.option value="">Semua Status</flux:select.option>
            @foreach($statuses as $st)
                <flux:select.option value="{{ $st->value }}">{{ $st->label() }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:input type="date" wire:model.live="dari" label="Dari Tanggal" />
        <flux:input type="date" wire:model.live="sampai" label="Sampai Tanggal" />
    </div>

    <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-zinc-50 dark:bg-zinc-900/50 border-b border-zinc-200 dark:border-zinc-700 text-zinc-600 dark:text-zinc-400 font-medium">
                    <tr>
                        <th class="px-4 py-3">Waktu</th>
                        <th class="px-4 py-3">Tiket</th>
                        <th class="px-4 py-3">Perubahan Status</th>
                        <th class="px-4 py-3">Oleh</th>
                        <th class="px-4 py-3">Catatan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse($histori as $entri)
                        <tr wire:key="histori-{{ $entri->id }}" class="hover:bg-zinc-50 dark:hover:bg-zinc-700/30 transition-colors align-top">
                            <td class="px-4 py-3 whitespace-nowrap text-zinc-600 dark:text-zinc-400">{{ $entri->created_at->format('d/m/Y H:i') }}</td>
                            <td class="px-4 py-3">
                                <a href="{{ route('ticket.show', $entri->ticket) }}" class="font-mono font-semibold text-blue-600 dark:text-blue-400 hover:underline" wire:navigate>{{ $entri->ticket->nomor_ticket }}</a>
                                <div class="text-xs text-zinc-500">{{ $entri->ticket->jenis->label() }} · {{ $entri->ticket->pelanggan?->namaLengkap() ?? '-' }}</div>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                @if ($entri->status_lama && $entri->status_lama !== $entri->status_baru)
                                    <flux:badge size="sm" :color="$entri->status_lama->color()">{{ $entri->status_lama->label() }}</flux:badge>
                                    <span class="text-zinc-400">→</span>
                                @endif
                                <flux:badge size="sm" :color="$entri->status_baru->color()">{{ $entri->status_baru->label() }}</flux:badge>
                            </td>
                            <td class="px-4 py-3 text-zinc-700 dark:text-zinc-300">{{ $entri->olehPengguna->name ?? 'Sistem' }}</td>
                            <td class="px-4 py-3 text-zinc-600 dark:text-zinc-400 max-w-md">{{ $entri->catatan ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-8 text-center text-zinc-500 dark:text-zinc-400">Belum ada riwayat yang sesuai filter.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4 border-t border-zinc-200 dark:border-zinc-700">{{ $histori->links() }}</div>
    </div>
</div>
