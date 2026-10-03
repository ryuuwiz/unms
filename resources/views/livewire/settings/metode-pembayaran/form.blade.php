<div class="mx-auto max-w-2xl space-y-6">
    <div>
        <flux:heading size="xl">{{ $channelId ? 'Edit Channel Pembayaran' : 'Tambah Channel Pembayaran' }}</flux:heading>
        <flux:subheading>Atur gateway, tipe, fee admin, status, dan icon untuk channel ini.</flux:subheading>
    </div>

    <flux:separator />

    <form wire:submit="save" class="space-y-6">
        <flux:field>
            <flux:label>Payment Gateway *</flux:label>
            <div class="flex gap-2">
                <flux:select wire:model.live="pengaturan_gateway_id" placeholder="Pilih gateway..." class="flex-1">
                    <flux:select.option value="">Pilih gateway...</flux:select.option>
                    @foreach ($gateways as $gateway)
                        <flux:select.option value="{{ $gateway->id }}">{{ $gateway->nama }} ({{ $gateway->provider }})</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:button type="button" wire:click="ambilDariGateway" wire:loading.attr="disabled" icon="arrow-down-tray" :disabled="! $pengaturan_gateway_id">
                    Ambil dari {{ $gateways->firstWhere('id', $pengaturan_gateway_id)?->provider ?? 'gateway' }}
                </flux:button>
            </div>
            <flux:error name="pengaturan_gateway_id" />
        </flux:field>

        @if ($saranGateway)
            <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 divide-y divide-zinc-100 dark:divide-zinc-800 max-h-72 overflow-y-auto">
                @foreach ($saranGateway as $index => $saran)
                    <button type="button" wire:click="pakaiSaran({{ $index }})" class="flex w-full items-center gap-3 p-2 text-left hover:bg-zinc-50 dark:hover:bg-zinc-800">
                        @if ($saran['logo'])
                            <img src="{{ $saran['logo'] }}" alt="" class="h-6 w-10 object-contain" loading="lazy">
                        @endif
                        <span class="flex-1 text-sm">{{ $saran['nama'] }} <span class="font-mono text-xs text-zinc-500">{{ $saran['kode'] }}</span></span>
                        <span class="text-xs text-zinc-500">{{ \App\Enums\GatewayChannel::from($saran['tipe'])->label() }} &bull; fee {{ $saran['fee_persen'] ? $saran['fee'].'%' : 'Rp '.number_format($saran['fee'], 0, ',', '.') }}</span>
                    </button>
                @endforeach
            </div>
        @endif

        <flux:field>
            <flux:label>Tipe Pembayaran *</flux:label>
            <flux:select wire:model.live="tipe" placeholder="Pilih tipe..." :disabled="! $pengaturan_gateway_id">
                <flux:select.option value="">Pilih tipe...</flux:select.option>
                @foreach ($tipeOptions as $tipeOption)
                    <flux:select.option value="{{ $tipeOption->value }}">{{ $tipeOption->label() }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:description>Menentukan kelompok channel, mis. QRIS, Virtual Account, atau gerai retail.</flux:description>
            <flux:error name="tipe" />
        </flux:field>

        <flux:field>
            <flux:label>Nama Channel *</flux:label>
            <flux:input wire:model="kode" placeholder="Contoh: bca, bri, linkaja, alfamart" list="kode-channel-saran" />
            <datalist id="kode-channel-saran">
                @foreach ($kodeSaran as $saran)
                    <option value="{{ $saran }}"></option>
                @endforeach
            </datalist>
            <flux:description>
                Dipakai sebagai kode internal, huruf kecil tanpa spasi.
                @if ($kodeSaran)
                    Didukung: <span class="font-mono">{{ implode(', ', $kodeSaran) }}</span>.
                @endif
            </flux:description>
            <flux:error name="kode" />
        </flux:field>

        <flux:field>
            <flux:label>Fee Admin *</flux:label>
            <flux:input wire:model="fee_admin" placeholder="0" inputmode="decimal" />
            <flux:description>Angka rupiah (mis. 4100) = flat per transaksi. Diakhiri % (mis. 1%) = persentase dari nominal tagihan. Ditambahkan ke tagihan pelanggan; 0 = ditanggung ISP.</flux:description>
            <flux:error name="fee_admin" />
        </flux:field>

        <flux:field>
            <flux:label>URL Icon</flux:label>
            <flux:input wire:model="icon_url" type="url" placeholder="https://contoh.com/icon.png" />
            <flux:description>Logo bank/QRIS yang ditampilkan di halaman pilih channel pembayaran.</flux:description>
            <flux:error name="icon_url" />
        </flux:field>

        <flux:field>
            <flux:label>Status</flux:label>
            <flux:select wire:model="status">
                <flux:select.option value="on">ON</flux:select.option>
                <flux:select.option value="off">OFF</flux:select.option>
            </flux:select>
            <flux:description>Hanya channel dengan status ON yang bisa dipakai pelanggan.</flux:description>
            <flux:error name="status" />
        </flux:field>

        <flux:field>
            <flux:label>Keterangan</flux:label>
            <flux:textarea wire:model="keterangan" rows="2" placeholder="Contoh: Bayar dengan QRIS, Bayar di Alfamart, dll." />
            <flux:error name="keterangan" />
        </flux:field>

        <div class="flex items-center justify-end gap-3 pt-2">
            <flux:button :href="route('settings.metode-pembayaran.index')" wire:navigate variant="ghost">Batal</flux:button>
            <flux:button type="submit" variant="primary" icon="check">Simpan Channel</flux:button>
        </div>
    </form>
</div>
