<div class="space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl">List Kontak Pelanggan</flux:heading>
            <flux:subheading>Daftar seluruh pelanggan internet beserta status pemasangan & aktivasi.</flux:subheading>
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
                placeholder="Cari no. reg, nama, no. HP, atau email..."
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
            <flux:table.column>No. Reg</flux:table.column>
            <flux:table.column>Nama Pelanggan</flux:table.column>
            <flux:table.column>No. HP</flux:table.column>
            <flux:table.column>Email</flux:table.column>
            <flux:table.column>Dibuat</flux:table.column>
            <flux:table.column>Status</flux:table.column>
            <flux:table.column align="end">Aksi</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($pelanggans as $pelanggan)
                <flux:table.row :key="$pelanggan->id">
                    {{-- No. Reg --}}
                    <flux:table.cell>
                        <a href="{{ route('pelanggan.show', $pelanggan) }}" wire:navigate class="font-mono text-xs font-bold text-primary-600 hover:underline dark:text-primary-400">
                            {{ $pelanggan->no_reg }}
                        </a>
                    </flux:table.cell>

                    {{-- Nama Pelanggan --}}
                    <flux:table.cell>
                        <div class="flex flex-col">
                            <a href="{{ route('pelanggan.show', $pelanggan) }}" wire:navigate class="font-medium text-xs text-zinc-900 hover:underline dark:text-zinc-100">
                                {{ $pelanggan->namaLengkap() }}
                            </a>
                            <div class="flex items-center gap-1.5 mt-0.5">
                                <flux:badge size="xs" color="zinc" class="text-[10px]">
                                    {{ $pelanggan->tipe_pelanggan->label() }}
                                </flux:badge>
                                @if ($pelanggan->latitude && $pelanggan->longitude)
                                    <span class="inline-flex items-center text-[10px] text-emerald-600 dark:text-emerald-400 font-medium gap-0.5">
                                        <flux:icon name="map-pin" class="size-3" />
                                        GPS
                                    </span>
                                @endif
                            </div>
                        </div>
                    </flux:table.cell>

                    {{-- No. HP --}}
                    <flux:table.cell class="text-xs">
                        <div class="flex items-center gap-1.5">
                            <span class="font-mono text-zinc-800 dark:text-zinc-200">{{ $pelanggan->no_hp }}</span>
                            <a href="https://wa.me/{{ $pelanggan->no_hp }}" target="_blank" rel="noopener noreferrer"
                                class="text-emerald-600 hover:text-emerald-700 dark:text-emerald-400" title="Chat WhatsApp">
                                <flux:icon name="chat-bubble-left-right" class="size-3.5" />
                            </a>
                        </div>
                    </flux:table.cell>

                    {{-- Email --}}
                    <flux:table.cell class="text-xs text-zinc-600 dark:text-zinc-300">
                        {{ $pelanggan->email ?: '—' }}
                    </flux:table.cell>

                    {{-- Dibuat (Timestamp) --}}
                    <flux:table.cell class="text-xs text-zinc-500">
                        <div class="font-medium text-zinc-700 dark:text-zinc-300">
                            {{ $pelanggan->created_at?->translatedFormat('d M Y, H:i') ?? '—' }}
                        </div>
                        <div class="text-[11px] text-zinc-400">
                            Oleh: {{ $pelanggan->pembuat?->name ?? 'Sistem' }}
                        </div>
                    </flux:table.cell>

                    {{-- Status --}}
                    <flux:table.cell>
                        <flux:badge size="sm" :color="$pelanggan->status->color()">
                            {{ $pelanggan->status->label() }}
                        </flux:badge>
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
                                title="Detail Pelanggan"
                            />
                            @can('update', $pelanggan)
                                <flux:button
                                    :href="route('pelanggan.edit', $pelanggan)"
                                    wire:navigate
                                    size="sm"
                                    variant="ghost"
                                    icon="pencil-square"
                                    aria-label="Edit"
                                    title="Edit Pelanggan"
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
                                    title="{{ $pelanggan->status === App\Enums\StatusPelanggan::Aktif ? 'Nonaktifkan' : 'Aktifkan' }}"
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
                                    title="Hapus Pelanggan"
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
    <flux:modal :open="$deletingCustomerId !== null" wire:model.self="deletingCustomerId" class="max-w-md">
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
</div>

