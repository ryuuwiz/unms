<div class="mx-auto max-w-2xl space-y-6">
    <div>
        <flux:heading size="xl">Tambah Router</flux:heading>
        <flux:subheading>Daftarkan router MikroTik baru dengan konfigurasi RouterOS API.</flux:subheading>
    </div>

    <flux:separator />

    <form wire:submit="save" class="space-y-6">
        <flux:field>
            <flux:label>Nama Router</flux:label>
            <flux:input wire:model="nama_router" placeholder="Contoh: RB4011-Core" autofocus />
            <flux:error name="nama_router" />
        </flux:field>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <flux:field class="sm:col-span-2">
                <flux:label>Alamat IP Router</flux:label>
                <flux:input wire:model="ip_address" placeholder="192.168.88.1" />
                <flux:error name="ip_address" />
            </flux:field>

            <flux:field class="sm:col-span-1">
                <flux:label>Port API</flux:label>
                <flux:input wire:model="port" type="number" placeholder="8728" />
                <flux:error name="port" />
            </flux:field>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <flux:field>
                <flux:label>Username API</flux:label>
                <flux:input wire:model="username" placeholder="admin" />
                <flux:error name="username" />
            </flux:field>

            <flux:field>
                <flux:label>Password API (Terenkripsi)</flux:label>
                <flux:input wire:model="password" type="password" />
                <flux:error name="password" />
            </flux:field>
        </div>

        <flux:field>
            <flux:label>Deskripsi <span class="text-zinc-400 font-normal">(opsional)</span></flux:label>
            <flux:textarea wire:model="deskripsi" rows="3" placeholder="Lokasi fisik, tipe perangkat, dll..." />
            <flux:error name="deskripsi" />
        </flux:field>

        <div class="flex items-center justify-end gap-3 pt-2">
            <flux:button :href="route('router.index')" wire:navigate variant="ghost">Batal</flux:button>
            <flux:button type="submit" variant="primary" icon="check">Simpan Router</flux:button>
        </div>
    </form>
</div>
