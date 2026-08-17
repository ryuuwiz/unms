<div class="mx-auto max-w-2xl space-y-6">
    <div>
        <flux:heading size="xl">Edit Paket Layanan</flux:heading>
        <flux:subheading>Perbarui konfigurasi paket {{ $nama_paket }}.</flux:subheading>
    </div>

    <flux:separator />

    <form wire:submit="save" class="space-y-6">
        <flux:field>
            <flux:label>Nama Paket</flux:label>
            <flux:input wire:model="nama_paket" placeholder="Contoh: Paket Home 20 Mbps" />
            <flux:error name="nama_paket" />
        </flux:field>

        <flux:field>
            <flux:label>Profil Bandwidth</flux:label>
            <flux:select wire:model="profil_bandwidth_id" placeholder="Pilih profil bandwidth...">
                @foreach ($profils as $profil)
                    <flux:select.option value="{{ $profil->id }}">
                        {{ $profil->nama_bandwidth }} ({{ $profil->labelKecepatan() }})
                    </flux:select.option>
                @endforeach
            </flux:select>
            <flux:error name="profil_bandwidth_id" />
        </flux:field>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <flux:field class="sm:col-span-1">
                <flux:label>Harga (Rupiah)</flux:label>
                <flux:input wire:model="harga" type="number" placeholder="250000" />
                <flux:error name="harga" />
            </flux:field>

            <flux:field class="sm:col-span-1">
                <flux:label>Masa Aktif Nilai</flux:label>
                <flux:input wire:model="masa_aktif_nilai" type="number" min="1" placeholder="1" />
                <flux:error name="masa_aktif_nilai" />
            </flux:field>

            <flux:field class="sm:col-span-1">
                <flux:label>Satuan</flux:label>
                <flux:select wire:model="masa_aktif_satuan">
                    @foreach ($satuans as $satuan)
                        <flux:select.option value="{{ $satuan->value }}">{{ $satuan->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="masa_aktif_satuan" />
            </flux:field>
        </div>

        <flux:field>
            <flux:label>Status</flux:label>
            <flux:select wire:model="status">
                @foreach ($statuses as $st)
                    <flux:select.option value="{{ $st->value }}">{{ $st->label() }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:error name="status" />
        </flux:field>

        <flux:field>
            <flux:label>Keterangan <span class="text-zinc-400 font-normal">(opsional)</span></flux:label>
            <flux:textarea wire:model="keterangan" rows="3" placeholder="Keterangan atau benefit paket..." />
            <flux:error name="keterangan" />
        </flux:field>

        <div class="flex items-center justify-end gap-3 pt-2">
            <flux:button :href="route('paket-layanan.index')" wire:navigate variant="ghost">Batal</flux:button>
            <flux:button type="submit" variant="primary" icon="check">Perbarui Paket</flux:button>
        </div>
    </form>
</div>
