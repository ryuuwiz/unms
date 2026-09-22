<div class="mx-auto max-w-2xl space-y-6">
    <div>
        <flux:heading size="xl">Tambah Data Registrasi Billing</flux:heading>
        <flux:subheading>Hubungkan data registrasi billing pelanggan dengan paket layanan internet dan konfigurasi PPP.</flux:subheading>
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
            <span>Konfigurasi Jaringan & PPP</span>
        </div>
    </div>

    <flux:separator />

    @if ($step === 1)
        <form wire:submit="nextStep" class="space-y-6">
            <x-searchable-select field="pelanggan_id" label="Pilih Pelanggan"
                placeholder="Cari nama, no. HP, atau no. registrasi..." />

            <x-searchable-select field="paket_layanan_id" label="Pilih Paket Layanan"
                placeholder="Cari nama paket..." />

            <div class="flex justify-end gap-3 pt-2">
                <flux:button :href="route('layanan-pelanggan.index')" wire:navigate variant="ghost">Batal</flux:button>
                <flux:button type="submit" variant="primary" icon-trailing="arrow-right">Lanjut ke Konfigurasi</flux:button>
            </div>
        </form>
    @elseif ($step === 2)
        <form wire:submit="save" class="space-y-6">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <flux:field>
                    <flux:label>Jenis Koneksi</flux:label>
                    <flux:select wire:model.live="jenis_koneksi">
                        @foreach ($jenisKoneksi as $jk)
                            <flux:select.option value="{{ $jk->value }}">{{ $jk->label() }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="jenis_koneksi" />
                </flux:field>

                <flux:field>
                    <flux:label>Router Gateway</flux:label>
                    <flux:select wire:model.live="router_id" placeholder="Pilih router gateway...">
                        <flux:select.option value="">-- Pilih Router Gateway --</flux:select.option>
                        @foreach ($routers as $r)
                            <flux:select.option value="{{ $r->id }}">
                                {{ $r->nama_router }} ({{ $r->ip_address }})@if ($r->status_koneksi->value !== 'online') — {{ $r->status_koneksi->label() }} @endif
                            </flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="router_id" />
                </flux:field>
            </div>

            @if ($router_id && $ipPools->isEmpty() && $jenis_koneksi === 'pppoe')
                <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-800/50 dark:bg-amber-950/30 dark:text-amber-300">
                    <div class="flex items-center gap-2 font-medium">
                        <flux:icon name="exclamation-triangle" class="size-4 text-amber-600 dark:text-amber-400" />
                        <span>Router ini belum memiliki IP Pool aktif.</span>
                    </div>
                    <p class="mt-1 text-xs text-amber-700 dark:text-amber-400">
                        Buat IP Pool di menu <strong>Jaringan & Infrastruktur &rarr; IP Pool</strong> untuk router ini terlebih dahulu, atau gunakan opsi IP Static.
                    </p>
                </div>
            @endif

            @if ($jenis_koneksi === 'pppoe')
                <flux:field>
                    <flux:label>IP Pool <span class="text-zinc-400 font-normal">(Remote Address PPPoE)</span></flux:label>
                    <flux:select wire:model.live="ip_pool_id" placeholder="Pilih IP Pool..." :disabled="! $router_id || $ipPools->isEmpty()">
                        <flux:select.option value="">-- Pilih IP Pool --</flux:select.option>
                        @foreach ($ipPools as $pool)
                            <flux:select.option value="{{ $pool->id }}">
                                {{ $pool->nama_pool }} ({{ $pool->ip_network }}/{{ $pool->cidr }})
                            </flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:description>Pool ini menentukan alokasi IP pelanggan di MikroTik (remote-address PPP Secret).</flux:description>
                    <flux:error name="ip_pool_id" />
                </flux:field>
            @else
                <flux:field>
                    <flux:label>Alamat IP Statis <span class="text-zinc-400 font-normal">(Remote Address IPv4)</span></flux:label>
                    <flux:input wire:model="ip_static" placeholder="Contoh: 10.10.10.50" />
                    <flux:description>Alamat IP statis dedicated yang akan dialokasikan langsung ke pelanggan di MikroTik.</flux:description>
                    <flux:error name="ip_static" />
                </flux:field>
            @endif

            <flux:field>
                <flux:label>Username PPP</flux:label>
                <flux:input wire:model="ppp_username" placeholder="Contoh: BF2308202601_00001" />
                <flux:error name="ppp_username" />
            </flux:field>

            <flux:callout icon="key" variant="secondary">
                <flux:callout.text>
                    Password PPP dibuat otomatis secara acak (8 karakter) saat data disimpan dan ditampilkan sekali lewat notifikasi setelah berhasil. Setelah itu hanya Super Admin yang bisa mengungkapnya kembali.
                </flux:callout.text>
            </flux:callout>

            {{-- Informasi Lokasi Pemasangan Spesifik Site (Multi-Site Ready) --}}
            <div class="rounded-lg border border-zinc-200 bg-zinc-50/50 p-4 space-y-4 dark:border-zinc-800 dark:bg-zinc-900/30">
                <div>
                    <h4 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">Titik Pasang & Lokasi Site <span class="text-xs font-normal text-zinc-500">(Opsional / Multi-Lokasi)</span></h4>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400">Kosongkan jika pemasangan berada di alamat utama pelanggan.</p>
                </div>

                <flux:field>
                    <flux:label>Nama / Label Site</flux:label>
                    <flux:input wire:model="nama_site" placeholder="Contoh: Rumah Utama, Ruko Lt. 2, Kantor Cabang" />
                    <flux:error name="nama_site" />
                </flux:field>

                <flux:field>
                    <flux:label>Alamat Pemasangan Spesifik</flux:label>
                    <flux:textarea wire:model="alamat_pemasangan" placeholder="Isi jika lokasi fisik berbeda dari domisili pelanggan..." rows="2" />
                    <flux:error name="alamat_pemasangan" />
                </flux:field>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <flux:field>
                        <flux:label>Latitude Site</flux:label>
                        <flux:input wire:model="latitude" type="number" step="any" placeholder="Contoh: -6.2088" />
                        <flux:error name="latitude" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Longitude Site</flux:label>
                        <flux:input wire:model="longitude" type="number" step="any" placeholder="Contoh: 106.8456" />
                        <flux:error name="longitude" />
                    </flux:field>
                </div>
            </div>

            <flux:field>
                <flux:label>Tanggal Mulai Berlangganan</flux:label>
                <flux:input wire:model.live="tanggal_mulai" type="date" />
                <flux:error name="tanggal_mulai" />
            </flux:field>

            <div class="rounded-lg border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-800 dark:bg-zinc-900/50">
                <flux:checkbox
                    wire:model="auto_provision"
                    label="Langsung buat akun PPPoE Secret di router MikroTik"
                    description="Jika dicentang, job provisioning akan langsung dikirim ke router yang dipilih saat pendaftaran disimpan."
                />
            </div>

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
            @if ($jenis_tagihan_pertama !== '')
                <div class="bg-blue-50 dark:bg-blue-950/40 p-6 rounded-xl border border-blue-200 dark:border-blue-800 space-y-3">
                    <h4 class="font-semibold text-blue-900 dark:text-blue-200 text-sm uppercase tracking-wide">
                        Pengaturan Harga Layanan
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

            <div class="flex items-center justify-between pt-2">
                <flux:button wire:click="prevStep" variant="ghost" icon="arrow-left">Kembali</flux:button>
                <flux:button type="submit" variant="primary" icon="check">Daftarkan Layanan</flux:button>
            </div>
        </form>
    @endif
</div>
