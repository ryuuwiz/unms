<div class="space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl">Detail Paket PPPoE</flux:heading>
            <flux:subheading>Informasi paket dan router yang terhubung.</flux:subheading>
        </div>
        <flux:button :href="route('paket-layanan.index')" wire:navigate variant="ghost" icon="arrow-left">Kembali</flux:button>
    </div>

    {{-- Info Paket --}}
    <flux:card class="p-6">
        <dl class="grid grid-cols-1 gap-4 sm:grid-cols-4">
            <div>
                <dt class="text-sm text-zinc-500">Nama Paket</dt>
                <dd class="font-semibold text-zinc-900 dark:text-white">{{ $paketLayanan->nama_paket }}</dd>
            </div>
            <div>
                <dt class="text-sm text-zinc-500">Harga</dt>
                <dd class="font-semibold text-zinc-900 dark:text-white">{{ $paketLayanan->formattedHarga() }}</dd>
            </div>
            <div>
                <dt class="text-sm text-zinc-500">Masa Aktif</dt>
                <dd class="font-semibold text-zinc-900 dark:text-white">{{ $paketLayanan->labelMasaAktif() }}</dd>
            </div>
            <div>
                <dt class="text-sm text-zinc-500">Keterangan</dt>
                <dd class="text-zinc-700 dark:text-zinc-300">{{ $paketLayanan->keterangan ?: '-' }}</dd>
            </div>
        </dl>
    </flux:card>

    {{-- Router pada Paket Ini --}}
    <flux:card class="space-y-4 p-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <flux:heading size="lg">Router pada Paket Ini</flux:heading>
                <flux:subheading>Management router untuk paket {{ $paketLayanan->nama_paket }}. Profile PPP bernama paket dibuat di setiap router.</flux:subheading>
            </div>
            @if ($bisaKelola)
                <flux:button variant="primary" icon="plus" wire:click="openCreateModal">Tambah Router</flux:button>
            @endif
        </div>

        <flux:table>
            <flux:table.columns>
                <flux:table.column>Nama Router</flux:table.column>
                <flux:table.column>IP Address</flux:table.column>
                <flux:table.column>IP Pool</flux:table.column>
                <flux:table.column>Waktu Aktif</flux:table.column>
                <flux:table.column>Deskripsi</flux:table.column>
                @if ($bisaKelola)
                    <flux:table.column class="text-right">Aksi</flux:table.column>
                @endif
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($paketLayanan->routerPakets as $routerPaket)
                    <flux:table.row :key="$routerPaket->id">
                        <flux:table.cell class="font-medium">{{ $routerPaket->router->nama_router }}</flux:table.cell>
                        <flux:table.cell class="font-mono">{{ $routerPaket->router->ip_address }}</flux:table.cell>
                        <flux:table.cell>
                            {{ $routerPaket->ipPool->nama_pool }}
                            <span class="block font-mono text-xs text-zinc-500">{{ $routerPaket->ipPool->labelNetwork() }}</span>
                        </flux:table.cell>
                        <flux:table.cell>{{ $routerPaket->router->uptime ?: '-' }}</flux:table.cell>
                        <flux:table.cell class="text-zinc-600 dark:text-zinc-400">{{ $routerPaket->deskripsi ?: '-' }}</flux:table.cell>
                        @if ($bisaKelola)
                            <flux:table.cell class="text-right">
                                <div class="flex items-center justify-end gap-1">
                                    <flux:button variant="ghost" size="sm" icon="pencil-square" wire:click="openEditModal({{ $routerPaket->id }})" title="Ubah" />
                                    <flux:button variant="ghost" size="sm" icon="trash" wire:click="hapus({{ $routerPaket->id }})"
                                        wire:confirm="Hapus router {{ $routerPaket->router->nama_router }} dari paket ini?" title="Hapus" />
                                </div>
                            </flux:table.cell>
                        @endif
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6" class="py-8 text-center text-zinc-500">
                            Belum ada router. Paket ini belum bisa dipilih NOC untuk pelanggan mana pun.
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    {{-- Tambah / Ubah Router ke Paket PPPoE --}}
    <flux:modal wire:model="showModal" class="max-w-md">
        <form wire:submit="simpan" class="space-y-6">
            <div>
                <flux:heading size="lg">Konfigurasi Router untuk Paket</flux:heading>
                <flux:subheading>Tambahkan router yang akan menggunakan profil PPPoE paket ini.</flux:subheading>
            </div>

            <flux:input label="Nama Paket" :value="$paketLayanan->nama_paket" readonly
                description="Nama paket referensi untuk profil PPPoE di Mikrotik." />

            <flux:field>
                <flux:label>Nama Router</flux:label>
                <flux:select wire:model.live="router_id" placeholder="Pilih router...">
                    <flux:select.option value="">Pilih router...</flux:select.option>
                    @foreach ($routers as $router)
                        <flux:select.option value="{{ $router->id }}">{{ $router->nama_router }} ({{ $router->ip_address }})</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:description>Router Mikrotik tempat profil PPPoE paket ini akan dibuat.</flux:description>
                <flux:error name="router_id" />
            </flux:field>

            <flux:field>
                <flux:label>IP Pool</flux:label>
                <flux:select wire:model="ip_pool_id" placeholder="Pilih IP pool..." :disabled="! $router_id">
                    <flux:select.option value="">Pilih IP pool...</flux:select.option>
                    @foreach ($ipPools as $pool)
                        <flux:select.option value="{{ $pool->id }}">{{ $pool->nama_pool }} ({{ $pool->labelNetwork() }})</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:description>Pool mengikuti router yang dipilih. Dipakai sebagai local-address (.1) dan remote-address di profil PPPoE.</flux:description>
                <flux:error name="ip_pool_id" />
            </flux:field>

            <flux:textarea wire:model="deskripsi" label="Deskripsi" rows="2"
                placeholder="Opsional. Misal: lokasi router, uplink, port, catatan teknis." />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">Batal</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">Simpan</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
