<div class="mx-auto max-w-2xl space-y-6">
    <div>
        <flux:heading size="xl">Tambah Barang</flux:heading>
        <flux:subheading>Kode barang akan digenerate otomatis dari Jenis, Kondisi, dan Cabang yang dipilih.</flux:subheading>
    </div>

    <flux:separator />

    <form wire:submit="save" class="space-y-6">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <flux:field>
                <flux:label>Jenis Barang</flux:label>
                <flux:select wire:model="jenis_barang_id" placeholder="Pilih jenis...">
                    <flux:select.option value="">-- Pilih Jenis --</flux:select.option>
                    @foreach ($jenisList as $jenis)
                        <flux:select.option value="{{ $jenis->id }}">{{ $jenis->kode }} — {{ $jenis->nama }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="jenis_barang_id" />
            </flux:field>

            <flux:field>
                <flux:label>Kondisi</flux:label>
                <flux:select wire:model="kondisi_barang_id" placeholder="Pilih kondisi...">
                    <flux:select.option value="">-- Pilih Kondisi --</flux:select.option>
                    @foreach ($kondisiList as $kondisi)
                        <flux:select.option value="{{ $kondisi->id }}">{{ $kondisi->kode }} — {{ $kondisi->nama }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="kondisi_barang_id" />
            </flux:field>

            <flux:field>
                <flux:label>Cabang</flux:label>
                <flux:select wire:model="cabang_barang_id" placeholder="Pilih cabang...">
                    <flux:select.option value="">-- Pilih Cabang --</flux:select.option>
                    @foreach ($cabangList as $cabang)
                        <flux:select.option value="{{ $cabang->id }}">{{ $cabang->kode }} — {{ $cabang->nama }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="cabang_barang_id" />
            </flux:field>
        </div>

        <flux:field>
            <flux:label>Nama Barang</flux:label>
            <flux:input wire:model="nama_barang" placeholder="Contoh: Modem ZTE F609" autofocus />
            <flux:error name="nama_barang" />
        </flux:field>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <flux:field>
                <flux:label>Satuan</flux:label>
                <flux:input wire:model="satuan" placeholder="unit" />
                <flux:error name="satuan" />
            </flux:field>

            <flux:field>
                <flux:label>Stok Awal</flux:label>
                <flux:input wire:model="stok" type="number" min="0" placeholder="0" />
                <flux:error name="stok" />
            </flux:field>
        </div>

        <flux:field>
            <flux:label>Keterangan <span class="text-zinc-400 font-normal">(opsional)</span></flux:label>
            <flux:textarea wire:model="keterangan" rows="3" placeholder="Keterangan tambahan..." />
            <flux:error name="keterangan" />
        </flux:field>

        <div class="flex items-center justify-end gap-3 pt-2">
            <flux:button :href="route('barang.index')" wire:navigate variant="ghost">Batal</flux:button>
            <flux:button type="submit" variant="primary" icon="check">Simpan Barang</flux:button>
        </div>
    </form>
</div>
