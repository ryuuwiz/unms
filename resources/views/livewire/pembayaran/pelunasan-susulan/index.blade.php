<div class="space-y-6" @if($sedangBerjalan) wire:poll.3s @endif>
    <div>
        <flux:heading size="xl">Pelunasan Susulan</flux:heading>
        <flux:subheading>Pembayaran yang sudah PAID di Xendit tetapi belum Lunas di sistem. Jadwal harian 02:15 memeriksa 35 hari terakhir; gunakan halaman ini untuk riwayat yang lebih lama.</flux:subheading>
    </div>

    <flux:card class="p-4 space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-end gap-3">
            <flux:input type="date" wire:model="dari" label="Tanggal awal waktu bayar (WIB)" class="sm:max-w-56" />

            <div class="flex flex-wrap gap-2">
                <flux:button wire:click="pratinjau" icon="eye" wire:loading.attr="disabled" :disabled="$sedangBerjalan">
                    Pratinjau
                </flux:button>
                @can('kelolaPelunasanSusulan', \App\Models\Pembayaran::class)
                    <flux:button wire:click="lunasiSekarang" variant="primary" icon="check-badge" wire:loading.attr="disabled" :disabled="$sedangBerjalan"
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
                <flux:badge :color="$pemindaian['status']->color()">{{ $pemindaian['status']->label() }}</flux:badge>
            </div>

            @if($macet)
                <div class="p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-amber-50 dark:bg-amber-950/30 text-sm text-amber-800 dark:text-amber-200">
                    <span>Pemindaian belum diambil worker antrean atau melewati batas waktunya. Pastikan queue worker berjalan, atau batalkan agar pemindaian lain dan jadwal harian tidak tertahan.</span>
                    <flux:button size="sm" variant="filled" wire:click="batalkanPemindaian" wire:confirm="Batalkan pemindaian ini?">Batalkan</flux:button>
                </div>
            @endif

            @if($pemindaian['status'] === \App\Enums\StatusPemindaian::Gagal)
                <div class="p-4 text-sm text-red-600 dark:text-red-400">{{ $pemindaian['galat'] ?? 'Pemindaian gagal.' }}</div>
            @elseif($pemindaian['status'] === \App\Enums\StatusPemindaian::Selesai)
                <div class="px-4 py-3 text-sm text-zinc-700 dark:text-zinc-300">{{ $pemindaian['ringkasan'] }}</div>
                <div class="overflow-x-auto">
                    <table class="w-full text-xs text-left">
                        <thead class="bg-zinc-50 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-300 font-semibold border-y border-zinc-200 dark:border-zinc-700">
                            <tr>
                                @foreach($kolom as $kunci => $judul)
                                    <th @class(['px-4 py-3', 'text-right' => $kunci === 'nominal'])>{{ $judul }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                            @forelse($pemindaian['baris'] as $baris)
                                <tr wire:key="baris-{{ $loop->index }}">
                                    @foreach($kolom as $kunci => $judul)
                                        <td @class([
                                            'px-4 py-3',
                                            'font-mono' => $kunci === 'invoice',
                                            'text-right whitespace-nowrap' => $kunci === 'nominal',
                                            'whitespace-nowrap' => $kunci === 'dibayar',
                                            'min-w-64' => $kunci === 'keterangan',
                                        ])>
                                            @if($kunci === 'aksi')
                                                <flux:badge size="sm">{{ $baris[$kunci] }}</flux:badge>
                                            @else
                                                {{ $baris[$kunci] }}
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ count($kolom) }}" class="px-4 py-6 text-center text-zinc-500">Tidak ada pembayaran yang perlu dilunasi atau dilaporkan.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            @endif
        </flux:card>
    @endif

    <flux:card class="p-0 overflow-hidden">
        <div class="p-4 border-b border-zinc-200 dark:border-zinc-700">
            <flux:heading size="md">Kasus Pelunasan Susulan terbuka ({{ $kasusTerbuka->total() }})</flux:heading>
            <flux:text class="text-xs">Pembayaran yang perlu tindakan manual, misalnya pembayaran ganda untuk direfund atau nominal yang tidak sama.</flux:text>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-xs text-left">
                <thead class="bg-zinc-50 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-300 font-semibold border-b border-zinc-200 dark:border-zinc-700">
                    <tr>
                        <th class="px-4 py-3">Waktu</th>
                        <th class="px-4 py-3">Invoice & Pelanggan</th>
                        <th class="px-4 py-3 text-right">Nominal</th>
                        <th class="px-4 py-3">Alasan</th>
                        @can('kelolaPelunasanSusulan', \App\Models\Pembayaran::class)
                            <th class="px-4 py-3 text-center">Aksi</th>
                        @endcan
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @forelse($kasusTerbuka as $kasus)
                        <tr wire:key="kasus-{{ $kasus->id }}">
                            <td class="px-4 py-3 whitespace-nowrap">
                                <div>{{ $kasus->created_at->timezone(config('app.zona_waktu_bisnis'))->translatedFormat('d M Y H:i') }}</div>
                                @if($kasus->dibayar_pada)
                                    <div class="text-[11px] text-zinc-400">Dibayar {{ $kasus->dibayar_pada->timezone(config('app.zona_waktu_bisnis'))->translatedFormat('d M Y H:i') }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                @if($kasus->invoice && ! $kasus->invoice->trashed())
                                    <a href="{{ route('invoice.show', $kasus->invoice) }}" wire:navigate class="font-mono font-semibold text-blue-600 dark:text-blue-400 hover:underline">{{ $kasus->invoice->no_invoice }}</a>
                                @else
                                    <div class="font-mono">{{ $kasus->invoice?->no_invoice ?? $kasus->external_id }}</div>
                                @endif
                                <div class="text-[11px] text-zinc-500">{{ $kasus->invoice?->pelanggan?->namaLengkap() ?? '-' }} · {{ $kasus->koneksi }}</div>
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">{{ $kasus->nominal !== null ? \App\Support\Rupiah::format((float) $kasus->nominal) : '-' }}</td>
                            <td class="px-4 py-3 min-w-64">{{ $kasus->alasan }}</td>
                            @can('kelolaPelunasanSusulan', \App\Models\Pembayaran::class)
                                <td class="px-4 py-3 text-center">
                                    <flux:button size="sm" variant="subtle" icon="check" wire:click="bukaTandaiDitangani({{ $kasus->id }})">Sudah Ditangani</flux:button>
                                </td>
                            @endcan
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-6 text-center text-zinc-500">Tidak ada kasus terbuka.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($kasusTerbuka->hasPages())
            <div class="p-4">{{ $kasusTerbuka->links() }}</div>
        @endif
    </flux:card>

    <flux:modal :open="$tampilkanModalTandai" wire:model.self="tampilkanModalTandai" class="max-w-lg">
        <form wire:submit="tandaiDitangani" class="space-y-4">
            <flux:heading size="lg">Tandai Sudah Ditangani</flux:heading>
            <flux:textarea wire:model="catatanPenanganan" label="Catatan (opsional)" placeholder="Mis. sudah direfund di Xendit pada …" rows="3" />
            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">Batal</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">Simpan</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
