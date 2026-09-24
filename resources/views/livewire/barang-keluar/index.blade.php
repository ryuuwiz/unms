<div class="space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl">Barang Keluar</flux:heading>
            <flux:subheading>Riwayat pencatatan barang keluar dari gudang/inventaris.</flux:subheading>
        </div>
        <div class="flex items-center gap-2">
            @can('create', App\Models\BarangKeluar::class)
                <flux:button :href="route('barang-keluar.create')" wire:navigate variant="primary" icon="plus">
                    Catat Barang Keluar
                </flux:button>
            @endcan
            @can('viewAny', App\Models\BarangKeluar::class)
                <flux:button wire:click="exportExcel" variant="filled" icon="arrow-down-tray">
                    Ekspor Excel
                </flux:button>
            @endcan
        </div>
    </div>

    <div class="bg-white dark:bg-zinc-800 p-4 rounded-xl border border-zinc-200 dark:border-zinc-700 shadow-sm flex flex-col md:flex-row gap-4 md:items-end">
        <div class="flex-1">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" label="Cari Barang" placeholder="Cari kode/nama barang..." />
        </div>
        <div class="w-full md:w-44">
            <flux:input type="date" wire:model.live="startDate" label="Dari Tanggal" />
        </div>
        <div class="w-full md:w-44">
            <flux:input type="date" wire:model.live="endDate" label="Sampai Tanggal" />
        </div>
    </div>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>Tanggal</flux:table.column>
            <flux:table.column>Kode Barang</flux:table.column>
            <flux:table.column>Nama Barang</flux:table.column>
            <flux:table.column>Jumlah Keluar</flux:table.column>
            <flux:table.column>Teknisi</flux:table.column>
            <flux:table.column>Keterangan</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($items as $item)
                <flux:table.row :key="$item->id">
                    <flux:table.cell>{{ $item->tanggal->format('d/m/Y') }}</flux:table.cell>
                    <flux:table.cell class="font-mono">{{ $item->barang?->kode_barang ?? '-' }}</flux:table.cell>
                    <flux:table.cell>{{ $item->barang?->nama_barang ?? '-' }}</flux:table.cell>
                    <flux:table.cell class="font-semibold text-rose-600 dark:text-rose-400">-{{ number_format($item->jumlah_keluar) }}</flux:table.cell>
                    <flux:table.cell>{{ $item->teknisi ?? '-' }}</flux:table.cell>
                    <flux:table.cell class="max-w-xs truncate" title="{{ $item->keterangan }}">{{ $item->keterangan ?? '-' }}</flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="6" class="py-12 text-center text-zinc-500">
                        Belum ada riwayat barang keluar.
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    @if ($items->hasPages())
        <div>{{ $items->links() }}</div>
    @endif
</div>
