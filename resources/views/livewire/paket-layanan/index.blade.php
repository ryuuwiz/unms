<div class="space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl">Paket Layanan</flux:heading>
            <flux:subheading>Kelola katalog layanan internet, profil bandwidth, masa aktif, dan tarif.</flux:subheading>
        </div>
        @can('create', App\Models\PaketLayanan::class)
            <flux:button :href="route('paket-layanan.create')" wire:navigate variant="primary" icon="plus">
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

    {{-- Hasil: kartu di HP, tabel dari md ke atas --}}
    @if ($pakets->isEmpty())
        <div class="flex flex-col items-center gap-2 rounded-xl border border-zinc-200 bg-white px-4 py-12 text-center text-zinc-500 dark:border-zinc-700 dark:bg-zinc-800">
            <flux:icon name="queue-list" class="size-8 text-zinc-300 dark:text-zinc-600" />
            <p class="font-medium">Tidak ada paket layanan ditemukan.</p>
            @if ($search || $filterStatus)
                <p class="text-xs text-zinc-400">Coba ubah filter atau kata kunci pencarian.</p>
            @endif
        </div>
    @else
        {{-- Kartu (HP) --}}
        <div class="space-y-3 md:hidden">
            @foreach ($pakets as $paket)
                <div wire:key="kartu-{{ $paket->id }}" @class(['relative rounded-xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-800', 'opacity-60' => $paket->status !== App\Enums\StatusPaket::Aktif])>
                    <div class="flex items-start justify-between gap-2">
                        @can('update', $paket)
                            <a href="{{ route('paket-layanan.edit', $paket) }}" wire:navigate class="font-medium text-zinc-900 after:absolute after:inset-0 dark:text-zinc-100">{{ $paket->nama_paket }}</a>
                        @else
                            <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ $paket->nama_paket }}</span>
                        @endcan
                        <div class="relative z-10 flex shrink-0 items-center gap-1">
                            <flux:badge size="sm" :color="$paket->status->color()">{{ $paket->status->label() }}</flux:badge>
                            @include('livewire.paket-layanan.partials.aksi')
                        </div>
                    </div>

                    <div class="mt-1 font-semibold text-zinc-900 dark:text-zinc-100">
                        {{ $paket->profilBandwidth?->labelKecepatan() ?? '—' }} · {{ $paket->formattedHarga() }}
                    </div>

                    @if ($paket->keterangan)
                        <p class="mt-1 truncate text-sm text-zinc-500">{{ $paket->keterangan }}</p>
                    @endif

                    @can('update', $paket)
                        <p class="mt-1 text-xs text-zinc-400">Dipakai {{ $paket->layanans_count }} layanan</p>
                    @endcan
                </div>
            @endforeach
        </div>

        {{-- Tabel (md ke atas) --}}
        <div class="hidden md:block">
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>Paket</flux:table.column>
                    <flux:table.column>Kecepatan</flux:table.column>
                    <flux:table.column>Harga</flux:table.column>
                    <flux:table.column>Status</flux:table.column>
                    <flux:table.column class="w-12"><span class="sr-only">Aksi</span></flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach ($pakets as $paket)
                        @php($bisaUbah = auth()->user()->can('update', $paket))
                        <flux:table.row
                            :key="$paket->id"
                            :class="Arr::toCssClasses(['opacity-60' => $paket->status !== App\Enums\StatusPaket::Aktif, 'cursor-pointer hover:bg-zinc-50 dark:hover:bg-zinc-700/30' => $bisaUbah])"
                            data-href="{{ $bisaUbah ? route('paket-layanan.edit', $paket) : '' }}"
                            x-on:click="$el.dataset.href && ! $event.target.closest('a, button, [data-flux-menu]') && Livewire.navigate($el.dataset.href)"
                        >
                            <flux:table.cell>
                                <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $paket->nama_paket }}</div>
                                @if ($paket->keterangan)
                                    <div class="max-w-xs truncate text-xs text-zinc-500" title="{{ $paket->keterangan }}">{{ $paket->keterangan }}</div>
                                @endif
                            </flux:table.cell>

                            <flux:table.cell>
                                <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $paket->profilBandwidth?->labelKecepatan() ?? '—' }}</div>
                                <div class="text-xs text-zinc-500">{{ $paket->profilBandwidth?->nama_bandwidth }}</div>
                            </flux:table.cell>

                            <flux:table.cell class="font-medium whitespace-nowrap text-zinc-900 dark:text-zinc-100">
                                {{ $paket->formattedHarga() }}
                            </flux:table.cell>

                            <flux:table.cell>
                                <flux:badge size="sm" :color="$paket->status->color()">{{ $paket->status->label() }}</flux:badge>
                                @if ($bisaUbah)
                                    <div class="mt-1 text-xs text-zinc-400">Dipakai {{ $paket->layanans_count }} layanan</div>
                                @endif
                            </flux:table.cell>

                            <flux:table.cell align="end">
                                @include('livewire.paket-layanan.partials.aksi')
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </div>
    @endif

    {{-- Pagination --}}
    @if ($pakets->hasPages())
        <div>
            {{ $pakets->links() }}
        </div>
    @endif

    {{-- Modal Konfirmasi Hapus --}}
    <flux:modal :open="$deletingId !== null" wire:model.self="deletingId" class="max-w-md">
        <div class="space-y-4">
            <div>
                <flux:heading size="lg">Hapus Paket Layanan</flux:heading>
                <flux:subheading>
                    Apakah Anda yakin ingin menghapus paket layanan ini? Tindakan ini tidak dapat dibatalkan.
                </flux:subheading>
            </div>
            <div class="flex justify-end gap-3">
                <flux:button wire:click="$set('deletingId', null)" variant="ghost">Batal</flux:button>
                <flux:button wire:click="deletePaket" variant="danger">Hapus</flux:button>
            </div>
        </div>
    </flux:modal>
</div>

