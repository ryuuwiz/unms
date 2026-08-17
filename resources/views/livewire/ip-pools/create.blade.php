<div class="max-w-3xl mx-auto space-y-6">
    <div>
        <flux:heading size="xl">Tambah IP Pool</flux:heading>
        <flux:subheading>Tentukan network, limitasi bandwidth, dan router target.</flux:subheading>
    </div>

    <form wire:submit="save" class="space-y-6">
        <flux:card>
            <div class="space-y-6">
                
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                    <flux:field>
                        <flux:label>Nama Pool</flux:label>
                        <flux:input wire:model="name" placeholder="Mis: PPP_POOL_RUMAH" />
                        <flux:error name="name" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Target Router</flux:label>
                        <flux:select wire:model="mikrotik_router_id" placeholder="Pilih router...">
                            @foreach($routers as $router)
                                <flux:select.option value="{{ $router->id }}">{{ $router->name }} ({{ $router->ip_address }})</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:error name="mikrotik_router_id" />
                    </flux:field>
                </div>

                <div class="grid grid-cols-3 gap-4 items-start">
                    <div class="col-span-2">
                        <flux:field>
                            <flux:label>IP Network</flux:label>
                            <flux:input wire:model="ip_network" placeholder="Mis: 192.168.88.0" />
                            <flux:description>Gunakan IP Calculator jika ragu.</flux:description>
                            <flux:error name="ip_network" />
                        </flux:field>
                    </div>
                    <div class="col-span-1">
                        <flux:field>
                            <flux:label>CIDR</flux:label>
                            <div class="flex items-center gap-2">
                                <span class="text-zinc-500 font-medium">/</span>
                                <flux:input type="number" wire:model="cidr" placeholder="24" min="1" max="32" class="flex-1" />
                            </div>
                            <flux:error name="cidr" />
                        </flux:field>
                    </div>
                </div>

                <div class="border-t border-zinc-200 dark:border-zinc-700 py-2"></div>

                <div>
                    <div class="flex items-center justify-between mb-2">
                        <flux:label>Rentang IP (Opsional)</flux:label>
                        <flux:button type="button" wire:click="generateRange" size="sm" variant="subtle" icon="sparkles">
                            Gunakan range saran
                        </flux:button>
                    </div>
                    
                    <div class="flex items-center gap-4">
                        <flux:field class="flex-1">
                            <flux:input wire:model="ip_range_start" placeholder="IP Awal (Mis: 192.168.88.2)" />
                            <flux:error name="ip_range_start" />
                        </flux:field>
                        <span class="text-zinc-400 font-medium mt-1">-</span>
                        <flux:field class="flex-1">
                            <flux:input wire:model="ip_range_end" placeholder="IP Akhir (Mis: 192.168.88.254)" />
                            <flux:error name="ip_range_end" />
                        </flux:field>
                    </div>
                    <p class="text-sm text-zinc-500 mt-2">Jika kosong, sistem hanya menyimpan IP Network & Queue.</p>
                </div>

                <div class="border-t border-zinc-200 dark:border-zinc-700 py-2"></div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                    <flux:field>
                        <flux:label>Queue Limit TX (Mbps)</flux:label>
                        <flux:input type="number" step="0.01" wire:model="queue_tx_mbps" placeholder="Mis: 10" />
                        <flux:error name="queue_tx_mbps" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Queue Limit RX (Mbps)</flux:label>
                        <flux:input type="number" step="0.01" wire:model="queue_rx_mbps" placeholder="Mis: 10" />
                        <flux:error name="queue_rx_mbps" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Priority TX</flux:label>
                        <flux:select wire:model="priority_tx">
                            @for ($i = 1; $i <= 8; $i++)
                                <flux:select.option value="{{ $i }}">{{ $i }} {{ $i === 1 ? '(Tertinggi)' : ($i === 8 ? '(Terendah)' : '') }}</flux:select.option>
                            @endfor
                        </flux:select>
                        <flux:error name="priority_tx" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Priority RX</flux:label>
                        <flux:select wire:model="priority_rx">
                            @for ($i = 1; $i <= 8; $i++)
                                <flux:select.option value="{{ $i }}">{{ $i }} {{ $i === 1 ? '(Tertinggi)' : ($i === 8 ? '(Terendah)' : '') }}</flux:select.option>
                            @endfor
                        </flux:select>
                        <flux:error name="priority_rx" />
                    </flux:field>
                </div>
            </div>
        </flux:card>

        <div class="flex items-center justify-end gap-3">
            <flux:button :href="route('ip-pools.index')" wire:navigate variant="ghost">Batal</flux:button>
            <flux:button type="submit" variant="primary">Simpan IP Pool</flux:button>
        </div>
    </form>
</div>
