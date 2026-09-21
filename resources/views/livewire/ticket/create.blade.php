<div class="max-w-5xl mx-auto space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
            <flux:button href="{{ route('ticket.index') }}" variant="subtle" icon="arrow-left" size="sm" wire:navigate />
            <div>
                <h1 class="text-2xl font-bold text-zinc-900 dark:text-white">Buat Tiket Baru</h1>
                <p class="text-sm text-zinc-500 dark:text-zinc-400 mt-0.5">Input tiket permohonan pemasangan baru, aduan gangguan, atau perubahan layanan.</p>
            </div>
        </div>
    </div>

    <form wire:submit="save" class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Form Utama (2 Kolom) -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Kartu Jenis & Pelanggan -->
            <div class="bg-white dark:bg-zinc-800 p-6 rounded-xl border border-zinc-200 dark:border-zinc-700 shadow-sm space-y-5">
                <h2 class="text-base font-semibold text-zinc-900 dark:text-white flex items-center gap-2">
                    <flux:icon name="ticket" class="size-5 text-blue-600 dark:text-blue-400" />
                    Jenis & Pelanggan
                </h2>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <!-- Jenis Tiket -->
                    <div>
                        <flux:select wire:model.live="jenis" label="Jenis Tiket" required>
                            @foreach($jenisList as $j)
                                <flux:select.option value="{{ $j->value }}">
                                    {{ $j->label() }}
                                </flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>

                    <!-- Divisi Penanggung Jawab (Multi-select) -->
                    <div>
                        <flux:field>
                            <flux:label>Divisi Penanggung Jawab</flux:label>
                            <div class="grid grid-cols-2 gap-2 mt-1">
                                @foreach($divisiList as $d)
                                    <flux:checkbox
                                        wire:model="divisis"
                                        value="{{ $d->value }}"
                                        label="{{ $d->label() }}"
                                    />
                                @endforeach
                            </div>
                            <flux:error name="divisis" />
                        </flux:field>
                    </div>
                </div>

                <!-- Pelanggan Selector -->
                <div>
                    <x-searchable-select field="pelanggan_id" label="Pilih Pelanggan / Prospek"
                        placeholder="Cari nama, no. HP, atau no. registrasi..." required />
                </div>

                <!-- Layanan Pelanggan Selector (Kondisional) -->
                @if($pelanggan_id)
                    <div>
                        <flux:select wire:model="layanan_pelanggan_id" label="Layanan Internet Terkait (opsional untuk Pemasangan Baru, wajib untuk Pencabutan dan Pindah Alamat)" placeholder="Pilih Layanan...">
                            <flux:select.option value="">-- Tanpa Layanan / Pemasangan Baru --</flux:select.option>
                            @foreach($layanans as $lay)
                                <flux:select.option value="{{ $lay->id }}">
                                    PPP: {{ $lay->ppp_username }} • Paket: {{ $lay->paketLayanan?->nama_paket ?? '-' }} • Router: {{ $lay->router?->nama_router ?? '-' }}
                                </flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>
                @endif
            </div>

            <!-- Kartu Prioritas & Jadwal Lapangan -->
            <div class="bg-white dark:bg-zinc-800 p-6 rounded-xl border border-zinc-200 dark:border-zinc-700 shadow-sm space-y-5">
                <h2 class="text-base font-semibold text-zinc-900 dark:text-white flex items-center gap-2">
                    <flux:icon name="clock" class="size-5 text-amber-600 dark:text-amber-400" />
                    Prioritas, PIC, & Jadwal
                </h2>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <!-- Prioritas -->
                    <div>
                        <flux:select wire:model.live="prioritas" label="Skala Prioritas" required>
                            @foreach($prioritasList as $pr)
                                <flux:select.option value="{{ $pr->value }}">
                                    {{ $pr->label() }} (SLA: {{ $pr->slaLabel() }})
                                </flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>

                    <!-- PIC Staf -->
                    <div>
                        <flux:select wire:model="pic_id" label="Penugasan PIC (Person in Charge)" placeholder="Pilih Petugas...">
                            <flux:select.option value="">-- Belum Ditugaskan --</flux:select.option>
                            @foreach($staffList as $stf)
                                <flux:select.option value="{{ $stf->id }}">
                                    {{ $stf->name }}
                                </flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>
                </div>

                <!-- Jadwal Lapangan -->
                <div>
                    <flux:input
                        type="datetime-local"
                        wire:model="dijadwalkan_pada"
                        label="Jadwal Kunjungan / Pengerjaan (Opsional)"
                    />
                </div>
            </div>

            <!-- Kartu Deskripsi -->
            <div class="bg-white dark:bg-zinc-800 p-6 rounded-xl border border-zinc-200 dark:border-zinc-700 shadow-sm space-y-4">
                <h2 class="text-base font-semibold text-zinc-900 dark:text-white flex items-center gap-2">
                    <flux:icon name="document-text" class="size-5 text-zinc-600 dark:text-zinc-400" />
                    Uraian Masalah / Deskripsi Tiket
                </h2>

                <div>
                    <flux:textarea
                        wire:model="deskripsi"
                        label="Deskripsi Lengkap"
                        placeholder="Tuliskan kronologi keluhan gangguan, lokasi detail titik sambung, atau instruksi teknis pengerjaan..."
                        rows="5"
                        required
                    />
                    @error('deskripsi') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
                </div>

                <!-- Upload Foto Kendala / Lokasi -->
                <div class="space-y-3">
                    <flux:field>
                        <flux:label>Foto Kendala / Lokasi (Opsional)</flux:label>
                        <input
                            type="file"
                            wire:model="fotoKendala"
                            accept="image/png, image/jpeg, image/webp"
                            class="block w-full text-xs text-zinc-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 dark:file:bg-zinc-700 dark:file:text-zinc-200"
                        />
                        <flux:description>Format: JPG, PNG, WEBP. Maksimal 5 MB.</flux:description>
                        <flux:error name="fotoKendala" />
                    </flux:field>

                    @if ($fotoKendala)
                        <x-file-upload-preview :src="$fotoKendala->temporaryUrl()" target="fotoKendala"
                            aspect="video" fit="cover" icon="camera" empty-text="Belum ada foto"
                            alt="Preview Foto Kendala" />
                    @endif
                </div>
            </div>
        </div>

        <!-- Kolom Samping Ringkasan (1 Kolom) -->
        <div class="space-y-6">
            <!-- Ringkasan SLA Target -->
            <div class="bg-white dark:bg-zinc-800 p-5 rounded-xl border border-zinc-200 dark:border-zinc-700 shadow-sm space-y-4">
                <h3 class="font-semibold text-sm text-zinc-900 dark:text-white flex items-center gap-2">
                    <flux:icon name="shield-check" class="size-4 text-emerald-600 dark:text-emerald-400" />
                    Target SLA Operasional
                </h3>

                <div class="p-3 bg-zinc-50 dark:bg-zinc-900/50 rounded-lg space-y-2 text-xs">
                    <div class="flex justify-between">
                        <span class="text-zinc-500">Skala Prioritas:</span>
                        <span class="font-semibold text-zinc-900 dark:text-white">{{ $prioritasEnum?->label() ?? 'Sedang' }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-zinc-500">Batas Waktu SLA:</span>
                        <span class="font-semibold text-blue-600 dark:text-blue-400">{{ $prioritasEnum?->slaLabel() ?? '3 Hari' }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-zinc-500">Estimasi Tenggat:</span>
                        <span class="font-semibold text-zinc-700 dark:text-zinc-300">
                            {{ now()->addHours($prioritasEnum?->durasiSlaHours() ?? 72)->format('d/m/Y H:i') }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Preview Data Pelanggan -->
            @if($selectedPelanggan)
                <div class="bg-white dark:bg-zinc-800 p-5 rounded-xl border border-zinc-200 dark:border-zinc-700 shadow-sm space-y-3 text-xs">
                    <h3 class="font-semibold text-sm text-zinc-900 dark:text-white flex items-center gap-2">
                        <flux:icon name="user" class="size-4 text-blue-600" />
                        Info Kontak & Alamat
                    </h3>

                    <div class="space-y-2 divide-y divide-zinc-100 dark:divide-zinc-700/50">
                        <div class="pt-2">
                            <div class="text-zinc-500">Pelanggan</div>
                            <div class="font-semibold text-zinc-900 dark:text-white text-sm">{{ $selectedPelanggan->identitasLengkap() }}</div>
                        </div>

                        <div class="pt-2">
                            <div class="text-zinc-500">Kontak</div>
                            <div class="text-zinc-900 dark:text-white font-medium">{{ $selectedPelanggan->no_hp }}</div>
                            <div class="text-zinc-500">{{ $selectedPelanggan->email ?? '-' }}</div>
                        </div>

                        <div class="pt-2">
                            <div class="text-zinc-500">Alamat Pemasangan</div>
                            <div class="text-zinc-900 dark:text-white">{{ $selectedPelanggan->alamat_lengkap }}</div>
                            @if($selectedPelanggan->perumahan)
                                <div class="text-zinc-500 mt-0.5">
                                    {{ $selectedPelanggan->perumahan->nama_perumahan }}, {{ $selectedPelanggan->perumahan->kelurahan?->nama_kelurahan }}, {{ $selectedPelanggan->perumahan->kelurahan?->kecamatan?->nama_kecamatan }}
                                </div>
                            @endif
                        </div>

                        @if($selectedPelanggan->dibuatOleh)
                            <div class="pt-2">
                                <div class="text-zinc-500">Sales Pendaftar</div>
                                <div class="text-zinc-800 dark:text-zinc-200 font-medium">{{ $selectedPelanggan->dibuatOleh->name }}</div>
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            <!-- Tombol Aksi Simpan -->
            <div class="bg-white dark:bg-zinc-800 p-5 rounded-xl border border-zinc-200 dark:border-zinc-700 shadow-sm space-y-3">
                <flux:button type="submit" variant="primary" icon="check" class="w-full">
                    Terbitkan Tiket
                </flux:button>
                <flux:button href="{{ route('ticket.index') }}" variant="subtle" class="w-full" wire:navigate>
                    Batal
                </flux:button>
            </div>
        </div>
    </form>
</div>
