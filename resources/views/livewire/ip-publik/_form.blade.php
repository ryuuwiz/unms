@if ($terpakai)
    <flux:callout variant="warning" icon="exclamation-triangle">
        IP ini sedang dipakai layanan. Router dan alamat IP terkunci; harga baru hanya berlaku untuk penetapan berikutnya.
    </flux:callout>
@endif

<flux:field>
    <flux:label>Router Gateway</flux:label>
    <flux:select wire:model="router_id" :disabled="$terpakai">
        <flux:select.option value="" disabled>Pilih router...</flux:select.option>
        @foreach ($routers as $router)
            <flux:select.option value="{{ $router->id }}">{{ $router->nama_router }} ({{ $router->ip_address }})</flux:select.option>
        @endforeach
    </flux:select>
    <flux:error name="router_id" />
</flux:field>

<div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
    <flux:field>
        <flux:label>Alamat IP Publik</flux:label>
        <flux:input wire:model="alamat_ip" placeholder="Contoh: 203.0.113.10" :disabled="$terpakai" />
        <flux:description>Tidak boleh berada di dalam rentang IP Pool router yang sama.</flux:description>
        <flux:error name="alamat_ip" />
    </flux:field>

    <flux:field>
        <flux:label>Gateway (local-address)</flux:label>
        <flux:input wire:model="gateway" placeholder="Contoh: 203.0.113.1" />
        <flux:error name="gateway" />
    </flux:field>
</div>

<flux:field>
    <flux:label>Harga Bulanan (Rp)</flux:label>
    <flux:input wire:model="harga_bulanan" type="number" min="0" step="1000" />
    <flux:description>Ditagih setiap periode bersama tagihan paket, mulai invoice periodik berikutnya sejak IP dipasang.</flux:description>
    <flux:error name="harga_bulanan" />
</flux:field>

<flux:field>
    <flux:label>Keterangan</flux:label>
    <flux:input wire:model="keterangan" placeholder="Opsional" />
    <flux:error name="keterangan" />
</flux:field>
