<div class="space-y-6" @if(($pemindaian['status'] ?? null) === 'berjalan') wire:poll.3s @endif>
    <div>
        <flux:heading size="xl">Pelunasan Susulan</flux:heading>
        <flux:subheading>Pembayaran yang sudah PAID di Xendit tetapi belum Lunas di sistem. Jadwal harian 02:15 memeriksa 35 hari terakhir; gunakan halaman ini untuk riwayat yang lebih lama.</flux:subheading>
    </div>

    <flux:card class="p-4 space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-end gap-3">
            <flux:input type="date" wire:model="dari" label="Tanggal awal waktu bayar (WIB)" class="sm:max-w-56" />

            <div class="flex flex-wrap gap-2">
                <flux:button wire:click="pratinjau" icon="eye" wire:loading.attr="disabled" :disabled="($pemindaian['status'] ?? null) === 'berjalan'">
                    Pratinjau
                </flux:button>
                @can('payment_gateway.ubah')
                    <flux:button wire:click="lunasiSekarang" variant="primary" icon="check-badge" wire:loading.attr="disabled" :disabled="($pemindaian['status'] ?? null) === 'berjalan'"
                        wire:confirm="Lunasi sekarang? Sistem akan memindai ulang Xendit dan melunasi invoice yang memenuhi aturan Pelunasan Susulan.">
                        Lunasi sekarang
                    </flux:button>
                @endcan
            </div>
        </div>
        <flux:text class="text-xs">Pratinjau tidak mengubah data. "Lunasi sekarang" memindai ulang lalu menerapkan aturan saat itu juga; kasus yang perlu tindakan manual dilaporkan, tidak dilunasi.</flux:text>
    </flux:card>

    @if($pemindaian)
        <flux:card class="p-0 overflow-hidden">
            <div class="p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-zinc-200 dark:border-zinc-700">
                <div>
                    <flux:heading size="md">
                        {{ $pemindaian['mode'] === 'pratinjau' ? 'Pratinjau' : 'Pelunasan' }} sejak {{ \Illuminate\Support\Carbon::parse($pemindaian['dari'])->translatedFormat('d M Y') }}
                    </flux:heading>
                    <flux:text class="text-xs">
                        Oleh {{ $pemindaian['oleh'] }} · dimulai {{ \Illuminate\Support\Carbon::parse($pemindaian['dimulai_pada'])->timezone(config('app.zona_waktu_bisnis'))->translatedFormat('d M Y H:i') }} WIB
                    </flux:text>
                </div>
                @if($pemindaian['status'] === 'berjalan')
                    <flux:badge color="blue" icon="arrow-path">Sedang memeriksa…</flux:badge>
                @elseif($pemindaian['status'] === 'gagal')
                    <flux:badge color="red">Gagal</flux:badge>
                @else
                    <flux:badge color="green">Selesai</flux:badge>
                @endif
            </div>

            @if($pemindaian['status'] === 'gagal')
                <div class="p-4 text-sm text-red-600 dark:text-red-400">{{ $pemindaian['galat'] ?? 'Pemindaian gagal.' }}</div>
            @elseif($pemindaian['status'] === 'selesai')
                <div class="px-4 py-3 text-sm text-zinc-700 dark:text-zinc-300">{{ $pemindaian['ringkasan'] }}</div>
                <div class="overflow-x-auto">
                    <table class="w-full text-xs text-left">
                        <thead class="bg-zinc-50 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-300 font-semibold border-y border-zinc-200 dark:border-zinc-700">
                            <tr>
                                <th class="px-4 py-3">Koneksi</th>
                                <th class="px-4 py-3">Invoice</th>
                                <th class="px-4 py-3">Pelanggan</th>
                                <th class="px-4 py-3 text-right">Nominal</th>
                                <th class="px-4 py-3">Dibayar</th>
                                <th class="px-4 py-3">Status Lokal</th>
                                <th class="px-4 py-3">Aksi</th>
                                <th class="px-4 py-3">Keterangan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                            @forelse($pemindaian['baris'] as $baris)
                                <tr wire:key="baris-{{ $loop->index }}">
                                    <td class="px-4 py-3">{{ $baris['koneksi'] }}</td>
                                    <td class="px-4 py-3 font-mono">{{ $baris['invoice'] }}</td>
                                    <td class="px-4 py-3">{{ $baris['pelanggan'] }}</td>
                                    <td class="px-4 py-3 text-right whitespace-nowrap">{{ $baris['nominal'] }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap">{{ $baris['dibayar'] }}</td>
                                    <td class="px-4 py-3">{{ $baris['status_lokal'] }}</td>
                                    <td class="px-4 py-3"><flux:badge size="sm">{{ $baris['aksi'] }}</flux:badge></td>
                                    <td class="px-4 py-3 min-w-64">{{ $baris['keterangan'] }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="px-4 py-6 text-center text-zinc-500">Tidak ada pembayaran yang perlu dilunasi atau dilaporkan.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            @endif
        </flux:card>
    @endif
</div>
