<div class="mx-auto max-w-3xl space-y-6">
    <div>
        <flux:heading size="xl">Tambah Perumahan / Cluster</flux:heading>
        <flux:subheading>Daftarkan perumahan atau cluster baru dengan hierarki wilayah dan titik koordinat peta.</flux:subheading>
    </div>

    <flux:separator />

    <form wire:submit="save" class="space-y-6">
        {{-- Hierarki Wilayah --}}
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <flux:field>
                <flux:label>Kota / Kabupaten</flux:label>
                <flux:select wire:model.live="kota_id" placeholder="Pilih Kota / Kabupaten">
                    <flux:select.option value="">Pilih Kota</flux:select.option>
                    @foreach ($kotas as $kota)
                        <flux:select.option value="{{ $kota->id }}">{{ $kota->nama_kota }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="kota_id" />
            </flux:field>

            <flux:field>
                <flux:label>Kecamatan</flux:label>
                <flux:select wire:model.live="kecamatan_id" placeholder="Pilih Kecamatan" :disabled="!$kota_id">
                    <flux:select.option value="">Pilih Kecamatan</flux:select.option>
                    @foreach ($kecamatans as $kecamatan)
                        <flux:select.option value="{{ $kecamatan->id }}">{{ $kecamatan->nama_kecamatan }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="kecamatan_id" />
            </flux:field>

            <flux:field>
                <flux:label>Kelurahan Induk</flux:label>
                <flux:select wire:model="kelurahan_id" placeholder="Pilih Kelurahan" :disabled="!$kecamatan_id">
                    <flux:select.option value="">Pilih Kelurahan</flux:select.option>
                    @foreach ($kelurahans as $kelurahan)
                        <flux:select.option value="{{ $kelurahan->id }}">{{ $kelurahan->nama_kelurahan }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="kelurahan_id" />
            </flux:field>
        </div>

        {{-- Nama & Singkatan --}}
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <flux:field class="sm:col-span-2">
                <flux:label>Nama Perumahan / Cluster</flux:label>
                <flux:input wire:model="nama_perumahan" placeholder="Contoh: Griya Bandung Indah" />
                <flux:error name="nama_perumahan" />
            </flux:field>

            <flux:field class="sm:col-span-1">
                <flux:label>Singkatan / Kode <span class="text-zinc-400 font-normal">(opsional)</span></flux:label>
                <flux:input wire:model.blur="singkatan" placeholder="Contoh: GBI" maxlength="10" />
                <flux:description>Digunakan sebagai prefix penamaan ODP/Site.</flux:description>
                <flux:error name="singkatan" />
            </flux:field>
        </div>

        {{-- Koordinat & Peta --}}
        <div class="space-y-4 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
            <div class="flex items-center justify-between">
                <div>
                    <flux:heading size="sm">Titik Pusat / Gerbang Perumahan</flux:heading>
                    <flux:subheading>Tentukan koordinat sentral cluster perumahan pada peta untuk mempermudah survey teknisi.</flux:subheading>
                </div>
            </div>

            <x-map-picker :lat="$lat" :lng="$lng" height="300px" />

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <flux:field>
                    <flux:label>Latitude</flux:label>
                    <flux:input wire:model.live="lat" type="number" step="any" placeholder="-6.9663450" />
                    <flux:error name="lat" />
                </flux:field>

                <flux:field>
                    <flux:label>Longitude</flux:label>
                    <flux:input wire:model.live="lng" type="number" step="any" placeholder="107.6698120" />
                    <flux:error name="lng" />
                </flux:field>
            </div>
        </div>

        <flux:field>
            <flux:label>Keterangan <span class="text-zinc-400 font-normal">(opsional)</span></flux:label>
            <flux:textarea wire:model="keterangan" rows="3" placeholder="Informasi blok perumahan, akses masuk, PIC lapangan..." />
            <flux:error name="keterangan" />
        </flux:field>

        <div class="flex items-center justify-end gap-3 pt-2">
            <flux:button :href="route('wilayah.perumahan.index')" wire:navigate variant="ghost">Batal</flux:button>
            <flux:button type="submit" variant="primary" icon="check">Simpan Perumahan</flux:button>
        </div>
    </form>
</div>
