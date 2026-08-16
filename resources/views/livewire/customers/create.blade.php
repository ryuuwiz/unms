<div class="mx-auto max-w-3xl space-y-6">
    <div>
        <flux:heading size="xl">Tambah Pelanggan</flux:heading>
        <flux:subheading>Daftarkan data master pelanggan baru dan tentukan titik lokasi pemasangan jaringan.</flux:subheading>
    </div>

    <flux:separator />

    <form wire:submit="save" class="space-y-6">
        {{-- Section 1: Identitas & Kontak --}}
        <div class="space-y-4">
            <flux:heading size="base">Identitas & Kontak</flux:heading>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <flux:field>
                    <flux:label>Kode Pelanggan</flux:label>
                    <flux:input value="CUST-XXXXXX (Otomatis)" disabled class="bg-zinc-100 dark:bg-zinc-800" />
                    <flux:description>Kode unik 6-digit akan digenerate otomatis oleh sistem.</flux:description>
                </flux:field>

                <flux:field>
                    <flux:label>Nama Lengkap</flux:label>
                    <flux:input wire:model="name" placeholder="Contoh: Budi Santoso" autofocus />
                    <flux:error name="name" />
                </flux:field>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <flux:field>
                    <flux:label>Nomor WhatsApp / HP</flux:label>
                    <flux:input wire:model="phone" type="tel" placeholder="08123456789 atau +628123456789" />
                    <flux:description>Digunakan untuk notifikasi WA dan koordinasi teknisi.</flux:description>
                    <flux:error name="phone" />
                </flux:field>

                <flux:field>
                    <flux:label>Alamat Email <span class="text-zinc-400 font-normal">(opsional)</span></flux:label>
                    <flux:input wire:model="email" type="email" placeholder="budi@example.com" />
                    <flux:error name="email" />
                </flux:field>
            </div>
        </div>

        <flux:separator />

        {{-- Section 2: Alamat & Titik Pemasangan --}}
        <div class="space-y-4">
            <flux:heading size="base">Alamat & Lokasi Instalasi</flux:heading>

            <flux:field>
                <flux:label>Alamat KTP / Domisili <span class="text-zinc-400 font-normal">(opsional)</span></flux:label>
                <flux:textarea wire:model="address" rows="2" placeholder="Alamat tempat tinggal / sesuai KTP..." />
                <flux:error name="address" />
            </flux:field>

            <flux:field>
                <flux:label>Alamat Pemasangan / Instalasi</flux:label>
                <flux:textarea wire:model="installation_address" rows="2" placeholder="Alamat lengkap lokasi pemasangan perangkat ISP..." />
                <flux:error name="installation_address" />
            </flux:field>

            <div class="space-y-2">
                <flux:label>Peta Titik Koordinat Instalasi <span class="text-zinc-400 font-normal">(opsional)</span></flux:label>
                <x-map-picker :lat="$lat" :lng="$lng" height="300px" />
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <flux:field>
                    <flux:label>Latitude</flux:label>
                    <flux:input wire:model.live.debounce.500ms="lat" placeholder="-6.2088000" />
                    <flux:error name="lat" />
                </flux:field>

                <flux:field>
                    <flux:label>Longitude</flux:label>
                    <flux:input wire:model.live.debounce.500ms="lng" placeholder="106.8456000" />
                    <flux:error name="lng" />
                </flux:field>
            </div>
        </div>

        <flux:separator />

        {{-- Tombol Aksi --}}
        <div class="flex items-center justify-end gap-3 pt-2">
            <flux:button :href="route('customers.index')" wire:navigate variant="ghost">Batal</flux:button>
            <flux:button type="submit" variant="primary" icon="check">Simpan Pelanggan</flux:button>
        </div>
    </form>
</div>
