<div>
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-zinc-900 dark:text-white">Katalog Promo & Diskon</h1>
            <p class="text-sm text-zinc-500 dark:text-zinc-400 mt-1">Kelola program voucher diskon harga dan bonus durasi paket internet.</p>
        </div>
        <div class="flex items-center gap-2">
            @can('promo.buat')
                <flux:button href="{{ route('promo.create') }}" variant="primary" icon="plus" wire:navigate>
                    Tambah Promo Baru
                </flux:button>
            @endcan
        </div>
    </div>

    <!-- Filter & Search Bar -->
    <div class="bg-white dark:bg-zinc-800 p-4 rounded-xl border border-zinc-200 dark:border-zinc-700 shadow-sm mb-6">
        <div class="w-full sm:w-80">
            <flux:input
                wire:model.live.debounce.300ms="search"
                icon="magnifying-glass"
                placeholder="Cari Kode Promo / Nama..."
                clearable
            />
        </div>
    </div>

    <!-- Table -->
    <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-zinc-50 dark:bg-zinc-900/50 border-b border-zinc-200 dark:border-zinc-700 text-zinc-600 dark:text-zinc-400 font-medium">
                    <tr>
                        <th class="px-4 py-3">Kode & Nama Promo</th>
                        <th class="px-4 py-3">Jenis & Bentuk Diskon</th>
                        <th class="px-4 py-3">Periode Berlaku</th>
                        <th class="px-4 py-3 text-center">Penggunaan</th>
                        <th class="px-4 py-3 text-center">Status</th>
                        <th class="px-4 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse($promos as $promo)
                        <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-700/30 transition-colors">
                            <td class="px-4 py-3">
                                <div class="font-bold text-zinc-900 dark:text-white font-mono">{{ $promo->kode_promo }}</div>
                                <div class="text-xs text-zinc-600 dark:text-zinc-300">{{ $promo->nama_promo }}</div>
                            </td>
                            <td class="px-4 py-3">
                                <flux:badge size="sm" :color="$promo->jenis->color()">
                                    {{ $promo->jenis->label() }}
                                </flux:badge>
                                <div class="text-xs text-zinc-600 dark:text-zinc-300 mt-1">
                                    @if($promo->diskon_tipe?->value === 'persentase')
                                        Potongan {{ $promo->diskon_nilai }}%
                                    @elseif($promo->diskon_tipe?->value === 'nominal')
                                        Potongan Rp {{ number_format((float) $promo->diskon_nilai, 0, ',', '.') }}
                                    @elseif($promo->bonus_bulan)
                                        Bonus {{ $promo->bonus_bulan }} Bulan
                                    @endif
                                </div>
                            </td>
                            <td class="px-4 py-3 text-xs text-zinc-600 dark:text-zinc-300">
                                <div>Dari: {{ $promo->berlaku_dari ? $promo->berlaku_dari->format('d M Y') : 'Tanpa batas' }}</div>
                                <div>Sampai: {{ $promo->berlaku_sampai ? $promo->berlaku_sampai->format('d M Y') : 'Tanpa batas' }}</div>
                            </td>
                            <td class="px-4 py-3 text-center text-xs">
                                <span class="font-bold">{{ $promo->terpakai_global }}</span> / {{ $promo->kuota_global ?: '∞' }}
                            </td>
                            <td class="px-4 py-3 text-center">
                                @can('promo.ubah')
                                    <button wire:click="toggleStatus({{ $promo->id }})" class="cursor-pointer">
                                        <flux:badge size="sm" :color="$promo->aktif ? 'green' : 'zinc'">
                                            {{ $promo->aktif ? 'Aktif' : 'Nonaktif' }}
                                        </flux:badge>
                                    </button>
                                @else
                                    <flux:badge size="sm" :color="$promo->aktif ? 'green' : 'zinc'">
                                        {{ $promo->aktif ? 'Aktif' : 'Nonaktif' }}
                                    </flux:badge>
                                @endcan
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex items-center justify-end gap-1">
                                    @can('promo.ubah')
                                        <flux:button href="{{ route('promo.edit', $promo) }}" size="xs" variant="subtle" icon="pencil" wire:navigate title="Edit Promo" />
                                    @endcan
                                    @can('promo.hapus')
                                        <flux:button wire:click="confirmDelete({{ $promo->id }})" size="xs" variant="danger" icon="trash" title="Hapus Promo" />
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-zinc-500 dark:text-zinc-400">
                                Belum ada data promo.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($promos->hasPages())
            <div class="p-4 border-t border-zinc-200 dark:border-zinc-700">
                {{ $promos->links() }}
            </div>
        @endif
    </div>

    <!-- Modal Konfirmasi Hapus -->
    <flux:modal :open="$deletingId !== null" wire:model.self="deletingId" class="max-w-md">
        <div class="p-6 space-y-4">
            <h3 class="text-lg font-bold text-rose-600">Hapus Promo?</h3>
            <p class="text-sm text-zinc-600 dark:text-zinc-400">
                Apakah Anda yakin ingin menghapus promo ini secara permanen?
            </p>
            <div class="flex justify-end gap-2 pt-2">
                <flux:button wire:click="$set('deletingId', null)" variant="subtle">Batal</flux:button>
                <flux:button wire:click="deletePromo" variant="danger">Ya, Hapus</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
