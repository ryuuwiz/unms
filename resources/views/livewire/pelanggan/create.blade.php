<div class="mx-auto max-w-3xl space-y-6">
    <div>
        <flux:heading size="xl">Tambah Pelanggan</flux:heading>
        <flux:subheading>Daftarkan data master pelanggan baru dan tentukan titik lokasi pemasangan jaringan.</flux:subheading>
    </div>

    <flux:separator />

    <form wire:submit="save" class="space-y-6">
        {{-- Section 1: Identitas & Tipe --}}
        <div class="space-y-4">
            <flux:heading size="base">Identitas & Tipe Pelanggan</flux:heading>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <flux:field>
                    <flux:label>No. Registrasi <span class="text-zinc-400 font-normal">(opsional)</span></flux:label>
                    <flux:input wire:model="no_reg" placeholder="Otomatis (contoh: BF2308202601) atau isi custom" />
                    <flux:description>Kosongkan untuk generate otomatis berbasis tanggal dan urutan unik.</flux:description>
                    <flux:error name="no_reg" />
                </flux:field>

                <flux:field>
                    <flux:label>Tipe Pelanggan</flux:label>
                    <flux:select wire:model="tipe_pelanggan">
                        @foreach ($tipes as $tipe)
                            <flux:select.option value="{{ $tipe->value }}">{{ $tipe->label() }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="tipe_pelanggan" />
                </flux:field>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <flux:field>
                    <flux:label>Nama Depan</flux:label>
                    <flux:input wire:model="nama_depan" placeholder="Contoh: Budi" autofocus />
                    <flux:error name="nama_depan" />
                </flux:field>

                <flux:field>
                    <flux:label>Nama Belakang <span class="text-zinc-400 font-normal">(opsional)</span></flux:label>
                    <flux:input wire:model="nama_belakang" placeholder="Contoh: Santoso" />
                    <flux:error name="nama_belakang" />
                </flux:field>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <flux:field>
                    <flux:label>NIK (16 Digit) <span class="text-zinc-400 font-normal">(opsional, terenkripsi)</span></flux:label>
                    <flux:input wire:model="nik" maxlength="16" placeholder="3201xxxxxxxxxxxx" />
                    <flux:error name="nik" />
                </flux:field>

                <flux:field>
                    <flux:label>Status Pelanggan</flux:label>
                    <flux:select wire:model="status">
                        @foreach ($statuses as $st)
                            <flux:select.option value="{{ $st->value }}">{{ $st->label() }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="status" />
                </flux:field>
            </div>
        </div>

        <flux:separator />

        {{-- Section 2: Kontak --}}
        <div class="space-y-4">
            <flux:heading size="base">Kontak Pelanggan</flux:heading>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <flux:field>
                    <flux:label>Nomor WhatsApp / HP</flux:label>
                    <flux:input wire:model="no_hp" type="tel" placeholder="08123456789 atau +628123456789" />
                    <flux:description>Digunakan untuk notifikasi WA dan koordinasi teknisi.</flux:description>
                    <flux:error name="no_hp" />
                </flux:field>

                <flux:field>
                    <flux:label>Alamat Email <span class="text-zinc-400 font-normal">(opsional)</span></flux:label>
                    <flux:input wire:model="email" type="email" placeholder="budi@example.com" />
                    <flux:error name="email" />
                </flux:field>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <flux:field>
                    <flux:label>Telepon Rumah <span class="text-zinc-400 font-normal">(opsional)</span></flux:label>
                    <flux:input wire:model="telepon_rumah" placeholder="021xxxxxxx" />
                    <flux:error name="telepon_rumah" />
                </flux:field>

                <flux:field>
                    <flux:label>Perumahan / Area Coverage <span class="text-zinc-400 font-normal">(opsional)</span></flux:label>
                    <flux:select wire:model="perumahan_id" placeholder="Pilih perumahan...">
                        <flux:select.option :value="null">Bukan di perumahan</flux:select.option>
                        @foreach ($perumahans as $perum)
                            <flux:select.option value="{{ $perum->id }}">{{ $perum->nama_perumahan }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="perumahan_id" />
                </flux:field>
            </div>
        </div>

        <flux:separator />

        {{-- Section 3: Alamat & Lokasi Instalasi --}}
        <div class="space-y-4">
            <flux:heading size="base">Alamat & Lokasi Instalasi</flux:heading>

            <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                <flux:field>
                    <flux:label>RT</flux:label>
                    <flux:input wire:model="rt" placeholder="001" />
                </flux:field>
                <flux:field>
                    <flux:label>RW</flux:label>
                    <flux:input wire:model="rw" placeholder="002" />
                </flux:field>
                <flux:field>
                    <flux:label>No. Rumah</flux:label>
                    <flux:input wire:model="no_rumah" placeholder="A1/12" />
                </flux:field>
                <flux:field>
                    <flux:label>Kode Pos</flux:label>
                    <flux:input wire:model="kode_pos" maxlength="5" placeholder="12345" />
                </flux:field>
            </div>

            <flux:field>
                <flux:label>Alamat Lengkap Pemasangan</flux:label>
                <flux:textarea wire:model="alamat_lengkap" rows="2" placeholder="Alamat lengkap lokasi pemasangan perangkat ISP..." />
                <flux:error name="alamat_lengkap" />
            </flux:field>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <flux:field>
                    <flux:label>Latitude <span class="text-zinc-400 font-normal">(opsional)</span></flux:label>
                    <flux:input wire:model="latitude" placeholder="-6.2088000" />
                    <flux:error name="latitude" />
                </flux:field>

                <flux:field>
                    <flux:label>Longitude <span class="text-zinc-400 font-normal">(opsional)</span></flux:label>
                    <flux:input wire:model="longitude" placeholder="106.8456000" />
                    <flux:error name="longitude" />
                </flux:field>
            </div>
        </div>

        <flux:separator />

        {{-- Tombol Aksi --}}
        <div class="flex items-center justify-end gap-3 pt-2">
            <flux:button :href="route('pelanggan.index')" wire:navigate variant="ghost">Batal</flux:button>
            <flux:button type="submit" variant="primary" icon="check">Simpan Pelanggan</flux:button>
        </div>
    </form>
</div>
