<div class="space-y-6">
    {{-- Header --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl">Pengaturan Payment Gateway</flux:heading>
            <flux:subheading>Kelola multi-gateway pembayaran (Xendit, iPaymu, dll), kredensial terenkripsi di database, dan kebijakan biaya transaksi</flux:subheading>
        </div>
        <div class="flex items-center gap-2">
            <flux:button variant="primary" icon="plus" wire:click="openCreateModal">
                Tambah Gateway
            </flux:button>
        </div>
    </div>

    {{-- Metric Stat Cards --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <flux:card class="flex items-center gap-4 p-4">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600 dark:bg-indigo-950/50 dark:text-indigo-400">
                <flux:icon name="credit-card" class="size-5" />
            </div>
            <div>
                <flux:subheading size="sm">Total Gateway Terdaftar</flux:subheading>
                <flux:heading size="lg">{{ $totalGateways }}</flux:heading>
            </div>
        </flux:card>

        <flux:card class="flex items-center gap-4 p-4">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600 dark:bg-emerald-950/50 dark:text-emerald-400">
                <flux:icon name="check-circle" class="size-5" />
            </div>
            <div>
                <flux:subheading size="sm">Gateway Aktif</flux:subheading>
                <flux:heading size="lg">{{ $totalActive }}</flux:heading>
            </div>
        </flux:card>

        <flux:card class="flex items-center gap-4 p-4">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-amber-50 text-amber-600 dark:bg-amber-950/50 dark:text-amber-400">
                <flux:icon name="shield-check" class="size-5" />
            </div>
            <div>
                <flux:subheading size="sm">Keamanan Kredensial</flux:subheading>
                <div class="text-sm font-semibold text-emerald-600 dark:text-emerald-400">Terenkripsi Database (APP_KEY)</div>
            </div>
        </flux:card>
    </div>

    {{-- Main Card & Table --}}
    <flux:card class="space-y-4 p-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="w-full sm:w-72">
                <flux:input
                    wire:model.live.debounce.300ms="search"
                    placeholder="Cari gateway..."
                    icon="magnifying-glass"
                />
            </div>
        </div>

        <flux:table>
            <flux:table.columns>
                <flux:table.column>Provider & Nama</flux:table.column>
                <flux:table.column>Mode Operasional</flux:table.column>
                <flux:table.column>Beban Biaya (Fee)</flux:table.column>
                <flux:table.column>Status</flux:table.column>
                <flux:table.column class="text-right">Aksi</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse($gateways as $gateway)
                    <flux:table.row :key="$gateway->id">
                        <flux:table.cell>
                            <div class="flex items-center gap-2">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="font-semibold text-zinc-900 dark:text-white">{{ $gateway->nama }}</span>
                                        @if($gateway->is_default)
                                            <flux:badge color="indigo" size="sm">Default</flux:badge>
                                        @endif
                                    </div>
                                    <div class="text-xs text-zinc-500 font-mono mt-0.5">
                                        Provider: {{ strtoupper($gateway->provider) }}
                                        @if($gateway->keterangan)
                                            • {{ $gateway->keterangan }}
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </flux:table.cell>

                        <flux:table.cell>
                            @if($gateway->sandbox_mode)
                                <flux:badge color="amber" size="sm" inset="top bottom">Sandbox / Testing</flux:badge>
                            @else
                                <flux:badge color="emerald" size="sm" inset="top bottom">Production / Live</flux:badge>
                            @endif
                        </flux:table.cell>

                        <flux:table.cell>
                            <div class="text-xs space-y-0.5">
                                <div>
                                    <span class="text-zinc-500">Kebijakan:</span>
                                    <span class="font-medium text-zinc-800 dark:text-zinc-200">
                                        {{ $gateway->bebankan_ke_pelanggan ? 'Beban Pelanggan' : 'Disubsidi ISP' }}
                                    </span>
                                </div>
                                <div class="text-zinc-500">
                                    VA: Rp {{ number_format((float) $gateway->fee_va_nominal, 0, ',', '.') }} • QRIS: {{ $gateway->fee_qris_persen }}%
                                </div>
                            </div>
                        </flux:table.cell>

                        <flux:table.cell>
                            @if($gateway->is_active)
                                <flux:badge color="emerald" size="sm" inset="top bottom">Aktif</flux:badge>
                            @else
                                <flux:badge color="zinc" size="sm" inset="top bottom">Nonaktif</flux:badge>
                            @endif
                        </flux:table.cell>

                        <flux:table.cell class="text-right">
                            <div class="flex items-center justify-end gap-1">
                                <flux:button
                                    variant="ghost"
                                    size="sm"
                                    icon="bolt"
                                    wire:click="openPingModal({{ $gateway->id }})"
                                    title="Tes Ping / Cek Saldo"
                                />

                                @if(! $gateway->is_default)
                                    <flux:button
                                        variant="ghost"
                                        size="sm"
                                        icon="star"
                                        wire:click="setAsDefault({{ $gateway->id }})"
                                        title="Jadikan Default"
                                    />
                                @endif

                                <flux:button
                                    variant="ghost"
                                    size="sm"
                                    icon="pencil-square"
                                    wire:click="openEditModal({{ $gateway->id }})"
                                    title="Ubah Konfigurasi"
                                />

                                <flux:button
                                    variant="ghost"
                                    size="sm"
                                    :icon="$gateway->is_active ? 'pause' : 'play'"
                                    wire:click="toggleStatus({{ $gateway->id }})"
                                    :title="$gateway->is_active ? 'Nonaktifkan' : 'Aktifkan'"
                                />

                                @if(! $gateway->is_default || $gateways->count() <= 1)
                                    <flux:button
                                        variant="ghost"
                                        size="sm"
                                        icon="trash"
                                        wire:click="hapus({{ $gateway->id }})"
                                        wire:confirm="Yakin ingin menghapus koneksi gateway ini?"
                                        class="text-rose-600 hover:text-rose-700"
                                        title="Hapus"
                                    />
                                @endif
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="5" class="py-8 text-center text-zinc-500">
                            Belum ada payment gateway yang dikonfigurasi. Klik tombol "Tambah Gateway" untuk menghubungkan gateway.
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    {{-- Modal Form Create / Edit Gateway --}}
    <flux:modal wire:model="showModal" class="max-w-2xl">
        <form wire:submit="simpan" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $editingId ? 'Ubah Pengaturan Gateway' : 'Tambah Gateway Pembayaran' }}</flux:heading>
                <flux:subheading>Konfigurasi kredensial API yang disimpan terenkripsi di database</flux:subheading>
            </div>

            <div class="space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <flux:field>
                        <flux:label>Penyedia Gateway (Provider)</flux:label>
                        <flux:select wire:model.live="provider" placeholder="Pilih Provider">
                            @foreach($supportedProviders as $key => $label)
                                <flux:select.option value="{{ $key }}">{{ $label }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:error name="provider" />
                    </flux:field>

                    <flux:input
                        wire:model="nama"
                        label="Nama / Label Koneksi"
                        placeholder="Contoh: Xendit Utama atau iPaymu Official"
                    />
                </div>

                {{-- Dynamic Credential Inputs --}}
                <div class="p-4 rounded-xl bg-zinc-50 dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 space-y-4">
                    <flux:heading size="sm">Kredensial API (Terenkripsi Database)</flux:heading>

                    @if($provider === 'xendit')
                        <flux:input
                            wire:model="xendit_secret_key"
                            type="password"
                            viewable
                            label="Xendit Secret API Key"
                            placeholder="xnd_development_... atau xnd_production_..."
                            description="API key rahasia dari Dashboard Xendit"
                        />

                        <flux:input
                            wire:model="xendit_callback_token"
                            type="text"
                            label="Xendit Webhook Verification Token"
                            placeholder="Contoh token webhook dari dashboard Xendit"
                            description="Token untuk memvalidasi header x-callback-token webhook"
                        />
                    @elseif($provider === 'ipaymu')
                        <flux:input
                            wire:model="ipaymu_va"
                            label="Virtual Account (Merchant ID)"
                            placeholder="Contoh: 117900xxxxxxxx"
                            description="Nomor Virtual Account / Merchant ID resmi akun iPaymu"
                        />

                        <flux:input
                            wire:model="ipaymu_api_key"
                            type="password"
                            viewable
                            label="iPaymu API Key"
                            placeholder="Contoh: QJ513xxxxxxxxxxxxxxxxxxxx"
                            description="API Key rahasia dari Dashboard iPaymu Integrasi"
                        />
                    @endif
                </div>

                {{-- Mode & Toggles --}}
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 pt-2">
                    <flux:field>
                        <flux:checkbox wire:model="sandbox_mode" label="Mode Sandbox" />
                        <flux:description>Gunakan endpoint testing</flux:description>
                    </flux:field>

                    <flux:field>
                        <flux:checkbox wire:model="is_active" label="Status Aktif" />
                        <flux:description>Izinkan transaksi masuk</flux:description>
                    </flux:field>

                    <flux:field>
                        <flux:checkbox wire:model="is_default" label="Default Gateway" />
                        <flux:description>Utamakan untuk tagihan</flux:description>
                    </flux:field>
                </div>

                {{-- Fee Settings --}}
                <div class="pt-4 border-t border-zinc-100 dark:border-zinc-800 space-y-4">
                    <div class="flex items-center justify-between">
                        <flux:heading size="sm">Biaya Transaksi (Admin Fee)</flux:heading>
                    </div>

                    <flux:field>
                        <flux:checkbox wire:model="bebankan_ke_pelanggan" label="Bebankan Biaya Transaksi ke Pelanggan" />
                        <flux:description>Jika dicentang, biaya transaksi ditambahkan/dibebankan ke pelanggan. Jika tidak, disubsidi oleh ISP.</flux:description>
                    </flux:field>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <flux:input
                            wire:model="fee_va_nominal"
                            type="number"
                            step="100"
                            min="0"
                            label="Biaya Admin VA (Rp)"
                            placeholder="Contoh: 4000"
                        />

                        <flux:input
                            wire:model="fee_qris_persen"
                            type="number"
                            step="0.01"
                            min="0"
                            max="100"
                            label="Biaya Admin QRIS (%)"
                            placeholder="Contoh: 0.70"
                        />
                    </div>
                </div>

                <flux:input
                    wire:model="keterangan"
                    label="Catatan / Keterangan (Opsional)"
                    placeholder="Contoh: Akun utama rekening operasional"
                />
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">Batal</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">
                    Simpan Gateway
                </flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Modal Ping / Tes Koneksi --}}
    <flux:modal wire:model="showPingModal" class="max-w-md">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Uji Koneksi API Gateway</flux:heading>
                <flux:subheading>Memverifikasi kredensial dan ketersediaan layanan gateway</flux:subheading>
            </div>

            @if($isPinging)
                <div class="py-8 text-center space-y-3">
                    <div class="inline-block animate-spin rounded-full h-8 w-8 border-4 border-indigo-600 border-t-transparent"></div>
                    <div class="text-sm text-zinc-500">Menghubungi server payment gateway...</div>
                </div>
            @elseif($pingResult)
                <div class="space-y-4">
                    @if($pingResult->success)
                        <div class="p-4 rounded-xl bg-emerald-50 text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800 space-y-2">
                            <div class="flex items-center gap-2 font-semibold">
                                <flux:icon name="check-circle" class="size-5 text-emerald-600" />
                                <span>Koneksi Berhasil Terhubung!</span>
                            </div>
                            <div class="text-xs">{{ $pingResult->message }}</div>

                            @if($pingResult->balance !== null)
                                <div class="pt-2 border-t border-emerald-200 dark:border-emerald-800 text-xs flex justify-between">
                                    <span class="text-zinc-600 dark:text-zinc-400">Saldo Akun Merchant:</span>
                                    <span class="font-bold font-mono">Rp {{ number_format($pingResult->balance, 0, ',', '.') }}</span>
                                </div>
                            @endif
                        </div>
                    @else
                        <div class="p-4 rounded-xl bg-rose-50 text-rose-800 dark:bg-rose-950/50 dark:text-rose-300 border border-rose-200 dark:border-rose-800 space-y-2">
                            <div class="flex items-center gap-2 font-semibold">
                                <flux:icon name="x-circle" class="size-5 text-rose-600" />
                                <span>Koneksi Gagal Terhubung</span>
                            </div>
                            <div class="text-xs">{{ $pingResult->message }}</div>
                        </div>
                    @endif
                </div>
            @endif

            <div class="flex justify-end gap-2">
                <flux:button variant="filled" wire:click="eksekusiPing" :disabled="$isPinging">
                    Uji Ulang
                </flux:button>
                <flux:modal.close>
                    <flux:button variant="primary">Tutup</flux:button>
                </flux:modal.close>
            </div>
        </div>
    </flux:modal>
</div>
