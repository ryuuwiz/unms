<div class="space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl">Data Pelanggan</flux:heading>
            <flux:subheading>Kelola master data, kontak, dan titik instalasi pelanggan.</flux:subheading>
        </div>
        @can('create', App\Models\Pelanggan::class)
            <flux:button :href="route('pelanggan.create')" wire:navigate variant="primary" icon="plus">
                Tambah Pelanggan
            </flux:button>
        @endcan
    </div>

    {{-- Filter & Search Bar --}}
    <div class="flex flex-col gap-3 lg:flex-row lg:items-center">
        <div class="flex-1">
            <flux:input
                wire:model.live.debounce.300ms="search"
                icon="magnifying-glass"
                placeholder="Cari nama, no. HP, email, atau no. registrasi..."
            />
        </div>

        @if ($isSales)
            <flux:select wire:model.live="filterScope" class="sm:w-48">
                <flux:select.option value="all">Semua Pelanggan</flux:select.option>
                <flux:select.option value="my">Pelanggan Saya</flux:select.option>
            </flux:select>
        @endif

        <flux:select wire:model.live="filterStatus" placeholder="Semua Status" class="sm:w-44">
            <flux:select.option value="">Semua Status</flux:select.option>
            @foreach ($statuses as $status)
                <flux:select.option value="{{ $status->value }}">{{ $status->label() }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    {{-- Tabel Pelanggan --}}
    <flux:table>
        <flux:table.columns>
            <flux:table.column>No. Registrasi</flux:table.column>
            <flux:table.column>Nama & Kontak</flux:table.column>
            <flux:table.column>Tipe</flux:table.column>
            <flux:table.column>Alamat</flux:table.column>
            <flux:table.column>Status</flux:table.column>
            <flux:table.column>Didaftarkan Oleh</flux:table.column>
            <flux:table.column align="end">Aksi</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($pelanggans as $pelanggan)
                <flux:table.row :key="$pelanggan->id">
                    {{-- No. Registrasi --}}
                    <flux:table.cell class="font-mono font-medium text-zinc-800 dark:text-zinc-200">
                        {{ $pelanggan->no_reg }}
                    </flux:table.cell>

                    {{-- Nama & Kontak --}}
                    <flux:table.cell>
                        <div class="flex flex-col">
                            <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ $pelanggan->namaLengkap() }}</span>
                            <span class="text-xs text-zinc-500">{{ $pelanggan->no_hp }}</span>
                            @if ($pelanggan->email)
                                <span class="text-xs text-zinc-400">{{ $pelanggan->email }}</span>
                            @endif
                        </div>
                    </flux:table.cell>

                    {{-- Tipe --}}
                    <flux:table.cell>
                        <flux:badge size="sm" color="zinc">
                            {{ $pelanggan->tipe_pelanggan->label() }}
                        </flux:badge>
                    </flux:table.cell>

                    {{-- Alamat --}}
                    <flux:table.cell class="max-w-xs truncate text-zinc-600 dark:text-zinc-300">
                        <div class="flex items-center gap-1.5">
                            @if ($pelanggan->latitude && $pelanggan->longitude)
                                <flux:icon name="map-pin" class="size-4 shrink-0 text-emerald-500" />
                            @endif
                            <span class="truncate" title="{{ $pelanggan->alamat_lengkap }}">
                                {{ $pelanggan->alamat_lengkap }}
                            </span>
                        </div>
                    </flux:table.cell>

                    {{-- Status --}}
                    <flux:table.cell>
                        <flux:badge size="sm" :color="$pelanggan->status->color()">
                            {{ $pelanggan->status->label() }}
                        </flux:badge>
                    </flux:table.cell>

                    {{-- Pembuat --}}
                    <flux:table.cell class="text-xs text-zinc-500">
                        {{ $pelanggan->pembuat?->name ?? '—' }}
                    </flux:table.cell>

                    {{-- Aksi --}}
                    <flux:table.cell align="end">
                        <div class="flex items-center justify-end gap-1">
                            <flux:button
                                :href="route('pelanggan.show', $pelanggan)"
                                wire:navigate
                                size="sm"
                                variant="ghost"
                                icon="eye"
                                aria-label="Lihat detail"
                            />
                            @can('update', $pelanggan)
                                <flux:button
                                    :href="route('pelanggan.edit', $pelanggan)"
                                    wire:navigate
                                    size="sm"
                                    variant="ghost"
                                    icon="pencil-square"
                                    aria-label="Edit"
                                />
                            @endcan
                            @can('update', $pelanggan)
                                <flux:button
                                    wire:click="toggleStatus({{ $pelanggan->id }})"
                                    wire:confirm="Ubah status pelanggan ini?"
                                    size="sm"
                                    variant="ghost"
                                    :icon="$pelanggan->status === App\Enums\StatusPelanggan::Aktif ? 'pause' : 'play'"
                                    :aria-label="$pelanggan->status === App\Enums\StatusPelanggan::Aktif ? 'Nonaktifkan' : 'Aktifkan'"
                                />
                            @endcan
                            @can('delete', $pelanggan)
                                <flux:button
                                    wire:click="confirmDelete({{ $pelanggan->id }})"
                                    size="sm"
                                    variant="ghost"
                                    icon="trash"
                                    class="text-red-600 hover:text-red-700 dark:text-red-400"
                                    aria-label="Hapus"
                                />
                            @endcan
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="7" class="py-12 text-center text-zinc-500">
                        <div class="flex flex-col items-center gap-2">
                            <flux:icon name="user-group" class="size-8 text-zinc-300 dark:text-zinc-600" />
                            <p class="font-medium">Tidak ada data pelanggan ditemukan.</p>
                            @if ($search || $filterStatus || $filterScope !== 'all')
                                <p class="text-xs text-zinc-400">Coba ubah filter atau kata kunci pencarian.</p>
                            @endif
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    {{-- Pagination --}}
    @if ($pelanggans->hasPages())
        <div>
            {{ $pelanggans->links() }}
        </div>
    @endif

    {{-- Konfirmasi Hapus Modal --}}
    @if ($deletingCustomerId)
        <flux:modal name="confirm-delete" :show="true" class="max-w-md">
            <div class="space-y-4">
                <div>
                    <flux:heading size="lg">Hapus Pelanggan</flux:heading>
                    <flux:subheading>
                        Apakah Anda yakin ingin menghapus data pelanggan ini? Data akan dipindahkan ke tempat sampah (soft delete).
                    </flux:subheading>
                </div>
                <div class="flex justify-end gap-3">
                    <flux:button wire:click="$set('deletingCustomerId', null)" variant="ghost">
                        Batal
                    </flux:button>
                    <flux:button wire:click="deleteCustomer" variant="danger">
                        Hapus
                    </flux:button>
                </div>
            </div>
        </flux:modal>
    @endif
</div>
