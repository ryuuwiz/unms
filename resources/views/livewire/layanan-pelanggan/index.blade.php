<div class="space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl">Layanan Pelanggan</flux:heading>
            <flux:subheading>Kelola layanan internet aktif, kredensial PPPoE, router, dan masa aktif pelanggan.</flux:subheading>
        </div>
        @can('create', App\Models\LayananPelanggan::class)
            <flux:button :href="route('layanan-pelanggan.create')" wire:navigate variant="primary" icon="plus">
                Tambah Layanan
            </flux:button>
        @endcan
    </div>

    {{-- Filter & Search Bar --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
        <div class="flex-1">
            <flux:input
                wire:model.live.debounce.300ms="search"
                icon="magnifying-glass"
                placeholder="Cari pelanggan, no. HP, atau site ID..."
            />
        </div>

        <flux:select wire:model.live="filterStatus" placeholder="Semua Status" class="sm:w-44">
            <flux:select.option value="">Semua Status</flux:select.option>
            @foreach ($statuses as $status)
                <flux:select.option value="{{ $status->value }}">{{ $status->label() }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    {{-- Tabel Layanan --}}
    <flux:table>
        <flux:table.columns>
            <flux:table.column>Pelanggan / Site ID</flux:table.column>
            <flux:table.column>Paket Layanan</flux:table.column>
            <flux:table.column>Router & PPP</flux:table.column>
            <flux:table.column>Masa Aktif / Jatuh Tempo</flux:table.column>
            <flux:table.column>Status</flux:table.column>
            <flux:table.column align="end">Aksi</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($layanans as $layanan)
                <flux:table.row :key="$layanan->id">
                    {{-- Pelanggan --}}
                    <flux:table.cell>
                        <div class="flex flex-col">
                            <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ $layanan->pelanggan->namaLengkap() }}</span>
                            <span class="font-mono text-xs text-zinc-500">{{ $layanan->site_id }} ({{ $layanan->pelanggan->no_reg }})</span>
                        </div>
                    </flux:table.cell>

                    {{-- Paket --}}
                    <flux:table.cell>
                        <div class="flex flex-col">
                            <span class="font-medium text-zinc-800 dark:text-zinc-200">{{ $layanan->paketLayanan->nama_paket }}</span>
                            <span class="text-xs text-zinc-500">{{ $layanan->paketLayanan->formattedHarga() }}</span>
                        </div>
                    </flux:table.cell>

                    {{-- Router & PPP --}}
                    <flux:table.cell>
                        <div class="flex flex-col">
                            <span class="text-xs font-medium text-zinc-700 dark:text-zinc-300">{{ $layanan->router->nama_router }}</span>
                            <span class="font-mono text-xs text-zinc-500">{{ $layanan->ppp_username }} ({{ $layanan->jenis_koneksi->label() }})</span>
                        </div>
                    </flux:table.cell>

                    {{-- Tanggal Expired --}}
                    <flux:table.cell>
                        <div class="flex flex-col text-xs">
                            <span class="text-zinc-500">Mulai: {{ $layanan->tanggal_mulai->format('d/m/Y') }}</span>
                            <span class="font-medium {{ $layanan->tanggal_expired && $layanan->tanggal_expired->isPast() ? 'text-red-600' : 'text-zinc-700 dark:text-zinc-300' }}">
                                Exp: {{ $layanan->tanggal_expired?->format('d/m/Y') ?? '—' }}
                            </span>
                        </div>
                    </flux:table.cell>

                    {{-- Status --}}
                    <flux:table.cell>
                        <flux:badge size="sm" :color="$layanan->status->color()">
                            {{ $layanan->status->label() }}
                        </flux:badge>
                    </flux:table.cell>

                    {{-- Aksi --}}
                    <flux:table.cell align="end">
                        <div class="flex items-center justify-end gap-1">
                            @can('update', $layanan)
                                <flux:button
                                    :href="route('layanan-pelanggan.edit', $layanan)"
                                    wire:navigate
                                    size="sm"
                                    variant="ghost"
                                    icon="pencil-square"
                                    title="Edit Layanan"
                                />
                            @endcan

                            @can('delete', $layanan)
                                <flux:button
                                    wire:click="confirmDelete({{ $layanan->id }})"
                                    size="sm"
                                    variant="ghost"
                                    icon="trash"
                                    class="text-red-600 hover:text-red-700 dark:text-red-400"
                                    title="Hapus Layanan"
                                />
                            @endcan
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="6" class="py-12 text-center text-zinc-500">
                        <div class="flex flex-col items-center gap-2">
                            <flux:icon name="signal" class="size-8 text-zinc-300 dark:text-zinc-600" />
                            <p class="font-medium">Tidak ada layanan pelanggan ditemukan.</p>
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    {{-- Pagination --}}
    @if ($layanans->hasPages())
        <div>
            {{ $layanans->links() }}
        </div>
    @endif

    {{-- Modal Konfirmasi Hapus --}}
    @if ($deletingId)
        <flux:modal name="confirm-delete" :show="true" class="max-w-md">
            <div class="space-y-4">
                <div>
                    <flux:heading size="lg">Hapus Layanan</flux:heading>
                    <flux:subheading>
                        Apakah Anda yakin ingin menghapus layanan ini?
                    </flux:subheading>
                </div>
                <div class="flex justify-end gap-3">
                    <flux:button wire:click="$set('deletingId', null)" variant="ghost">Batal</flux:button>
                    <flux:button wire:click="deleteLayanan" variant="danger">Hapus</flux:button>
                </div>
            </div>
        </flux:modal>
    @endif
</div>
