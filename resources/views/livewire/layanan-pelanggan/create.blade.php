<div class="mx-auto max-w-2xl space-y-6">
    <div>
        <flux:heading size="xl">Tambah Layanan Pelanggan</flux:heading>
        <flux:subheading>Hubungkan pelanggan dengan paket layanan internet dan konfigurasi PPP.</flux:subheading>
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
            <flux:field>
                <flux:label>Pilih Pelanggan</flux:label>
                <flux:select wire:model.live="pelanggan_id" placeholder="Pilih pelanggan aktif..." searchable>
                    <flux:select.option value="">-- Pilih Pelanggan --</flux:select.option>
                    @foreach ($pelanggans as $p)
                        <flux:select.option value="{{ $p->id }}">
                            {{ $p->labelSelector() }}
                        </flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="pelanggan_id" />
            </flux:field>

            <flux:field>
                <flux:label>Pilih Paket Layanan</flux:label>
                <flux:select wire:model.live="paket_layanan_id" placeholder="Pilih paket..." searchable>
                    <flux:select.option value="">-- Pilih Paket Layanan --</flux:select.option>
                    @foreach ($pakets as $pk)
                        <flux:select.option value="{{ $pk->id }}">
                            {{ $pk->nama_paket }} — {{ $pk->formattedHarga() }} ({{ $pk->profilBandwidth?->labelKecepatan() ?? 'No Profile' }})
                        </flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="paket_layanan_id" />
            </flux:field>

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
                    <flux:input wire:model="ppp_username" placeholder="Contoh: BF2308202601_00001" />
                    <flux:error name="ppp_username" />
                </flux:field>

                <flux:field>
                    <flux:label>Password PPP</flux:label>
                    <flux:input wire:model="ppp_password" type="password" placeholder="Minimal 4 karakter" />
                    <flux:error name="ppp_password" />
                </flux:field>
            </div>

            <flux:field>
                <flux:label>Tanggal Mulai Berlangganan</flux:label>
                <flux:input wire:model="tanggal_mulai" type="date" />
                <flux:error name="tanggal_mulai" />
            </flux:field>

            <div class="rounded-lg border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-800 dark:bg-zinc-900/50">
                <flux:checkbox
                    wire:model="auto_provision"
                    label="Langsung buat akun PPPoE Secret di router MikroTik"
                    description="Jika dicentang, job provisioning akan langsung dikirim ke router yang dipilih saat pendaftaran disimpan."
                />
            </div>

            <div class="flex items-center justify-between pt-2">
                <flux:button wire:click="prevStep" variant="ghost" icon="arrow-left">Kembali</flux:button>
                <flux:button type="submit" variant="primary" icon="check">Daftarkan Layanan</flux:button>
            </div>
        </form>
    @endif
</div>
