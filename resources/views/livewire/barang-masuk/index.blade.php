<div class="space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl">Barang Masuk</flux:heading>
            <flux:subheading>Riwayat pencatatan barang masuk ke gudang/inventaris.</flux:subheading>
        </div>
        <div class="flex items-center gap-2">
            @can('create', App\Models\BarangMasuk::class)
                <flux:button :href="route('barang-masuk.create')" wire:navigate variant="primary" icon="plus">
                    Catat Barang Masuk
                </flux:button>
            @endcan
            @can('viewAny', App\Models\BarangMasuk::class)
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
            <flux:table.column>Jumlah Masuk</flux:table.column>
            <flux:table.column>Dicatat Oleh</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($items as $item)
                <flux:table.row :key="$item->id">
                    <flux:table.cell>{{ $item->tanggal->format('d/m/Y') }}</flux:table.cell>
                    <flux:table.cell class="font-mono">{{ $item->barang?->kode_barang ?? '-' }}</flux:table.cell>
                    <flux:table.cell>{{ $item->barang?->nama_barang ?? '-' }}</flux:table.cell>
                    <flux:table.cell class="font-semibold text-emerald-600 dark:text-emerald-400">+{{ number_format($item->jumlah_masuk) }}</flux:table.cell>
                    <flux:table.cell>{{ $item->dicatatOleh?->name ?? '-' }}</flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="5" class="py-12 text-center text-zinc-500">
                        Belum ada riwayat barang masuk.
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    @if ($items->hasPages())
        <div>{{ $items->links() }}</div>
    @endif
</div>
