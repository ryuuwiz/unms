<div class="mx-auto max-w-3xl space-y-6">
    <div>
        <flux:heading size="xl">Tambah ODP Baru</flux:heading>
        <flux:subheading>Daftarkan titik terminasi Optical Distribution Point baru dan tentukan koordinat lokasi.</flux:subheading>
    </div>

    <flux:separator />

    <form wire:submit="save" class="space-y-6">
        <div class="space-y-4">
            <flux:heading size="base">Informasi ODP</flux:heading>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <flux:field>
                    <flux:label>Nama / Kode ODP</flux:label>
                    <flux:input wire:model="nama_odp" placeholder="Contoh: ODP-MLT-01" autofocus />
                    <flux:description>Identifier unik ODP (contoh: ODP-CLUSTER-01).</flux:description>
                    <flux:error name="nama_odp" />
                </flux:field>

                <flux:field>
                    <flux:label>Kapasitas Port</flux:label>
                    <flux:select wire:model="kapasitas_port">
                        <flux:select.option value="4">4 Port (1:4 Splitter)</flux:select.option>
                        <flux:select.option value="8">8 Port (1:8 Splitter)</flux:select.option>
                        <flux:select.option value="16">16 Port (1:16 Splitter)</flux:select.option>
                        <flux:select.option value="24">24 Port</flux:select.option>
                        <flux:select.option value="32">32 Port (1:32 Splitter)</flux:select.option>
                    </flux:select>
                    <flux:description>Port 1..N akan otomatis di-generate berstatus kosong.</flux:description>
                    <flux:error name="kapasitas_port" />
                </flux:field>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <flux:field>
                    <flux:label>Perumahan / Area Coverage <span class="text-zinc-400 font-normal">(opsional)</span></flux:label>
                    <flux:select wire:model="perumahan_id" placeholder="Pilih perumahan...">
                        <flux:select.option :value="null">Bukan di perumahan</flux:select.option>
                        @foreach ($perumahans as $prm)
                            <flux:select.option value="{{ $prm->id }}">{{ $prm->nama_perumahan }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="perumahan_id" />
                </flux:field>

                <flux:field>
                    <flux:label>Keterangan / PON Info <span class="text-zinc-400 font-normal">(opsional)</span></flux:label>
                    <flux:input wire:model="keterangan" placeholder="Contoh: PON-1 Tiang PLN No. 12" />
                    <flux:error name="keterangan" />
                </flux:field>
            </div>
        </div>

        <flux:separator />

        {{-- Titik Koordinat & Map Picker --}}
        <div class="space-y-4">
            <flux:heading size="base">Titik Koordinat Lokasi ODP</flux:heading>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <flux:field>
                    <flux:label>Latitude</flux:label>
                    <flux:input wire:model="latitude" placeholder="-6.2088000" />
                    <flux:error name="latitude" />
                </flux:field>

                <flux:field>
                    <flux:label>Longitude</flux:label>
                    <flux:input wire:model="longitude" placeholder="106.8456000" />
                    <flux:error name="longitude" />
                </flux:field>
            </div>

            <x-map-picker :lat="$latitude" :lng="$longitude" height="300px" />
        </div>

        <flux:separator />

        <div class="flex items-center justify-end gap-3 pt-2">
            <flux:button :href="route('odp.index')" wire:navigate variant="ghost">Batal</flux:button>
            <flux:button type="submit" variant="primary" icon="check">Simpan ODP</flux:button>
        </div>
    </form>
</div>
