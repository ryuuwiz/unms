<div class="space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl">Paket Internet</flux:heading>
            <flux:subheading>Kelola katalog layanan internet, kecepatan bandwidth, dan tarif bulanan.</flux:subheading>
        </div>
        @can('create', App\Models\Package::class)
            <flux:button wire:click="openCreateModal" variant="primary" icon="plus">
                Tambah Paket
            </flux:button>
        @endcan
    </div>

    {{-- Filter & Search Bar --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
        <div class="flex-1">
            <flux:input
                wire:model.live.debounce.300ms="search"
                icon="magnifying-glass"
                placeholder="Cari nama paket atau keterangan..."
            />
        </div>

        <flux:select wire:model.live="filterStatus" placeholder="Semua Status" class="sm:w-44">
            <flux:select.option value="">Semua Status</flux:select.option>
            @foreach ($statuses as $status)
                <flux:select.option value="{{ $status->value }}">{{ $status->label() }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    {{-- Tabel Paket --}}
    <flux:table>
        <flux:table.columns>
            <flux:table.column>Nama Paket</flux:table.column>
            <flux:table.column>Kecepatan Bandwidth</flux:table.column>
            <flux:table.column>Tarif Bulanan</flux:table.column>
            <flux:table.column>Status</flux:table.column>
            <flux:table.column align="end">Aksi</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($packages as $package)
                <flux:table.row :key="$package->id">
                    {{-- Nama Paket & Keterangan --}}
                    <flux:table.cell>
                        <div class="flex flex-col">
                            <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ $package->name }}</span>
                            @if ($package->description)
                                <span class="max-w-md truncate text-xs text-zinc-500" title="{{ $package->description }}">
                                    {{ $package->description }}
                                </span>
                            @endif
                        </div>
                    </flux:table.cell>

                    {{-- Kecepatan Bandwidth --}}
                    <flux:table.cell>
                        <div class="flex items-center gap-2">
                            <flux:badge size="sm" color="sky">
                                ↓ {{ $package->download_speed_mbps }} Mbps
                            </flux:badge>
                            <flux:badge size="sm" color="indigo">
                                ↑ {{ $package->upload_speed_mbps }} Mbps
                            </flux:badge>
                        </div>
                    </flux:table.cell>

                    {{-- Tarif Bulanan --}}
                    <flux:table.cell class="font-medium text-zinc-900 dark:text-zinc-100">
                        {{ $package->formattedPrice() }}
                    </flux:table.cell>

                    {{-- Status --}}
                    <flux:table.cell>
                        <flux:badge size="sm" :color="$package->status->color()">
                            {{ $package->status->label() }}
                        </flux:badge>
                    </flux:table.cell>

                    {{-- Aksi --}}
                    <flux:table.cell align="end">
                        <div class="flex items-center justify-end gap-1">
                            @can('update', $package)
                                <flux:button
                                    wire:click="openEditModal({{ $package->id }})"
                                    size="sm"
                                    variant="ghost"
                                    icon="pencil-square"
                                    title="Edit Paket"
                                />

                                <flux:button
                                    wire:click="toggleStatus({{ $package->id }})"
                                    size="sm"
                                    variant="ghost"
                                    :icon="$package->isActive() ? 'pause' : 'play'"
                                    :title="$package->isActive() ? 'Nonaktifkan Paket' : 'Aktifkan Paket'"
                                />
                            @endcan

                            @can('delete', $package)
                                <flux:button
                                    wire:click="confirmDelete({{ $package->id }})"
                                    size="sm"
                                    variant="ghost"
                                    icon="trash"
                                    class="text-red-600 hover:text-red-700 dark:text-red-400"
                                    title="Hapus Paket"
                                />
                            @endcan
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="5" class="py-12 text-center text-zinc-400">
                        Tidak ada paket internet yang ditemukan.
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <div>
        {{ $packages->links() }}
    </div>

    {{-- Modal Tambah / Edit Paket --}}
    @if ($isModalOpen)
        <flux:modal :open="true" wire:model="isModalOpen" class="max-w-lg space-y-4">
            <div>
                <flux:heading size="lg">{{ $packageId ? 'Edit Paket Internet' : 'Tambah Paket Internet' }}</flux:heading>
                <flux:subheading>
                    {{ $packageId ? 'Perbarui parameter bandwidth, harga, atau status paket.' : 'Daftarkan paket internet baru ke katalog layanan.' }}
                </flux:subheading>
            </div>

            <form wire:submit="save" class="space-y-4">
                <flux:field>
                    <flux:label>Nama Paket</flux:label>
                    <flux:input wire:model="name" placeholder="Contoh: Home 20 Mbps" autofocus />
                    <flux:error name="name" />
                </flux:field>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <flux:field>
                        <flux:label>Kecepatan Unduh (Download)</flux:label>
                        <flux:input wire:model.live.debounce.300ms="download_speed_mbps" type="number" min="1" placeholder="20" />
                        <flux:description>Satuan dalam Mbps.</flux:description>
                        <flux:error name="download_speed_mbps" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Kecepatan Unggah (Upload)</flux:label>
                        <flux:input wire:model.live.debounce.300ms="upload_speed_mbps" type="number" min="1" placeholder="10" />
                        <flux:description>Satuan dalam Mbps.</flux:description>
                        <flux:error name="upload_speed_mbps" />
                    </flux:field>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <flux:field>
                        <flux:label>Tarif / Bulan (Rp)</flux:label>
                        <flux:input wire:model="price" type="number" min="1" placeholder="200000" />
                        <flux:description>Tarif bulanan dalam Rupiah.</flux:description>
                        <flux:error name="price" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Status Paket</flux:label>
                        <flux:select wire:model="status">
                            @foreach ($statuses as $st)
                                <flux:select.option value="{{ $st->value }}">{{ $st->label() }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:error name="status" />
                    </flux:field>
                </div>

                <flux:field>
                    <flux:label>Keterangan / Benefit <span class="text-zinc-400 font-normal">(opsional)</span></flux:label>
                    <flux:textarea wire:model="description" rows="2" placeholder="Keterangan fitur paket, batasan, atau benefit..." />
                    <flux:error name="description" />
                </flux:field>

                <div class="flex justify-end gap-3 pt-2">
                    <flux:button type="button" wire:click="$set('isModalOpen', false)" variant="ghost">Batal</flux:button>
                    <flux:button type="submit" variant="primary" icon="check">
                        {{ $packageId ? 'Simpan Perubahan' : 'Tambah Paket' }}
                    </flux:button>
                </div>
            </form>
        </flux:modal>
    @endif

    {{-- Modal Konfirmasi Hapus --}}
    @if ($deletingPackageId)
        <flux:modal :open="true" wire:model="deletingPackageId" class="max-w-md space-y-4">
            <div>
                <flux:heading size="lg">Hapus Paket Internet</flux:heading>
                <flux:subheading>
                    Apakah Anda yakin ingin menghapus paket internet ini? Paket akan dinonaktifkan/diarsipkan dan tidak dapat dipilih untuk pelanggan baru.
                </flux:subheading>
            </div>

            <div class="flex justify-end gap-3 pt-2">
                <flux:button wire:click="$set('deletingPackageId', null)" variant="ghost">Batal</flux:button>
                <flux:button wire:click="deletePackage" variant="danger">Ya, Hapus</flux:button>
            </div>
        </flux:modal>
    @endif
</div>
