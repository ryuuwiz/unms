<div class="space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl">Channel Pembayaran</flux:heading>
            <flux:subheading>Channel yang bisa dipilih pelanggan di halaman tagihan. Hanya channel berstatus ON yang tampil; bila tidak ada, pelanggan diarahkan ke Hosted Invoice iPaymu.</flux:subheading>
        </div>
        @can('payment_gateway.buat')
            <flux:button :href="route('settings.metode-pembayaran.create')" wire:navigate variant="primary" icon="plus">
                Tambah Channel
            </flux:button>
        @endcan
    </div>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>Channel</flux:table.column>
            <flux:table.column>Gateway</flux:table.column>
            <flux:table.column>Tipe</flux:table.column>
            <flux:table.column>Fee Admin</flux:table.column>
            <flux:table.column>Status</flux:table.column>
            <flux:table.column align="end">Aksi</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($channels as $channel)
                <flux:table.row :key="$channel->id">
                    <flux:table.cell>
                        <div class="flex items-center gap-3">
                            @if ($channel->icon_url)
                                <img src="{{ $channel->icon_url }}" alt="" class="h-6 w-10 object-contain" loading="lazy">
                            @endif
                            <div>
                                <div class="font-mono font-medium text-zinc-900 dark:text-zinc-100">{{ $channel->kode }}</div>
                                @if ($channel->keterangan)
                                    <div class="text-xs text-zinc-500">{{ $channel->keterangan }}</div>
                                @endif
                            </div>
                        </div>
                    </flux:table.cell>
                    <flux:table.cell>
                        <flux:badge size="sm" color="zinc">{{ $channel->pengaturanGateway->nama }}</flux:badge>
                    </flux:table.cell>
                    <flux:table.cell>
                        <flux:badge size="sm" :color="$channel->tipe->color()">{{ $channel->tipe->label() }}</flux:badge>
                    </flux:table.cell>
                    <flux:table.cell>{{ $channel->labelFee() }}</flux:table.cell>
                    <flux:table.cell>
                        @can('payment_gateway.ubah')
                            <flux:switch :checked="$channel->is_active" wire:click="toggleStatus({{ $channel->id }})" />
                        @else
                            <flux:badge size="sm" :color="$channel->is_active ? 'green' : 'zinc'">{{ $channel->is_active ? 'ON' : 'OFF' }}</flux:badge>
                        @endcan
                    </flux:table.cell>
                    <flux:table.cell align="end">
                        <div class="flex items-center justify-end gap-1">
                            @can('payment_gateway.ubah')
                                <flux:button :href="route('settings.metode-pembayaran.edit', $channel)" wire:navigate size="sm" variant="ghost" icon="pencil-square" title="Edit Metode" />
                            @endcan
                            @can('payment_gateway.hapus')
                                <flux:button wire:click="hapus({{ $channel->id }})" wire:confirm="Hapus channel '{{ $channel->kode }}'?" size="sm" variant="ghost" icon="trash" class="text-red-600 hover:text-red-700 dark:text-red-400" title="Hapus Channel" />
                            @endcan
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="6" class="py-12 text-center text-zinc-500">
                        <div class="flex flex-col items-center gap-2">
                            <flux:icon name="credit-card" class="size-8 text-zinc-300 dark:text-zinc-600" />
                            <p class="font-medium">Belum ada Channel Pembayaran. Pelanggan memakai Hosted Invoice iPaymu.</p>
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</div>
