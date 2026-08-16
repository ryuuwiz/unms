<div class="space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl">Data Pelanggan</flux:heading>
            <flux:subheading>Kelola master data, kontak, dan titik instalasi pelanggan.</flux:subheading>
        </div>
        @can('create', App\Models\Customer::class)
            <flux:button :href="route('customers.create')" wire:navigate variant="primary" icon="plus">
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
                placeholder="Cari nama, no. HP, atau kode pelanggan..."
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
            <flux:table.column>Kode Pelanggan</flux:table.column>
            <flux:table.column>Nama & Kontak</flux:table.column>
            <flux:table.column>Alamat Instalasi</flux:table.column>
            <flux:table.column>Status</flux:table.column>
            <flux:table.column>Didaftarkan Oleh</flux:table.column>
            <flux:table.column align="end">Aksi</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($customers as $customer)
                <flux:table.row :key="$customer->id">
                    {{-- Kode Pelanggan --}}
                    <flux:table.cell class="font-mono font-medium text-zinc-800 dark:text-zinc-200">
                        {{ $customer->customer_code }}
                    </flux:table.cell>

                    {{-- Nama & Kontak --}}
                    <flux:table.cell>
                        <div class="flex flex-col">
                            <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ $customer->name }}</span>
                            <span class="text-xs text-zinc-500">{{ $customer->phone }}</span>
                            @if ($customer->email)
                                <span class="text-xs text-zinc-400">{{ $customer->email }}</span>
                            @endif
                        </div>
                    </flux:table.cell>

                    {{-- Alamat Instalasi --}}
                    <flux:table.cell class="max-w-xs truncate text-zinc-600 dark:text-zinc-300">
                        <div class="flex items-center gap-1.5">
                            @if ($customer->lat && $customer->lng)
                                <flux:icon name="map-pin" class="size-4 shrink-0 text-emerald-500" />
                            @endif
                            <span class="truncate" title="{{ $customer->installation_address }}">
                                {{ $customer->installation_address }}
                            </span>
                        </div>
                    </flux:table.cell>

                    {{-- Status --}}
                    <flux:table.cell>
                        <flux:badge size="sm" :color="$customer->status->color()">
                            {{ $customer->status->label() }}
                        </flux:badge>
                    </flux:table.cell>

                    {{-- Pembuat --}}
                    <flux:table.cell class="text-xs text-zinc-500">
                        {{ $customer->creator?->name ?? '—' }}
                    </flux:table.cell>

                    {{-- Aksi --}}
                    <flux:table.cell align="end">
                        <div class="flex items-center justify-end gap-1">
                            <flux:button
                                :href="route('customers.show', $customer)"
                                wire:navigate
                                size="sm"
                                variant="ghost"
                                icon="eye"
                                title="Lihat Detail"
                            />

                            @can('update', $customer)
                                <flux:button
                                    :href="route('customers.edit', $customer)"
                                    wire:navigate
                                    size="sm"
                                    variant="ghost"
                                    icon="pencil-square"
                                    title="Edit Pelanggan"
                                />

                                <flux:button
                                    wire:click="toggleStatus({{ $customer->id }})"
                                    size="sm"
                                    variant="ghost"
                                    :icon="$customer->isActive() ? 'pause' : 'play'"
                                    :title="$customer->isActive() ? 'Nonaktifkan Pelanggan' : 'Aktifkan Pelanggan'"
                                />
                            @endcan

                            @can('delete', $customer)
                                <flux:button
                                    wire:click="confirmDelete({{ $customer->id }})"
                                    size="sm"
                                    variant="ghost"
                                    icon="trash"
                                    class="text-red-600 hover:text-red-700 dark:text-red-400"
                                    title="Hapus Pelanggan"
                                />
                            @endcan
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="6" class="py-12 text-center text-zinc-400">
                        Tidak ada data pelanggan yang ditemukan.
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <div>
        {{ $customers->links() }}
    </div>

    {{-- Modal Konfirmasi Hapus --}}
    @if ($deletingCustomerId)
        <flux:modal :open="true" wire:model="deletingCustomerId" class="max-w-md space-y-4">
            <div>
                <flux:heading size="lg">Hapus Data Pelanggan</flux:heading>
                <flux:subheading>
                    Apakah Anda yakin ingin menghapus data pelanggan ini? Data akan diarsipkan dan dapat dipulihkan jika diperlukan.
                </flux:subheading>
            </div>

            <div class="flex justify-end gap-3 pt-2">
                <flux:button wire:click="$set('deletingCustomerId', null)" variant="ghost">Batal</flux:button>
                <flux:button wire:click="deleteCustomer" variant="danger">Ya, Hapus</flux:button>
            </div>
        </flux:modal>
    @endif
</div>
