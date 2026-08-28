<section class="w-full">
    @include('partials.settings-heading')

    <x-settings.layout :heading="__('WhatsApp Gateway & Template')" :subheading="__('Pantau status koneksi WABLAS WhatsApp API, lakukan uji coba pesan, dan kelola template pesan otomatis.')">
        <div class="my-6 w-full max-w-4xl space-y-8">

            {{-- 1. Status Gateway WABLAS --}}
            <flux:card class="p-6 space-y-5">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-zinc-100 dark:border-zinc-700/60 pb-4">
                    <div>
                        <flux:heading size="lg" class="flex items-center gap-2">
                            <flux:icon name="device-phone-mobile" class="size-5 text-emerald-600 dark:text-emerald-400" />
                            Status Perangkat WABLAS
                        </flux:heading>
                        <flux:subheading>Informasi koneksi nomor WhatsApp gateway yang dikonfigurasi pada .env.</flux:subheading>
                    </div>

                    <flux:button wire:click="refreshDeviceInfo" wire:loading.attr="disabled" variant="subtle" size="xs" icon="arrow-path">
                        <span wire:loading.remove wire:target="refreshDeviceInfo">Cek Status</span>
                        <span wire:loading wire:target="refreshDeviceInfo">Memeriksa...</span>
                    </flux:button>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div class="p-3.5 bg-zinc-50 dark:bg-zinc-900/60 rounded-xl border border-zinc-200/70 dark:border-zinc-700/60 space-y-1">
                        <span class="text-xs text-zinc-500 block">Status Koneksi</span>
                        <div class="flex items-center gap-2">
                            <span class="size-2.5 rounded-full {{ ($deviceInfo['connected'] ?? false) ? 'bg-emerald-500 animate-pulse' : 'bg-rose-500' }}"></span>
                            <span class="font-bold text-sm {{ ($deviceInfo['connected'] ?? false) ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}">
                                {{ ($deviceInfo['connected'] ?? false) ? 'TERHUBUNG (ONLINE)' : 'TERPUTUS / OFFLINE' }}
                            </span>
                        </div>
                    </div>

                    <div class="p-3.5 bg-zinc-50 dark:bg-zinc-900/60 rounded-xl border border-zinc-200/70 dark:border-zinc-700/60 space-y-1">
                        <span class="text-xs text-zinc-500 block">Nomor WhatsApp Gateway</span>
                        <span class="font-bold font-mono text-sm text-zinc-900 dark:text-white">
                            {{ $deviceInfo['phone'] ?? config('services.wablas.number', '-') }}
                        </span>
                    </div>

                    <div class="p-3.5 bg-zinc-50 dark:bg-zinc-900/60 rounded-xl border border-zinc-200/70 dark:border-zinc-700/60 space-y-1">
                        <span class="text-xs text-zinc-500 block">Sisa Kuota Pesan</span>
                        <span class="font-bold text-sm text-zinc-900 dark:text-white">
                            {{ is_scalar($deviceInfo['quota'] ?? null) ? (string) $deviceInfo['quota'] : '-' }}
                        </span>
                    </div>

                    <div class="p-3.5 bg-zinc-50 dark:bg-zinc-900/60 rounded-xl border border-zinc-200/70 dark:border-zinc-700/60 space-y-1">
                        <span class="text-xs text-zinc-500 block">Host API Server</span>
                        <span class="font-medium text-xs font-mono text-zinc-700 dark:text-zinc-300 truncate block">
                            {{ config('services.wablas.host') }}
                        </span>
                    </div>
                </div>

                @if(!empty($deviceInfo['message']))
                    <p class="text-xs text-zinc-500 dark:text-zinc-400">
                        Pesan Status Gateway: <span class="font-medium text-zinc-700 dark:text-zinc-300">{{ $deviceInfo['message'] }}</span>
                    </p>
                @endif
            </flux:card>

            {{-- 2. Form Uji Coba Kirim Pesan --}}
            <flux:card class="p-6 space-y-5">
                <div>
                    <flux:heading size="lg" class="flex items-center gap-2">
                        <flux:icon name="paper-airplane" class="size-5 text-blue-600 dark:text-blue-400" />
                        Uji Coba Pengiriman Pesan WhatsApp
                    </flux:heading>
                    <flux:subheading>Kirim pesan pengujian instan ke nomor WhatsApp staf atau admin untuk memastikan API berfungsi.</flux:subheading>
                </div>

                <form wire:submit="kirimPesanUjiCoba" class="space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <flux:input
                                wire:model="testPhone"
                                label="Nomor WhatsApp Tujuan"
                                placeholder="Contoh: 081234567890"
                                required
                            />
                            @error('testPhone') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <div class="sm:col-span-2">
                            <flux:input
                                wire:model="testMessage"
                                label="Isi Pesan Uji Coba"
                                placeholder="Tuliskan pesan singkat..."
                                required
                            />
                            @error('testMessage') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <flux:button type="submit" variant="primary" icon="paper-airplane" size="sm" wire:loading.attr="disabled">
                            <span wire:loading.remove wire:target="kirimPesanUjiCoba">Kirim Pesan Uji Coba</span>
                            <span wire:loading wire:target="kirimPesanUjiCoba">Mengirim...</span>
                        </flux:button>
                    </div>
                </form>
            </flux:card>

            {{-- 3. Master Template Pesan WhatsApp --}}
            <flux:card class="p-6 space-y-5">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-zinc-100 dark:border-zinc-700/60 pb-4">
                    <div>
                        <flux:heading size="lg" class="flex items-center gap-2">
                            <flux:icon name="chat-bubble-bottom-center-text" class="size-5 text-indigo-600 dark:text-indigo-400" />
                            Template Pesan WhatsApp
                        </flux:heading>
                        <flux:subheading>Kustomisasi teks pesan notifikasi tagihan, konfirmasi pembayaran, dan tiket.</flux:subheading>
                    </div>

                    <div class="flex items-center gap-2">
                        <flux:select wire:model.live="filterKategori" placeholder="Semua Kategori" size="sm" class="w-44">
                            <flux:select.option value="">Semua Kategori</flux:select.option>
                            @foreach($kategoriList as $kat)
                                <flux:select.option value="{{ $kat->value }}">{{ $kat->label() }}</flux:select.option>
                            @endforeach
                        </flux:select>

                        <flux:button wire:click="openCreateTemplateModal" variant="primary" icon="plus" size="sm">
                            Tambah Template
                        </flux:button>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-zinc-50 dark:bg-zinc-900/50 border-b border-zinc-200 dark:border-zinc-700 text-zinc-600 dark:text-zinc-400 font-medium text-xs">
                            <tr>
                                <th class="px-4 py-3">Nama Template & Kode</th>
                                <th class="px-4 py-3">Kategori</th>
                                <th class="px-4 py-3">Preview Konten</th>
                                <th class="px-4 py-3 text-center">Status</th>
                                <th class="px-4 py-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                            @forelse($templates as $tmpl)
                                <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-700/30 transition-colors">
                                    <td class="px-4 py-3">
                                        <div class="font-bold text-zinc-900 dark:text-white">{{ $tmpl->nama }}</div>
                                        <div class="text-xs font-mono text-zinc-400 font-light">{{ $tmpl->kode }}</div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <flux:badge size="xs" :color="$tmpl->kategori->color()">
                                            {{ $tmpl->kategori->label() }}
                                        </flux:badge>
                                    </td>
                                    <td class="px-4 py-3 max-w-xs truncate text-xs text-zinc-600 dark:text-zinc-300 font-sans">
                                        {{ Str::limit($tmpl->konten, 80) }}
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <button
                                            type="button"
                                            wire:click="toggleTemplateStatus({{ $tmpl->id }})"
                                            class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium cursor-pointer transition-colors {{ $tmpl->is_aktif ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300' : 'bg-zinc-100 text-zinc-600 dark:bg-zinc-700 dark:text-zinc-400' }}"
                                        >
                                            {{ $tmpl->is_aktif ? 'Aktif' : 'Nonaktif' }}
                                        </button>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <div class="flex items-center justify-end gap-1">
                                            <flux:button wire:click="openEditTemplateModal({{ $tmpl->id }})" variant="subtle" size="xs" icon="pencil-square">
                                                Edit
                                            </flux:button>
                                            <flux:button wire:click="hapusTemplate({{ $tmpl->id }})" wire:confirm="Yakin ingin menghapus template ini?" variant="subtle" size="xs" icon="trash" class="text-rose-600 hover:text-rose-700">
                                                Hapus
                                            </flux:button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center py-6 text-zinc-400 text-xs italic">
                                        Tidak ada template pesan yang sesuai kriteria.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </flux:card>
        </div>
    </x-settings.layout>

    <!-- Modal Form Tambah / Edit Template -->
    <flux:modal :open="$showTemplateModal" wire:model.self="showTemplateModal" class="max-w-2xl">
        <form wire:submit="simpanTemplate" class="p-6 space-y-4">
            <div class="flex items-center gap-3 text-indigo-600">
                <flux:icon name="chat-bubble-bottom-center-text" class="size-6" />
                <h3 class="text-lg font-bold text-zinc-900 dark:text-white">
                    {{ $editingTemplateId ? 'Edit Template WhatsApp' : 'Tambah Template WhatsApp' }}
                </h3>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <flux:input
                        wire:model="template_kode"
                        label="Kode Unik Template"
                        placeholder="Contoh: pengingat_tagihan_h3"
                        required
                    />
                    <flux:description>Identifier pemanggilan internal.</flux:description>
                    @error('template_kode') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
                </div>

                <div>
                    <flux:input
                        wire:model="template_nama"
                        label="Nama Template"
                        placeholder="Contoh: Pengingat Tagihan H-3"
                        required
                    />
                    @error('template_nama') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <flux:select wire:model="template_kategori" label="Kategori Template" required>
                        @foreach($kategoriList as $k)
                            <flux:select.option value="{{ $k->value }}">{{ $k->label() }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    @error('template_kategori') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
                </div>

                <div>
                    <flux:input
                        wire:model="template_keterangan"
                        label="Keterangan / Catatan (Opsional)"
                        placeholder="Catatan tujuan template..."
                    />
                </div>
            </div>

            <!-- Konten Pesan Textarea -->
            <div>
                <flux:textarea
                    wire:model="template_konten"
                    label="Isi Pesan WhatsApp (Format Teks Dinamis)"
                    placeholder="Tuliskan format pesan..."
                    rows="8"
                    required
                />
                @error('template_konten') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
            </div>

            <!-- Panduan Placeholder Cheat Sheet -->
            <div class="p-3.5 bg-zinc-50 dark:bg-zinc-900/70 rounded-xl border border-zinc-200 dark:border-zinc-700 text-xs space-y-2">
                <span class="font-bold text-zinc-800 dark:text-zinc-200 block">Daftar Variabel Dinamis (Placeholder):</span>
                <div class="flex flex-wrap gap-1.5 font-mono text-[11px]">
                    <span class="px-2 py-0.5 bg-white dark:bg-zinc-800 rounded border border-zinc-200 dark:border-zinc-700 text-blue-600 dark:text-blue-400">{nama_pelanggan}</span>
                    <span class="px-2 py-0.5 bg-white dark:bg-zinc-800 rounded border border-zinc-200 dark:border-zinc-700 text-blue-600 dark:text-blue-400">{no_reg}</span>
                    <span class="px-2 py-0.5 bg-white dark:bg-zinc-800 rounded border border-zinc-200 dark:border-zinc-700 text-blue-600 dark:text-blue-400">{no_invoice}</span>
                    <span class="px-2 py-0.5 bg-white dark:bg-zinc-800 rounded border border-zinc-200 dark:border-zinc-700 text-blue-600 dark:text-blue-400">{total_tagihan}</span>
                    <span class="px-2 py-0.5 bg-white dark:bg-zinc-800 rounded border border-zinc-200 dark:border-zinc-700 text-blue-600 dark:text-blue-400">{jatuh_tempo}</span>
                    <span class="px-2 py-0.5 bg-white dark:bg-zinc-800 rounded border border-zinc-200 dark:border-zinc-700 text-blue-600 dark:text-blue-400">{link_pembayaran}</span>
                    <span class="px-2 py-0.5 bg-white dark:bg-zinc-800 rounded border border-zinc-200 dark:border-zinc-700 text-blue-600 dark:text-blue-400">{nama_paket}</span>
                    <span class="px-2 py-0.5 bg-white dark:bg-zinc-800 rounded border border-zinc-200 dark:border-zinc-700 text-indigo-600 dark:text-indigo-400">{nomor_tiket}</span>
                    <span class="px-2 py-0.5 bg-white dark:bg-zinc-800 rounded border border-zinc-200 dark:border-zinc-700 text-indigo-600 dark:text-indigo-400">{jenis_tiket}</span>
                    <span class="px-2 py-0.5 bg-white dark:bg-zinc-800 rounded border border-zinc-200 dark:border-zinc-700 text-indigo-600 dark:text-indigo-400">{status_tiket}</span>
                    <span class="px-2 py-0.5 bg-white dark:bg-zinc-800 rounded border border-zinc-200 dark:border-zinc-700 text-indigo-600 dark:text-indigo-400">{nama_pic}</span>
                    <span class="px-2 py-0.5 bg-white dark:bg-zinc-800 rounded border border-zinc-200 dark:border-zinc-700 text-indigo-600 dark:text-indigo-400">{catatan_histori}</span>
                    <span class="px-2 py-0.5 bg-white dark:bg-zinc-800 rounded border border-zinc-200 dark:border-zinc-700 text-emerald-600 dark:text-emerald-400">{nama_brand}</span>
                    <span class="px-2 py-0.5 bg-white dark:bg-zinc-800 rounded border border-zinc-200 dark:border-zinc-700 text-emerald-600 dark:text-emerald-400">{whatsapp_perusahaan}</span>
                </div>
            </div>

            <div>
                <flux:checkbox
                    wire:model="template_is_aktif"
                    label="Aktifkan Template Ini"
                    description="Template yang dinonaktifkan tidak akan diproses saat ada event pengiriman."
                />
            </div>

            <div class="flex justify-end gap-2 pt-2">
                <flux:button wire:click="$set('showTemplateModal', false)" variant="subtle">Batal</flux:button>
                <flux:button type="submit" variant="primary">Simpan Template</flux:button>
            </div>
        </form>
    </flux:modal>
</section>
