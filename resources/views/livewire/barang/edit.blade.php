<div class="mx-auto max-w-2xl space-y-6">
    <div>
        <flux:heading size="xl">Ubah Barang</flux:heading>
        <flux:subheading>
            Kode Barang: <span class="font-mono font-semibold">{{ $barang->kode_barang }}</span>
            ({{ $barang->jenisBarang->kode }}-{{ $barang->kondisiBarang->kode }}-{{ $barang->cabangBarang->kode }}, tidak dapat diubah)
        </flux:subheading>
    </div>

    <flux:separator />

    <form wire:submit="save" class="space-y-6">
        <flux:field>
            <flux:label>Nama Barang</flux:label>
            <flux:input wire:model="nama_barang" autofocus />
            <flux:error name="nama_barang" />
        </flux:field>

        <flux:field>
            <flux:label>Satuan</flux:label>
            <flux:input wire:model="satuan" />
            <flux:error name="satuan" />
        </flux:field>

        <flux:field>
            <flux:label>Keterangan <span class="text-zinc-400 font-normal">(opsional)</span></flux:label>
            <flux:textarea wire:model="keterangan" rows="3" />
            <flux:error name="keterangan" />
        </flux:field>

        <flux:field>
            <flux:checkbox wire:model="is_active" label="Status Aktif" />
            <flux:description>Barang nonaktif tidak muncul di dropdown pencatatan barang masuk/keluar.</flux:description>
        </flux:field>

        <div class="rounded-lg bg-zinc-50 dark:bg-zinc-800/60 p-4 text-sm text-zinc-600 dark:text-zinc-400">
            Stok saat ini: <span class="font-semibold text-zinc-900 dark:text-zinc-100">{{ number_format($barang->stok) }} {{ $barang->satuan }}</span>.
            Ubah stok melalui menu Barang Masuk / Barang Keluar agar riwayat mutasi tetap tercatat.
        </div>

        <div class="flex items-center justify-end gap-3 pt-2">
            <flux:button :href="route('barang.index')" wire:navigate variant="ghost">Batal</flux:button>
            <flux:button type="submit" variant="primary" icon="check">Simpan Perubahan</flux:button>
        </div>
    </form>
</div>
