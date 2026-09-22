<div class="mx-auto max-w-3xl space-y-6">
    <div>
        <flux:heading size="xl">Tambah Pelanggan</flux:heading>
        <flux:subheading>Daftarkan data master pelanggan baru dan tentukan titik lokasi pemasangan jaringan.
        </flux:subheading>
    </div>

    <flux:separator />

    <form wire:submit="save" class="space-y-6">
        {{-- Section 1: Identitas & Tipe --}}
        <div class="space-y-4">
            <flux:heading size="base">Identitas & Tipe Pelanggan</flux:heading>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <flux:field>
                    <flux:label>No. Registrasi</flux:label>
                    <div class="flex gap-2">
                        <flux:select wire:model.live="prefix_registrasi_id" class="w-28">
                            <flux:select.option value="" disabled>Prefix</flux:select.option>
                            @foreach ($prefixList as $prefixOption)
                                <flux:select.option value="{{ $prefixOption->id }}">{{ $prefixOption->kode }}
                                </flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:input wire:model="no_reg" placeholder="Otomatis atau isi custom" class="flex-1" />
                    </div>
                    <flux:description>Pilih prefix untuk generate otomatis, atau isi No. Registrasi custom secara
                        manual.</flux:description>
                    <flux:error name="prefix_registrasi_id" />
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
                    <flux:label>NIK (16 Digit) <span class="text-zinc-400 font-normal">(opsional, terenkripsi)</span>
                    </flux:label>
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
                    <flux:label>Perumahan / Area Coverage <span class="text-zinc-400 font-normal">(opsional)</span>
                    </flux:label>
                    <flux:select wire:model="perumahan_id" placeholder="Pilih perumahan...">
                        <flux:select.option :value="null">Bukan di perumahan</flux:select.option>
                        @foreach ($perumahans as $perum)
                            <flux:select.option value="{{ $perum->id }}">{{ $perum->nama_perumahan }}
                            </flux:select.option>
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
                <flux:textarea wire:model="alamat_lengkap" rows="2"
                    placeholder="Alamat lengkap lokasi pemasangan perangkat ISP..." />
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

            <div class="flex items-center gap-3">
                <flux:button wire:click="cariOdpTerdekat" variant="primary" icon="map-pin">Cari ODP Terdekat
                </flux:button>
                <flux:description>Mencari maksimal 3 ODP terdekat dalam radius 300 meter berdasarkan koordinat di atas.
                </flux:description>
            </div>

            @if (!empty($odpTerdekat))
                <div class="space-y-2 mt-4">
                    <flux:label>Hasil Pencarian ODP Terdekat:</flux:label>
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                        @foreach ($odpTerdekat as $odp)
                            <flux:card class="flex flex-col gap-2 p-3">
                                <div class="flex justify-between items-start">
                                    <span class="font-medium">{{ $odp['nama_odp'] }}</span>
                                    <span class="text-sm text-zinc-500">{{ $odp['jarak'] }}m</span>
                                </div>
                                <div>
                                    @if ($odp['port_kosong_count'] > 0)
                                        <flux:badge color="green" size="sm">{{ $odp['port_kosong_count'] }} Port
                                            Kosong</flux:badge>
                                    @else
                                        <flux:badge color="red" size="sm">Penuh</flux:badge>
                                    @endif
                                </div>
                            </flux:card>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        <flux:separator />

        {{-- Section 4: Dokumen Identitas & Legalitas --}}
        <div class="space-y-4">
            <div>
                <flux:heading size="base">Dokumen Identitas & Legalitas (Terenkripsi)</flux:heading>
                <flux:subheading>Seluruh berkas disimpan terenkripsi di penyimpanan privat dan dilindungi UU PDP.
                </flux:subheading>
            </div>

            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                {{-- Foto KTP --}}
                <flux:card class="space-y-4 p-4 border border-zinc-200 dark:border-zinc-700">
                    <div class="flex items-center gap-2">
                        <flux:icon name="identification" class="size-5 text-indigo-500" />
                        <div>
                            <flux:label class="font-medium">Foto Kartu Identitas (KTP)</flux:label>
                            <flux:description>Format: JPG, PNG, WEBP (Maks. 5MB)</flux:description>
                        </div>
                    </div>

                    <flux:input wire:model="foto_ktp" type="file" accept="image/jpeg,image/png,image/webp" />
                    <flux:error name="foto_ktp" />

                    @if ($foto_ktp)
                        <div
                            class="rounded-lg border border-indigo-200 bg-indigo-50/50 p-3 dark:border-indigo-900/50 dark:bg-indigo-950/20">
                            <div class="flex items-center gap-2 text-xs text-indigo-700 dark:text-indigo-400">
                                <flux:icon name="check-circle" class="size-4 shrink-0" />
                                <span>Berkas KTP siap dienkripsi:
                                    <strong>{{ $foto_ktp->getClientOriginalName() }}</strong></span>
                            </div>
                        </div>
                    @endif
                </flux:card>

                {{-- Dokumen MOU / Kontrak Awal --}}
                <flux:card class="space-y-4 p-4 border border-zinc-200 dark:border-zinc-700">
                    <div class="flex items-center gap-2">
                        <flux:icon name="document-text" class="size-5 text-emerald-500" />
                        <div>
                            <flux:label class="font-medium">Dokumen MOU / Kontrak <span
                                    class="text-zinc-400 font-normal">(opsional)</span></flux:label>
                            <flux:description>Format: PDF, JPG, PNG (Maks. 10MB)</flux:description>
                        </div>
                    </div>

                    <flux:input wire:model="dokumen_mou" type="file"
                        accept="application/pdf,image/jpeg,image/png,image/webp" />
                    <flux:error name="dokumen_mou" />

                    @if ($dokumen_mou)
                        <div class="space-y-3 pt-2">
                            <flux:field>
                                <flux:label>Jenis Dokumen</flux:label>
                                <flux:select wire:model="jenis_dokumen">
                                    <flux:select.option value="MOU / Kontrak">MOU / Kontrak Kerja Sama
                                    </flux:select.option>
                                    <flux:select.option value="Formulir Berlangganan">Formulir Berlangganan
                                    </flux:select.option>
                                    <flux:select.option value="Surat Kuasa">Surat Kuasa</flux:select.option>
                                    <flux:select.option value="Berita Acara Pemasangan">Berita Acara Pemasangan
                                    </flux:select.option>
                                    <flux:select.option value="Lainnya">Dokumen Lainnya</flux:select.option>
                                </flux:select>
                            </flux:field>

                            <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                                <flux:field>
                                    <flux:label>Nomor Dokumen</flux:label>
                                    <flux:input wire:model="nomor_dokumen" placeholder="Contoh: MOU/2026/08/001" />
                                </flux:field>
                                <flux:field>
                                    <flux:label>Keterangan</flux:label>
                                    <flux:input wire:model="keterangan_dokumen" placeholder="Catatan berkas..." />
                                </flux:field>
                            </div>
                        </div>
                    @endif
                </flux:card>
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
