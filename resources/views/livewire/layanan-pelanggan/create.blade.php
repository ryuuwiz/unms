<div class="mx-auto max-w-2xl space-y-6">
    <div>
        <flux:heading size="xl">Tambah Data Registrasi Billing</flux:heading>
        <flux:subheading>Registrasi komersial pelanggan ke paket layanan internet. Router, IP Pool, dan Username PPP diisi belakangan lewat Aktivasi Pemasangan di Ticket.</flux:subheading>
    </div>

    {{-- Stepper Progress --}}
    <div class="flex items-center gap-4">
        <div class="flex items-center gap-2 {{ $step >= 1 ? 'text-primary-600 dark:text-primary-400 font-semibold' : 'text-zinc-400' }}">
            <span class="flex size-6 items-center justify-center rounded-full border {{ $step >= 1 ? 'border-primary-600 bg-primary-50 dark:bg-primary-950' : 'border-zinc-300' }} text-xs">1</span>
            <span>Pilih Pelanggan & Paket</span>
        </div>
        <div class="h-px flex-1 bg-zinc-200 dark:bg-zinc-700"></div>
        <div class="flex items-center gap-2 {{ $step >= 2 ? 'text-primary-600 dark:text-primary-400 font-semibold' : 'text-zinc-400' }}">
            <span class="flex size-6 items-center justify-center rounded-full border {{ $step >= 2 ? 'border-primary-600 bg-primary-50 dark:bg-primary-950' : 'border-zinc-300' }} text-xs">2</span>
            <span>Data Layanan</span>
        </div>
    </div>

    <flux:separator />

    @if ($step === 1)
        <form wire:submit="nextStep" class="space-y-6">
            <flux:field>
                <flux:label>Pelanggan</flux:label>
                <div class="rounded-lg border border-zinc-200 bg-zinc-50 px-4 py-3 text-sm dark:border-zinc-800 dark:bg-zinc-900/50">
                    {{ $this->pelanggan?->labelSelector() }}
                </div>
            </flux:field>

            <x-searchable-select field="paket_layanan_id" label="Pilih Paket Layanan"
                placeholder="Cari nama paket..." />

            @if ($paket_layanan_id)
                <flux:callout icon="tag" variant="secondary">
                    <flux:callout.text>
                        Harga Paket (Default): <strong>Rp {{ number_format($this->paketHargaDefault, 0, ',', '.') }}</strong>
                        — bisa diubah di bagian Pengaturan Harga Layanan pada langkah berikutnya.
                    </flux:callout.text>
                </flux:callout>
            @endif

            <div class="flex justify-end gap-3 pt-2">
                <flux:button :href="route('layanan-pelanggan.index')" wire:navigate variant="ghost">Batal</flux:button>
                <flux:button type="submit" variant="primary" icon-trailing="arrow-right">Lanjut ke Konfigurasi</flux:button>
            </div>
        </form>
    @elseif ($step === 2)
        <form wire:submit="save" class="space-y-6">
            <flux:callout icon="wifi" variant="secondary">
                <flux:callout.text>
                    Jenis Layanan: <strong>PPPoE</strong>. Router, IP Pool, dan Username PPP diatur nanti oleh NOC lewat Aktivasi Pemasangan di Ticket setelah instalasi lapangan selesai.
                </flux:callout.text>
            </flux:callout>

            {{-- Alamat Pemasangan --}}
            <div class="rounded-lg border border-zinc-200 bg-zinc-50/50 p-4 space-y-4 dark:border-zinc-800 dark:bg-zinc-900/30">
                <div>
                    <h4 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">Alamat Pemasangan</h4>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400">Alamat pemasangan bisa sama dengan alamat utama pelanggan atau berbeda (misal cabang, lantai lain, rumah orang tua, dll).</p>
                </div>

                <flux:field>
                    <flux:label>Nama / Label Site <span class="text-zinc-400 font-normal">(Opsional)</span></flux:label>
                    <flux:input wire:model="nama_site" placeholder="Contoh: Rumah Utama, Ruko Lt. 2, Kantor Cabang" />
                    <flux:error name="nama_site" />
                </flux:field>

                <flux:field>
                    <flux:label>Pilih Sumber Alamat</flux:label>
                    <flux:radio.group wire:model.live="alamat_sumber" variant="segmented">
                        <flux:radio value="utama">Gunakan alamat utama pelanggan</flux:radio>
                        <flux:radio value="custom">Alamat pemasangan berbeda</flux:radio>
                    </flux:radio.group>
                    <flux:error name="alamat_sumber" />
                </flux:field>

                @if ($alamat_sumber === 'utama')
                    <div class="rounded-lg border border-zinc-200 bg-white px-4 py-3 text-sm dark:border-zinc-700 dark:bg-zinc-900">
                        <p class="text-zinc-500 dark:text-zinc-400 text-xs mb-1">Alamat Utama Pelanggan</p>
                        <p class="text-zinc-800 dark:text-zinc-200">{{ $alamat_pemasangan ?: '—' }}</p>
                        @if ($latitude && $longitude)
                            <p class="text-zinc-500 dark:text-zinc-400 text-xs mt-1">Koordinat: {{ $latitude }}, {{ $longitude }}</p>
                        @endif
                    </div>
                @else
                    <flux:field>
                        <flux:label>Perumahan / Cluster <span class="text-zinc-400 font-normal">(Opsional)</span></flux:label>
                        <flux:select wire:model.live="perumahan_id" placeholder="Pilih perumahan / cluster...">
                            <flux:select.option value="">-- Tanpa perumahan --</flux:select.option>
                            @foreach ($perumahans as $perum)
                                <flux:select.option value="{{ $perum->id }}">{{ $perum->nama_perumahan }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:description>Pilih perumahan untuk otomatis menambahkan nama perumahan, kelurahan, kecamatan & kota ke alamat (jika alamat masih kosong).</flux:description>
                        <flux:error name="perumahan_id" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Alamat Pemasangan (Detail)</flux:label>
                        <flux:textarea wire:model="alamat_pemasangan" placeholder="Contoh: No. 5 - Curug Asri No.15B" rows="2" />
                        <flux:error name="alamat_pemasangan" />
                    </flux:field>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <flux:field>
                            <flux:label>Latitude</flux:label>
                            <flux:input wire:model.live="latitude" type="number" step="any" placeholder="Contoh: -6.2088" />
                            <flux:error name="latitude" />
                        </flux:field>

                        <flux:field>
                            <flux:label>Longitude</flux:label>
                            <flux:input wire:model.live="longitude" type="number" step="any" placeholder="Contoh: 106.8456" />
                            <flux:error name="longitude" />
                        </flux:field>
                    </div>

                    <x-map-picker lat="latitude" lng="longitude" />
                @endif
            </div>

            <flux:field>
                <flux:label>Tanggal Mulai Berlangganan</flux:label>
                <flux:input wire:model.live="tanggal_mulai" type="date" />
                <flux:error name="tanggal_mulai" />
            </flux:field>

            {{-- Tagihan Pertama --}}
            <div class="rounded-lg border border-zinc-200 bg-zinc-50/50 p-4 space-y-4 dark:border-zinc-800 dark:bg-zinc-900/30">
                <div>
                    <h4 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">Tagihan Pertama</h4>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400">Pilih cara penagihan pertama untuk layanan ini.</p>
                </div>

                <flux:radio.group wire:model.live="jenis_tagihan_pertama" variant="segmented">
                    @foreach ($jenisTagihanPertama as $jt)
                        <flux:radio value="{{ $jt->value }}">{{ $jt->label() }}</flux:radio>
                    @endforeach
                </flux:radio.group>
                <flux:error name="jenis_tagihan_pertama" />

                @if ($jenis_tagihan_pertama === 'promo')
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <flux:field>
                            <flux:label>Pilih Promo</flux:label>
                            <flux:select wire:model.live="promo_id" placeholder="-- Pilih Promo --">
                                <flux:select.option value="">-- Pilih Promo --</flux:select.option>
                                @foreach ($promos as $promo)
                                    <flux:select.option value="{{ $promo->id }}">
                                        {{ $promo->kode_promo }} - {{ $promo->nama_promo }}
                                    </flux:select.option>
                                @endforeach
                            </flux:select>
                            <flux:error name="promo_id" />
                        </flux:field>

                        <flux:field>
                            <flux:label>Atau Ketik Kode Promo</flux:label>
                            <flux:input wire:model.live="kode_promo" placeholder="Ketik kode promo global/musiman" :disabled="(bool) $promo_id" />
                            <flux:error name="kode_promo" />
                        </flux:field>
                    </div>
                @endif
            </div>

            {{-- Pengaturan Harga Layanan --}}
            <div class="rounded-lg border border-zinc-200 bg-zinc-50/50 p-4 space-y-4 dark:border-zinc-800 dark:bg-zinc-900/30">
                <div>
                    <h4 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">Pengaturan Harga Layanan</h4>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400">Harga dasar ini akan menjadi acuan penagihan bulanan untuk layanan ini.</p>
                </div>

                <flux:radio.group wire:model.live="price_mode" variant="segmented">
                    @foreach ($priceModes as $pm)
                        <flux:radio value="{{ $pm->value }}">{{ $pm->label() }}</flux:radio>
                    @endforeach
                </flux:radio.group>
                <flux:error name="price_mode" />

                @if ($price_mode === 'custom')
                    <flux:field>
                        <flux:label>Harga Layanan (Custom, per bulan)</flux:label>
                        <flux:input wire:model.live="price_custom" type="number" step="any" placeholder="Masukan angka saja, mis: 250000" />
                        <flux:description>Isi hanya jika memilih harga khusus (manual). Jika kosong, sistem akan memakai harga dari paket.</flux:description>
                        <flux:error name="price_custom" />
                    </flux:field>
                @endif
            </div>

            {{-- Estimasi Invoice Pertama --}}
            @if ($jenis_tagihan_pertama !== '')
                <div class="bg-blue-50 dark:bg-blue-950/40 p-6 rounded-xl border border-blue-200 dark:border-blue-800 space-y-3">
                    <h4 class="font-semibold text-blue-900 dark:text-blue-200 text-sm uppercase tracking-wide">
                        Estimasi Invoice Pertama
                    </h4>
                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between text-zinc-700 dark:text-zinc-300">
                            <span>Tarif Paket Layanan:</span>
                            <span class="font-semibold">Rp {{ number_format($hargaPaket, 0, ',', '.') }}</span>
                        </div>

                        @if ($jenis_tagihan_pertama === 'prorata' && $hariDitagih !== null)
                            <div class="flex justify-between text-zinc-500 dark:text-zinc-400 text-xs">
                                <span>Proporsional:</span>
                                <span>{{ $hariDitagih }} / {{ $hariTotalPeriode }} hari</span>
                            </div>
                        @endif

                        @if ($diskonTagihanPertama > 0)
                            <div class="flex justify-between text-emerald-600 dark:text-emerald-400">
                                <span>Potongan Diskon Promo:</span>
                                <span class="font-semibold">-Rp {{ number_format($diskonTagihanPertama, 0, ',', '.') }}</span>
                            </div>
                        @endif

                        <div class="flex justify-between text-base font-bold text-zinc-900 dark:text-white border-t border-blue-200 dark:border-blue-800 pt-2">
                            <span>Total Tagihan Pertama:</span>
                            <span class="text-blue-600 dark:text-blue-400">Rp {{ number_format($totalTagihanPertama, 0, ',', '.') }}</span>
                        </div>

                        @if ($tanggalJatuhTempoPertama)
                            <div class="flex justify-between text-zinc-500 dark:text-zinc-400 text-xs">
                                <span>Jatuh Tempo:</span>
                                <span>{{ \Illuminate\Support\Carbon::parse($tanggalJatuhTempoPertama)->translatedFormat('d F Y') }}</span>
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div class="rounded-lg border border-zinc-200 bg-zinc-50/50 p-4 space-y-1 text-sm dark:border-zinc-800 dark:bg-zinc-900/30">
                    <h4 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100 mb-2">Ringkasan Pelanggan</h4>
                    <div class="flex justify-between text-zinc-600 dark:text-zinc-400"><span>Nama:</span><span class="text-zinc-900 dark:text-zinc-100">{{ $this->pelanggan?->namaLengkap() }}</span></div>
                    <div class="flex justify-between text-zinc-600 dark:text-zinc-400"><span>No. Reg:</span><span class="text-zinc-900 dark:text-zinc-100">{{ $this->pelanggan?->no_reg }}</span></div>
                    <div class="flex justify-between text-zinc-600 dark:text-zinc-400"><span>No. HP:</span><span class="text-zinc-900 dark:text-zinc-100">{{ $this->pelanggan?->no_hp }}</span></div>
                    <div class="flex justify-between text-zinc-600 dark:text-zinc-400"><span>Status:</span><span class="text-zinc-900 dark:text-zinc-100">{{ $this->pelanggan?->status?->label() }}</span></div>
                </div>

                <div class="rounded-lg border border-zinc-200 bg-zinc-50/50 p-4 space-y-1 text-sm dark:border-zinc-800 dark:bg-zinc-900/30">
                    <h4 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100 mb-2">Mode Billing</h4>
                    <flux:badge color="zinc" size="sm">Default - Fixed Date</flux:badge>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-2">Semua layanan baru tetap memakai jatuh tempo fixed date secara default.</p>
                </div>
            </div>

            <flux:callout icon="information-circle" variant="secondary">
                <flux:callout.heading>Catatan</flux:callout.heading>
                <flux:callout.text>
                    Layanan ini akan tercatat di Layanan / Pemasangan pada detail pelanggan. Invoice pertama langsung dibuat saat layanan disimpan.
                    Site ID dibuat otomatis di backend. Ticketing pemasangan, gangguan, pencabutan, dan lainnya dikelola di halaman detail layanan.
                </flux:callout.text>
            </flux:callout>

            <div class="flex items-center justify-between pt-2">
                <flux:button wire:click="prevStep" variant="ghost" icon="arrow-left">Kembali</flux:button>
                <flux:button type="submit" variant="primary" icon="check">Daftarkan Layanan</flux:button>
            </div>
        </form>
    @endif
</div>
