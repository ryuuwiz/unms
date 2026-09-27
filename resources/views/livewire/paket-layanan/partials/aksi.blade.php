@canany(['update', 'delete'], $paket)
    <flux:dropdown position="bottom" align="end">
        <flux:button size="sm" variant="ghost" icon="ellipsis-vertical" aria-label="Aksi paket {{ $paket->nama_paket }}" />

        <flux:menu>
            @can('update', $paket)
                <flux:menu.item :href="route('paket-layanan.edit', $paket)" wire:navigate icon="pencil-square">Edit</flux:menu.item>
                <flux:menu.item wire:click="toggleStatus({{ $paket->id }})" :icon="$paket->status === App\Enums\StatusPaket::Aktif ? 'pause' : 'play'">
                    {{ $paket->status === App\Enums\StatusPaket::Aktif ? 'Nonaktifkan' : 'Aktifkan' }}
                </flux:menu.item>
            @endcan

            @can('delete', $paket)
                @if ($paket->layanans_count > 0)
                    <div title="Tidak dapat dihapus: dipakai {{ $paket->layanans_count }} layanan. Nonaktifkan saja agar tidak muncul di registrasi baru.">
                        <flux:menu.item icon="trash" disabled>Hapus</flux:menu.item>
                    </div>
                @else
                    <flux:menu.item wire:click="confirmDelete({{ $paket->id }})" icon="trash" variant="danger">Hapus</flux:menu.item>
                @endif
            @endcan
        </flux:menu>
    </flux:dropdown>
@endcanany
