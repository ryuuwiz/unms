<div class="mx-auto max-w-2xl space-y-6">
    <div>
        <flux:heading size="xl">Edit IP Pool</flux:heading>
        <flux:subheading>Perbarui konfigurasi IP pool {{ $nama_pool }}.</flux:subheading>
    </div>

    <flux:separator />

    <form wire:submit="save" class="space-y-6">
        <flux:field>
            <flux:label>Nama Pool</flux:label>
            <flux:input wire:model="nama_pool" placeholder="Contoh: Pool-Rumahan-01" />
            <flux:description>Huruf, angka, strip, dan underscore saja (tanpa spasi) -- nama ini dipakai langsung sebagai remote-address PPP Secret di MikroTik.</flux:description>
            <flux:error name="nama_pool" />
        </flux:field>

        <flux:field>
            <flux:label>Router Gateway</flux:label>
            <flux:select wire:model="router_id">
                @foreach ($routers as $router)
                    <flux:select.option value="{{ $router->id }}">{{ $router->nama_router }} ({{ $router->ip_address }})</flux:select.option>
                @endforeach
            </flux:select>
            <flux:error name="router_id" />
        </flux:field>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <flux:field class="sm:col-span-2">
                <flux:label>IP Network</flux:label>
                <flux:input wire:model="ip_network" />
                <flux:error name="ip_network" />
            </flux:field>

            <flux:field class="sm:col-span-1">
                <flux:label>CIDR</flux:label>
                <flux:input wire:model="cidr" type="number" min="1" max="32" />
                <flux:error name="cidr" />
            </flux:field>
        </div>

        <div class="flex justify-start">
            <flux:button type="button" wire:click="generateRange" size="sm" variant="subtle" icon="calculator">
                Hitung Otomatis Rentang IP
            </flux:button>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <flux:field>
                <flux:label>Rentang IP Awal</flux:label>
                <flux:input wire:model="rentang_ip_awal" />
                <flux:error name="rentang_ip_awal" />
            </flux:field>

            <flux:field>
                <flux:label>Rentang IP Akhir</flux:label>
                <flux:input wire:model="rentang_ip_akhir" />
                <flux:error name="rentang_ip_akhir" />
            </flux:field>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <flux:field>
                <flux:label>Priority TX (1-8)</flux:label>
                <flux:input wire:model="priority_tx" type="number" min="1" max="8" />
                <flux:error name="priority_tx" />
            </flux:field>

            <flux:field>
                <flux:label>Priority RX (1-8)</flux:label>
                <flux:input wire:model="priority_rx" type="number" min="1" max="8" />
                <flux:error name="priority_rx" />
            </flux:field>
        </div>

        <div class="flex items-center justify-end gap-3 pt-2">
            <flux:button :href="route('ip-pool.index')" wire:navigate variant="ghost">Batal</flux:button>
            <flux:button type="submit" variant="primary" icon="check">Perbarui IP Pool</flux:button>
        </div>
    </form>
</div>
