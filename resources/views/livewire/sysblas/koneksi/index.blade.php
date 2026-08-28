<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-zinc-900 dark:text-white">SysBlast - Koneksi API Gateway</h1>
            <p class="text-sm text-zinc-500 dark:text-zinc-400 mt-1">Kelola koneksi WABLAS / WhatsApp / SMS gateway, API key, batas laju pesan, dan status perangkat.</p>
        </div>
        <div class="flex items-center gap-2 flex-wrap">
            <flux:button wire:click="openCreateModal" variant="primary" icon="plus" size="sm">
                Tambah Koneksi Baru
            </flux:button>
        </div>
    </div>

    <!-- Stat Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <flux:card class="p-4 flex items-center gap-4">
            <div class="p-3 rounded-xl bg-blue-50 dark:bg-blue-950/50 text-blue-600 dark:text-blue-400">
                <flux:icon name="server-stack" class="size-6" />
            </div>
            <div>
                <p class="text-xs text-zinc-500 dark:text-zinc-400 font-medium">Total Koneksi Terdaftar</p>
                <h3 class="text-xl font-bold text-zinc-900 dark:text-white mt-0.5">{{ $totalKoneksi }} <span class="text-xs font-normal text-zinc-500">koneksi</span></h3>
            </div>
        </flux:card>

        <flux:card class="p-4 flex items-center gap-4">
            <div class="p-3 rounded-xl bg-emerald-50 dark:bg-emerald-950/50 text-emerald-600 dark:text-emerald-400">
                <flux:icon name="check-circle" class="size-6" />
            </div>
            <div>
                <p class="text-xs text-zinc-500 dark:text-zinc-400 font-medium">Koneksi Aktif</p>
                <h3 class="text-xl font-bold text-emerald-600 dark:text-emerald-400 mt-0.5">{{ $totalAktif }} <span class="text-xs font-normal text-zinc-500">aktif</span></h3>
            </div>
        </flux:card>

        <flux:card class="p-4 flex items-center gap-4">
            <div class="p-3 rounded-xl bg-amber-50 dark:bg-amber-950/50 text-amber-600 dark:text-amber-400">
                <flux:icon name="star" class="size-6" />
            </div>
            <div>
                <p class="text-xs text-zinc-500 dark:text-zinc-400 font-medium">Gateway Default Sistem</p>
                <h3 class="text-sm font-bold text-zinc-900 dark:text-white mt-0.5 truncate max-w-[200px]">
                    {{ $koneksis->where('is_default', true)->first()?->nama ?? 'Belum Ditentukan' }}
                </h3>
            </div>
        </flux:card>
    </div>

    <!-- Search Bar -->
    <div class="bg-white dark:bg-zinc-800 p-4 rounded-xl border border-zinc-200 dark:border-zinc-700 shadow-sm flex flex-col sm:flex-row gap-4 justify-between items-center">
        <div class="w-full sm:w-80">
            <flux:input
                wire:model.live.debounce.300ms="search"
                icon="magnifying-glass"
                placeholder="Cari nama koneksi, nomor, URL..."
                clearable
            />
        </div>
    </div>

    <!-- Table -->
    <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-zinc-50 dark:bg-zinc-900/50 border-b border-zinc-200 dark:border-zinc-700 text-zinc-600 dark:text-zinc-400 font-medium text-xs">
                    <tr>
                        <th class="px-4 py-3">Nama & Provider</th>
                        <th class="px-4 py-3">Nomor / Sender ID</th>
                        <th class="px-4 py-3">URL Base API</th>
                        <th class="px-4 py-3 text-center">Limit (MSG/Menit)</th>
                        <th class="px-4 py-3 text-center">Default</th>
                        <th class="px-4 py-3 text-center">Status</th>
                        <th class="px-4 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse($koneksis as $koneksi)
                        <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-700/30 transition-colors {{ $koneksi->is_default ? 'bg-amber-50/40 dark:bg-amber-950/20' : '' }}">
                            <td class="px-4 py-3">
                                <div class="font-bold text-zinc-900 dark:text-white flex items-center gap-2">
                                    {{ $koneksi->nama }}
                                    @if($koneksi->is_default)
                                        <flux:badge size="xs" color="amber" icon="star">Default</flux:badge>
                                    @endif
                                </div>
                                <div class="flex items-center gap-1 mt-0.5">
                                    <flux:badge size="xs" :color="$koneksi->provider->color()">
                                        {{ $koneksi->provider->label() }}
                                    </flux:badge>
                                    @if($koneksi->keterangan)
                                        <span class="text-[11px] text-zinc-400 truncate max-w-xs block">· {{ $koneksi->keterangan }}</span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-4 py-3 font-mono font-semibold text-xs text-zinc-800 dark:text-zinc-200">
                                {{ $koneksi->nomor ?: '-' }}
                            </td>
                            <td class="px-4 py-3 font-mono text-xs text-zinc-600 dark:text-zinc-300 max-w-xs truncate">
                                {{ $koneksi->url_api }}
                            </td>
                            <td class="px-4 py-3 text-center font-mono text-xs font-bold text-zinc-800 dark:text-zinc-200">
                                {{ $koneksi->limit_per_menit }} <span class="text-[10px] font-normal text-zinc-400">msg/min</span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                @if($koneksi->is_default)
                                    <span class="inline-flex items-center text-xs font-bold text-amber-600 dark:text-amber-400">
                                        ✓ Ya
                                    </span>
                                @else
                                    <button
                                        type="button"
                                        wire:click="setAsDefault({{ $koneksi->id }})"
                                        class="text-xs text-zinc-400 hover:text-amber-600 dark:hover:text-amber-400 font-medium cursor-pointer"
                                    >
                                        Jadikan Default
                                    </button>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-center">
                                <button
                                    type="button"
                                    wire:click="toggleStatus({{ $koneksi->id }})"
                                    class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium cursor-pointer transition-colors {{ $koneksi->is_aktif ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300' : 'bg-zinc-100 text-zinc-600 dark:bg-zinc-700 dark:text-zinc-400' }}"
                                >
                                    {{ $koneksi->is_aktif ? 'Aktif' : 'Nonaktif' }}
                                </button>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex items-center justify-end gap-1 flex-wrap">
                                    <flux:button wire:click="openPingModal({{ $koneksi->id }})" variant="subtle" size="xs" icon="signal" class="text-blue-600" title="Ping & Cek Status Koneksi">
                                        Ping
                                    </flux:button>
                                    <flux:button wire:click="openTestSendModal({{ $koneksi->id }})" variant="subtle" size="xs" icon="paper-airplane" class="text-emerald-600" title="Uji Coba Kirim Pesan">
                                        Tes Pesan
                                    </flux:button>
                                    <flux:button wire:click="openEditModal({{ $koneksi->id }})" variant="subtle" size="xs" icon="pencil-square" title="Edit">
                                        Edit
                                    </flux:button>
                                    @if(!$koneksi->is_default)
                                        <flux:button wire:click="hapus({{ $koneksi->id }})" wire:confirm="Yakin ingin menghapus koneksi gateway ini?" variant="subtle" size="xs" icon="trash" class="text-rose-600 hover:text-rose-700" title="Hapus">
                                            Hapus
                                        </flux:button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-8 text-zinc-400 text-sm italic">
                                Belum ada koneksi gateway yang terdaftar.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal Form Tambah / Edit Koneksi -->
    <flux:modal :open="$showModal" wire:model.self="showModal" class="max-w-xl">
        <form wire:submit="simpan" class="p-6 space-y-4">
            <div class="flex items-center gap-3 text-blue-600">
                <flux:icon name="server-stack" class="size-6" />
                <h3 class="text-lg font-bold text-zinc-900 dark:text-white">
                    {{ $editingId ? 'Edit Koneksi API Gateway' : 'Tambah Koneksi API Gateway' }}
                </h3>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <flux:input
                        wire:model="nama"
                        label="Nama / Label Koneksi"
                        placeholder="Contoh: WABLAS CS Utama"
                        required
                    />
                    @error('nama') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
                </div>

                <div>
                    <flux:select wire:model="provider" label="Provider Gateway" required>
                        @foreach($providers as $prov)
                            <flux:select.option value="{{ $prov->value }}">{{ $prov->label() }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    @error('provider') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <flux:input
                        wire:model="nomor"
                        label="Nomor WhatsApp / Sender ID"
                        placeholder="Contoh: 08970919525"
                    />
                    <flux:description>Nomor HP terdaftar di gateway.</flux:description>
                    @error('nomor') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
                </div>

                <div>
                    <flux:input
                        wire:model="url_api"
                        label="URL Base API Gateway"
                        placeholder="https://tegal.wablas.com"
                        required
                    />
                    @error('url_api') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <flux:input
                        wire:model="api_token"
                        label="API Token / Key"
                        placeholder="Token API dari WABLAS..."
                        type="password"
                        required
                    />
                    @error('api_token') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
                </div>

                <div>
                    <flux:input
                        wire:model="api_secret"
                        label="API Secret Key (Opsional)"
                        placeholder="Secret Key jika ada..."
                        type="password"
                    />
                    @error('api_secret') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <flux:input
                        type="number"
                        wire:model="limit_per_menit"
                        label="Batas Laju (Limit MSG/Menit)"
                        min="1"
                        max="300"
                        required
                    />
                    <flux:description>Maksimal pesan dikirim per 60 detik.</flux:description>
                    @error('limit_per_menit') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
                </div>

                <div>
                    <flux:input
                        wire:model="keterangan"
                        label="Keterangan / Catatan"
                        placeholder="Peruntukan notifikasi..."
                    />
                </div>
            </div>

            <div class="p-3 bg-zinc-50 dark:bg-zinc-900 rounded-lg space-y-2 border border-zinc-200 dark:border-zinc-700">
                <flux:checkbox
                    wire:model="is_default"
                    label="Jadikan Koneksi Default Sistem"
                    description="Koneksi ini akan digunakan untuk semua blast otomatis yang tidak ditentukan secara spesifik."
                />

                <flux:checkbox
                    wire:model="is_aktif"
                    label="Aktifkan Koneksi Ini"
                    description="Koneksi non-aktif tidak akan memproses pengiriman antrean."
                />
            </div>

            <div class="flex justify-end gap-2 pt-2">
                <flux:button wire:click="$set('showModal', false)" variant="subtle">Batal</flux:button>
                <flux:button type="submit" variant="primary">Simpan Koneksi</flux:button>
            </div>
        </form>
    </flux:modal>

    <!-- Modal Ping / Cek Status Koneksi -->
    <flux:modal :open="$showPingModal" wire:model.self="showPingModal" class="max-w-md">
        <div class="p-6 space-y-4">
            <div class="flex items-center justify-between border-b border-zinc-100 dark:border-zinc-700 pb-3">
                <div class="flex items-center gap-2">
                    <flux:icon name="signal" class="size-5 text-blue-600" />
                    <h3 class="font-bold text-base text-zinc-900 dark:text-white">Hasil Ping Koneksi API</h3>
                </div>

                <flux:button wire:click="eksekusiPing" wire:loading.attr="disabled" variant="subtle" size="xs" icon="arrow-path">
                    <span wire:loading.remove wire:target="eksekusiPing">Ping Ulang</span>
                    <span wire:loading wire:target="eksekusiPing">Memeriksa...</span>
                </flux:button>
            </div>

            @if($pingingSysblas)
                <div class="space-y-3 text-xs">
                    <div>
                        <span class="text-zinc-500 block">Koneksi:</span>
                        <div class="font-bold text-sm text-zinc-900 dark:text-white">{{ $pingingSysblas->nama }} ({{ $pingingSysblas->nomor }})</div>
                        <div class="font-mono text-zinc-400">{{ $pingingSysblas->url_api }}</div>
                    </div>

                    @if($isPinging)
                        <div class="py-6 flex flex-col items-center justify-center gap-2 text-zinc-400">
                            <flux:icon name="arrow-path" class="size-6 animate-spin text-blue-600" />
                            <span>Menghubungi server gateway WABLAS...</span>
                        </div>
                    @elseif($pingResult)
                        <div class="p-3.5 rounded-xl border {{ ($pingResult['connected'] ?? false) ? 'bg-emerald-50/70 border-emerald-200 dark:bg-emerald-950/30 dark:border-emerald-800' : 'bg-rose-50/70 border-rose-200 dark:bg-rose-950/30 dark:border-rose-800' }} space-y-2">
                            <div class="flex items-center gap-2">
                                <span class="size-3 rounded-full {{ ($pingResult['connected'] ?? false) ? 'bg-emerald-500 animate-pulse' : 'bg-rose-500' }}"></span>
                                <span class="font-bold text-sm {{ ($pingResult['connected'] ?? false) ? 'text-emerald-700 dark:text-emerald-300' : 'text-rose-700 dark:text-rose-300' }}">
                                    {{ ($pingResult['connected'] ?? false) ? 'STATUS: ONLINE (TERHUBUNG)' : 'STATUS: OFFLINE / TERPUTUS' }}
                                </span>
                            </div>

                            <div class="grid grid-cols-2 gap-2 pt-1 border-t border-zinc-200/60 dark:border-zinc-700/60 text-xs">
                                <div>
                                    <span class="text-zinc-500 block">Sisa Kuota:</span>
                                    <span class="font-bold text-zinc-800 dark:text-zinc-200">{{ is_scalar($pingResult['quota'] ?? null) ? (string) $pingResult['quota'] : '-' }}</span>
                                </div>
                                <div>
                                    <span class="text-zinc-500 block">Masa Aktif:</span>
                                    <span class="font-bold text-zinc-800 dark:text-zinc-200">{{ $pingResult['expired_at'] ?: '-' }}</span>
                                </div>
                            </div>

                            @if(!empty($pingResult['message']))
                                <div class="pt-1 text-[11px] text-zinc-600 dark:text-zinc-400">
                                    Respon Gateway: {{ $pingResult['message'] }}
                                </div>
                            @endif
                        </div>
                    @endif
                </div>
            @endif

            <div class="flex justify-end pt-2">
                <flux:button wire:click="$set('showPingModal', false)" variant="subtle">Tutup</flux:button>
            </div>
        </div>
    </flux:modal>

    <!-- Modal Test Kirim Pesan -->
    <flux:modal :open="$showTestSendModal" wire:model.self="showTestSendModal" class="max-w-md">
        <form wire:submit="kirimPesanTest" class="p-6 space-y-4">
            <div class="flex items-center gap-3 text-emerald-600">
                <flux:icon name="paper-airplane" class="size-6" />
                <h3 class="text-lg font-bold text-zinc-900 dark:text-white">Uji Coba Kirim Pesan</h3>
            </div>

            @if($testingSysblas)
                <p class="text-xs text-zinc-500 dark:text-zinc-400">
                    Kirim pesan instan menggunakan gateway <strong>{{ $testingSysblas->nama }}</strong> ({{ $testingSysblas->nomor }}).
                </p>
            @endif

            <div>
                <flux:input
                    wire:model="testPhone"
                    label="Nomor WhatsApp Tujuan"
                    placeholder="Contoh: 081234567890"
                    required
                />
                @error('testPhone') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
            </div>

            <div>
                <flux:textarea
                    wire:model="testMessage"
                    label="Isi Pesan Uji Coba"
                    placeholder="Tuliskan pesan..."
                    rows="3"
                    required
                />
                @error('testMessage') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
            </div>

            <div class="flex justify-end gap-2 pt-2">
                <flux:button wire:click="$set('showTestSendModal', false)" variant="subtle">Batal</flux:button>
                <flux:button type="submit" variant="primary" icon="paper-airplane" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="kirimPesanTest">Kirim Sekarang</span>
                    <span wire:loading wire:target="kirimPesanTest">Mengirim...</span>
                </flux:button>
            </div>
        </form>
    </flux:modal>
</div>
