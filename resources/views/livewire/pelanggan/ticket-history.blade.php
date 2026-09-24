<div>
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-4">
        <div>
            <flux:heading size="lg">Histori Ticket</flux:heading>
            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Seluruh tiket (pemasangan, gangguan, pencabutan, pindah alamat) milik pelanggan ini.</p>
        </div>
        <div class="w-full sm:w-56">
            <flux:select wire:model.live="status" placeholder="Semua Status">
                <flux:select.option value="">Semua Status</flux:select.option>
                @foreach ($statuses as $st)
                    <flux:select.option value="{{ $st->value }}">{{ $st->label() }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>
    </div>

    <div class="rounded-xl border border-zinc-200 bg-white shadow-sm overflow-hidden dark:border-zinc-700 dark:bg-zinc-900">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-zinc-50 dark:bg-zinc-900/50 border-b border-zinc-200 dark:border-zinc-700 text-zinc-600 dark:text-zinc-400 font-medium">
                    <tr>
                        <th class="px-4 py-3">No. Tiket</th>
                        <th class="px-4 py-3">Jenis</th>
                        <th class="px-4 py-3">Prioritas</th>
                        <th class="px-4 py-3">PIC</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Dibuat</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse ($tickets as $tck)
                        <tr wire:key="pelanggan-ticket-{{ $tck->id }}" class="hover:bg-zinc-50 dark:hover:bg-zinc-700/30 transition-colors">
                            <td class="px-4 py-3 font-mono font-semibold">
                                <a href="{{ route('ticket.show', $tck) }}" wire:navigate class="text-blue-600 dark:text-blue-400 hover:underline">
                                    {{ $tck->nomor_ticket }}
                                </a>
                            </td>
                            <td class="px-4 py-3 text-zinc-700 dark:text-zinc-300">
                                {{ $tck->jenis->label() }}
                            </td>
                            <td class="px-4 py-3 text-zinc-700 dark:text-zinc-300">
                                {{ $tck->prioritas->label() }}
                            </td>
                            <td class="px-4 py-3 text-zinc-700 dark:text-zinc-300">
                                {{ $tck->pic?->name ?? '-' }}
                            </td>
                            <td class="px-4 py-3">
                                <flux:badge size="sm" :color="$tck->status->color()">
                                    {{ $tck->status->label() }}
                                </flux:badge>
                            </td>
                            <td class="px-4 py-3 text-zinc-500 dark:text-zinc-400">
                                {{ $tck->created_at?->translatedFormat('d M Y, H:i') ?? '-' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-zinc-500 dark:text-zinc-400">
                                Belum ada tiket untuk pelanggan ini.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($tickets->hasPages())
            <div class="border-t border-zinc-200 p-4 dark:border-zinc-700">
                {{ $tickets->links() }}
            </div>
        @endif
    </div>
</div>
