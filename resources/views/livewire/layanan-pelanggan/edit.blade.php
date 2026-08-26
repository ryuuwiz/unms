<div class="mx-auto max-w-2xl space-y-6">
    <div>
        <flux:heading size="xl">Edit Data Registrasi Billing</flux:heading>
        <flux:subheading>Perbarui konfigurasi paket, router, kredensial PPP, atau status data registrasi billing.</flux:subheading>
    </div>

    <flux:separator />

    <form wire:submit="save" class="space-y-6">
        <flux:field>
            <flux:label>Paket Layanan</flux:label>
            <flux:select wire:model.live="paket_layanan_id" placeholder="Pilih paket layanan..." searchable>
                @foreach ($pakets as $pk)
                    <flux:select.option value="{{ $pk->id }}">
                        {{ $pk->nama_paket }} — {{ $pk->formattedHarga() }}
                    </flux:select.option>
                @endforeach
            </flux:select>
            <flux:error name="paket_layanan_id" />
        </flux:field>

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
                <flux:select wire:model.live="router_id" placeholder="Pilih router gateway..." searchable>
                    <flux:select.option value="">-- Pilih Router Gateway --</flux:select.option>
                    @foreach ($routers as $r)
                        <flux:select.option value="{{ $r->id }}">
                            {{ $r->nama_router }} ({{ $r->ip_address }})
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
                <flux:select wire:model.live="ip_pool_id" placeholder="Pilih IP Pool..." searchable :disabled="! $router_id || $ipPools->isEmpty()">
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

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <flux:field>
                <flux:label>Username PPP</flux:label>
                <flux:input wire:model="ppp_username" />
                <flux:error name="ppp_username" />
            </flux:field>

            <flux:field>
                <flux:label>Password PPP <span class="text-zinc-400 font-normal">(isi jika ingin ubah)</span></flux:label>
                <flux:input wire:model="ppp_password" type="password" placeholder="Kosongkan jika tidak diubah" />
                <flux:error name="ppp_password" />
            </flux:field>
        </div>

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
            <flux:label>Status Layanan</flux:label>
            <flux:select wire:model="status">
                @foreach ($statuses as $st)
                    <flux:select.option value="{{ $st->value }}">{{ $st->label() }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:error name="status" />
        </flux:field>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <flux:field>
                <flux:label>Tanggal Mulai</flux:label>
                <flux:input wire:model="tanggal_mulai" type="date" />
                <flux:error name="tanggal_mulai" />
            </flux:field>

            <flux:field>
                <flux:label>Tanggal Jatuh Tempo (Expired)</flux:label>
                <flux:input wire:model="tanggal_expired" type="date" />
                <flux:error name="tanggal_expired" />
            </flux:field>
        </div>

        <div class="flex items-center justify-end gap-3 pt-2">
            <flux:button :href="route('layanan-pelanggan.index')" wire:navigate variant="ghost">Batal</flux:button>
            <flux:button type="submit" variant="primary" icon="check">Perbarui Layanan</flux:button>
        </div>
    </form>
</div>
