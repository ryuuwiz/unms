<div class="mx-auto max-w-3xl space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">Edit Pelanggan</flux:heading>
            <flux:subheading>Perbarui data pelanggan {{ $pelanggan->identitasLengkap() }}.</flux:subheading>
        </div>
        <flux:badge size="sm" :color="$pelanggan->status->color()">
            {{ $pelanggan->status->label() }}
        </flux:badge>
    </div>

    <flux:separator />

    <form wire:submit="save" class="space-y-6">
        {{-- Section 1: Identitas & Tipe --}}
        <div class="space-y-4">
            <flux:heading size="base">Identitas & Tipe Pelanggan</flux:heading>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <flux:field>
                    <flux:label>No. Registrasi</flux:label>
                    <flux:input wire:model="no_reg" placeholder="Contoh: BF2308202601" />
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
                    <flux:input wire:model="nama_depan" placeholder="Contoh: Budi" />
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
                    <flux:input wire:model="no_hp" type="tel" placeholder="08123456789" />
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

        {{-- Section 3: Alamat --}}
        <div class="space-y-4">
            <flux:heading size="base">Alamat & Lokasi</flux:heading>

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
                <flux:textarea wire:model="alamat_lengkap" rows="2" />
                <flux:error name="alamat_lengkap" />
            </flux:field>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <flux:field>
                    <flux:label>Latitude</flux:label>
                    <flux:input wire:model="latitude" />
                    <flux:error name="latitude" />
                </flux:field>

                <flux:field>
                    <flux:label>Longitude</flux:label>
                    <flux:input wire:model="longitude" />
                    <flux:error name="longitude" />
                </flux:field>
            </div>
        </div>

        <flux:separator />

        {{-- Section 4: Dokumen Identitas (KTP) --}}
        <div class="space-y-4">
            <div>
                <flux:heading size="base">Dokumen Identitas (KTP Terenkripsi)</flux:heading>
                <flux:subheading>Berkas KTP disimpan terenkripsi di penyimpanan privat terisolasi.</flux:subheading>
            </div>

            <flux:card class="space-y-4 p-4 border border-zinc-200 dark:border-zinc-700 max-w-xl">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <flux:icon name="identification" class="size-5 text-indigo-500" />
                        <div>
                            <flux:label class="font-medium">Foto Kartu Identitas (KTP)</flux:label>
                            <flux:description>Format: JPG, PNG, WEBP (Maks. 5MB)</flux:description>
                        </div>
                    </div>

                    @if ($hasExistingKtp && ! $hapus_ktp)
                        <flux:badge color="green" size="sm" icon="check-badge">Tersimpan Terenkripsi</flux:badge>
                    @elseif ($hapus_ktp)
                        <flux:badge color="red" size="sm" icon="trash">Akan Dihapus</flux:badge>
                    @else
                        <flux:badge color="zinc" size="sm">Belum Diunggah</flux:badge>
                    @endif
                </div>

                @if ($hasExistingKtp && ! $hapus_ktp && ! $foto_ktp)
                    <div class="flex items-center justify-between rounded-lg border border-zinc-200 bg-zinc-50 p-3 dark:border-zinc-800 dark:bg-zinc-900">
                        <div class="flex items-center gap-2 text-sm text-zinc-600 dark:text-zinc-400">
                            <flux:icon name="lock-closed" class="size-4 text-emerald-500" />
                            <span>Berkas KTP aktif tersimpan aman.</span>
                        </div>
                        <flux:button wire:click="tandaiHapusKtp" variant="danger" size="sm" icon="trash">Hapus KTP</flux:button>
                    </div>
                @elseif ($hapus_ktp)
                    <div class="flex items-center justify-between rounded-lg border border-red-200 bg-red-50 p-3 dark:border-red-900/50 dark:bg-red-950/20">
                        <div class="flex items-center gap-2 text-sm text-red-600 dark:text-red-400">
                            <flux:icon name="exclamation-triangle" class="size-4" />
                            <span>KTP akan dihapus saat form disimpan.</span>
                        </div>
                        <flux:button wire:click="batalkanHapusKtp" variant="ghost" size="sm">Batalkan</flux:button>
                    </div>
                @endif

                <flux:field>
                    <flux:label>{{ $hasExistingKtp ? 'Ganti Foto KTP (opsional)' : 'Unggah Foto KTP' }}</flux:label>
                    <flux:input wire:model="foto_ktp" type="file" accept="image/jpeg,image/png,image/webp" />
                    <flux:error name="foto_ktp" />
                </flux:field>

                @if ($foto_ktp)
                    <div class="rounded-lg border border-indigo-200 bg-indigo-50/50 p-3 dark:border-indigo-900/50 dark:bg-indigo-950/20">
                        <div class="flex items-center gap-2 text-xs text-indigo-700 dark:text-indigo-400">
                            <flux:icon name="check-circle" class="size-4 shrink-0" />
                            <span>Berkas KTP baru siap dienkripsi: <strong>{{ $foto_ktp->getClientOriginalName() }}</strong></span>
                        </div>
                    </div>
                @endif
            </flux:card>
        </div>

        <flux:separator />

        {{-- Tombol Aksi --}}
        <div class="flex items-center justify-end gap-3 pt-2">
            <flux:button :href="route('pelanggan.index')" wire:navigate variant="ghost">Batal</flux:button>
            <flux:button type="submit" variant="primary" icon="check">Perbarui Pelanggan</flux:button>
        </div>
    </form>
</div>
