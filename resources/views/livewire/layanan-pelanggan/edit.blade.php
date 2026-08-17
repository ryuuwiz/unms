<div class="mx-auto max-w-2xl space-y-6">
    <div>
        <flux:heading size="xl">Edit Layanan Pelanggan</flux:heading>
        <flux:subheading>Perbarui konfigurasi paket, router, kredensial PPP, atau status layanan.</flux:subheading>
    </div>

    <flux:separator />

    <form wire:submit="save" class="space-y-6">
        <flux:field>
            <flux:label>Paket Layanan</flux:label>
            <flux:select wire:model="paket_layanan_id">
                @foreach ($pakets as $pk)
                    <flux:select.option value="{{ $pk->id }}">
                        {{ $pk->nama_paket }} — {{ $pk->formattedHarga() }}
                    </flux:select.option>
                @endforeach
            </flux:select>
            <flux:error name="paket_layanan_id" />
        </flux:field>

        <flux:field>
            <flux:label>Router Gateway</flux:label>
            <flux:select wire:model="router_id">
                @foreach ($routers as $r)
                    <flux:select.option value="{{ $r->id }}">
                        {{ $r->nama_router }} ({{ $r->ip_address }})
                    </flux:select.option>
                @endforeach
            </flux:select>
            <flux:error name="router_id" />
        </flux:field>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <flux:field>
                <flux:label>Username PPP</flux:label>
                <flux:input wire:model="ppp_username" />
                <flux:error name="ppp_username" />
            </flux:field>

            <flux:field>
                <flux:label>Password PPP <span class="text-zinc-400 font-normal">(isi jika ingin ubah)</span></flux:label>
                <flux:input wire:model="ppp_password" type="password" placeholder="Kosongkan jika tidak diubah" />
                <flux:error name="ppp_password" />
            </flux:field>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <flux:field>
                <flux:label>Jenis Koneksi</flux:label>
                <flux:select wire:model="jenis_koneksi">
                    @foreach ($jenisKoneksi as $jk)
                        <flux:select.option value="{{ $jk->value }}">{{ $jk->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="jenis_koneksi" />
            </flux:field>

            <flux:field>
                <flux:label>Status Layanan</flux:label>
                <flux:select wire:model="status">
                    @foreach ($statuses as $st)
                        <flux:select.option value="{{ $st->value }}">{{ $st->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="status" />
            </flux:field>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <flux:field>
                <flux:label>Tanggal Mulai</flux:label>
                <flux:input wire:model="tanggal_mulai" type="date" />
                <flux:error name="tanggal_mulai" />
            </flux:field>

            <flux:field>
                <flux:label>Tanggal Jatuh Tempo (Expired)</flux:label>
                <flux:input wire:model="tanggal_expired" type="date" />
                <flux:error name="tanggal_expired" />
            </flux:field>
        </div>

        <div class="flex items-center justify-end gap-3 pt-2">
            <flux:button :href="route('layanan-pelanggan.index')" wire:navigate variant="ghost">Batal</flux:button>
            <flux:button type="submit" variant="primary" icon="check">Perbarui Layanan</flux:button>
        </div>
    </form>
</div>
