<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-zinc-900 dark:text-white">Aturan Pengingat Tagihan</h1>
            <p class="text-sm text-zinc-500 dark:text-zinc-400 mt-1">Konfigurasi jadwal dan template WhatsApp otomatis untuk tagihan sebelum & sesudah jatuh tempo.</p>
        </div>
        <div class="flex items-center gap-2 flex-wrap">
            <flux:button wire:click="jalankanPengingatManual" wire:loading.attr="disabled" variant="subtle" icon="arrow-path" size="sm">
                <span wire:loading.remove wire:target="jalankanPengingatManual">Eksekusi Pengingat Sekarang</span>
                <span wire:loading wire:target="jalankanPengingatManual">Memproses Pengingat...</span>
            </flux:button>
            <flux:button wire:click="openCreateModal" variant="primary" icon="plus" size="sm">
                Tambah Aturan Baru
            </flux:button>
        </div>
    </div>

    <!-- Stat Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <flux:card class="p-4 flex items-center gap-4">
            <div class="p-3 rounded-xl bg-blue-50 dark:bg-blue-950/50 text-blue-600 dark:text-blue-400">
                <flux:icon name="bell-alert" class="size-6" />
            </div>
            <div>
                <p class="text-xs text-zinc-500 dark:text-zinc-400 font-medium">Aturan Pengingat Aktif</p>
                <h3 class="text-xl font-bold text-zinc-900 dark:text-white mt-0.5">{{ $totalAktif }} <span class="text-xs font-normal text-zinc-500">dari {{ $aturanList->count() }} aturan</span></h3>
            </div>
        </flux:card>

        <flux:card class="p-4 flex items-center gap-4">
            <div class="p-3 rounded-xl bg-amber-50 dark:bg-amber-950/50 text-amber-600 dark:text-amber-400">
                <flux:icon name="queue-list" class="size-6" />
            </div>
            <div>
                <p class="text-xs text-zinc-500 dark:text-zinc-400 font-medium">Antrean WA Hari Ini</p>
                <h3 class="text-xl font-bold text-zinc-900 dark:text-white mt-0.5">{{ $totalAntreanHariIni }} <span class="text-xs font-normal text-zinc-500">pesan diantrikan</span></h3>
            </div>
        </flux:card>

        <flux:card class="p-4 flex items-center gap-4">
            <div class="p-3 rounded-xl bg-emerald-50 dark:bg-emerald-950/50 text-emerald-600 dark:text-emerald-400">
                <flux:icon name="check-circle" class="size-6" />
            </div>
            <div>
                <p class="text-xs text-zinc-500 dark:text-zinc-400 font-medium">Berhasil Terkirim Hari Ini</p>
                <h3 class="text-xl font-bold text-emerald-600 dark:text-emerald-400 mt-0.5">{{ $totalTerkirimHariIni }} <span class="text-xs font-normal text-zinc-500">pesan sukses</span></h3>
            </div>
        </flux:card>
    </div>

    <!-- Tabel Aturan Pengingat -->
    <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 shadow-sm overflow-hidden">
        <div class="p-4 border-b border-zinc-100 dark:border-zinc-700 flex items-center justify-between">
            <h3 class="font-semibold text-zinc-900 dark:text-white text-sm flex items-center gap-2">
                <flux:icon name="adjustments-horizontal" class="size-4 text-blue-600" />
                Daftar Aturan Otomatis
            </h3>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-zinc-50 dark:bg-zinc-900/50 border-b border-zinc-200 dark:border-zinc-700 text-zinc-600 dark:text-zinc-400 font-medium text-xs">
                    <tr>
                        <th class="px-4 py-3">Nama Aturan</th>
                        <th class="px-4 py-3">Tipe / Offset Hari</th>
                        <th class="px-4 py-3">Jam Kirim</th>
                        <th class="px-4 py-3">Template WhatsApp</th>
                        <th class="px-4 py-3">Kirim Ulang</th>
                        <th class="px-4 py-3 text-center">Status</th>
                        <th class="px-4 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse($aturanList as $aturan)
                        <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-700/30 transition-colors">
                            <td class="px-4 py-3 font-semibold text-zinc-900 dark:text-white">
                                {{ $aturan->nama_aturan }}
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <flux:badge size="sm" :color="$aturan->tipe_pengingat->color()">
                                        {{ $aturan->tipe_pengingat->label() }}
                                    </flux:badge>
                                    <span class="text-xs font-mono font-bold text-zinc-700 dark:text-zinc-300">
                                        @if($aturan->tipe_pengingat->value === 'sebelum_jatuh_tempo')
                                            H-{{ $aturan->hari_offset }}
                                        @elseif($aturan->tipe_pengingat->value === 'hari_h')
                                            Hari-H (H0)
                                        @else
                                            H+{{ $aturan->hari_offset }}
                                        @endif
                                    </span>
                                </div>
                            </td>
                            <td class="px-4 py-3 font-mono text-xs text-zinc-800 dark:text-zinc-200">
                                {{ \Illuminate\Support\Carbon::parse($aturan->jam_eksekusi)->format('H:i') }} WIB
                            </td>
                            <td class="px-4 py-3">
                                @if($aturan->template)
                                    <span class="text-xs font-medium text-blue-600 dark:text-blue-400">
                                        {{ $aturan->template->nama }}
                                    </span>
                                    <div class="text-[11px] text-zinc-400 font-mono font-light">
                                        [{{ $aturan->template->kode }}]
                                    </div>
                                @else
                                    <span class="text-xs text-rose-500 italic">Template Belum Diatur</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-xs">
                                @if($aturan->kirim_ulang_berkala)
                                    <span class="text-emerald-600 font-medium flex items-center gap-1">
                                        <flux:icon name="arrow-path" class="size-3" />
                                        Tiap {{ $aturan->interval_hari }} Hari
                                    </span>
                                @else
                                    <span class="text-zinc-400">Sekali Saja</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-center">
                                <button
                                    type="button"
                                    wire:click="toggleStatus({{ $aturan->id }})"
                                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium cursor-pointer transition-colors {{ $aturan->is_aktif ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300' : 'bg-zinc-100 text-zinc-600 dark:bg-zinc-700 dark:text-zinc-400' }}"
                                >
                                    {{ $aturan->is_aktif ? 'Aktif' : 'Nonaktif' }}
                                </button>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex items-center justify-end gap-1">
                                    <flux:button wire:click="openEditModal({{ $aturan->id }})" variant="subtle" size="xs" icon="pencil-square">
                                        Edit
                                    </flux:button>
                                    <flux:button wire:click="hapus({{ $aturan->id }})" wire:confirm="Yakin ingin menghapus aturan pengingat ini?" variant="subtle" size="xs" icon="trash" class="text-rose-600 hover:text-rose-700">
                                        Hapus
                                    </flux:button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-8 text-zinc-400 text-sm italic">
                                Belum ada aturan pengingat tagihan yang dibuat.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal Form Tambah / Edit Aturan -->
    <flux:modal :open="$showModal" wire:model.self="showModal" class="max-w-lg">
        <form wire:submit="simpan" class="p-6 space-y-4">
            <div class="flex items-center gap-3 text-blue-600">
                <flux:icon name="bell-alert" class="size-6" />
                <h3 class="text-lg font-bold text-zinc-900 dark:text-white">
                    {{ $editingId ? 'Edit Aturan Pengingat' : 'Tambah Aturan Pengingat' }}
                </h3>
            </div>

            <div>
                <flux:input
                    wire:model="nama_aturan"
                    label="Nama Aturan"
                    placeholder="Contoh: Pengingat H-3 Tagihan Baru Terbit"
                    required
                />
                @error('nama_aturan') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <flux:select wire:model.live="tipe_pengingat" label="Tipe Pengingat" required>
                        @foreach($tipeList as $t)
                            <flux:select.option value="{{ $t->value }}">{{ $t->label() }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    @error('tipe_pengingat') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
                </div>

                <div>
                    <flux:input
                        type="number"
                        wire:model="hari_offset"
                        label="Offset Hari (0 = Hari H)"
                        min="0"
                        max="90"
                        required
                    />
                    @error('hari_offset') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <flux:input
                        type="time"
                        wire:model="jam_eksekusi"
                        label="Jam Eksekusi Harian"
                        required
                    />
                    <flux:description>Waktu pengiriman pengingat setiap hari.</flux:description>
                    @error('jam_eksekusi') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
                </div>

                <div>
                    <flux:select wire:model="template_id" label="Template Pesan WhatsApp" required>
                        <flux:select.option value="">-- Pilih Template --</flux:select.option>
                        @foreach($templates as $tmpl)
                            <flux:select.option value="{{ $tmpl->id }}">
                                {{ $tmpl->nama }} [{{ $tmpl->kode }}]
                            </flux:select.option>
                        @endforeach
                    </flux:select>
                    @error('template_id') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
                </div>
            </div>

            <div class="p-3 bg-zinc-50 dark:bg-zinc-900 rounded-lg space-y-3 border border-zinc-200 dark:border-zinc-700">
                <flux:checkbox
                    wire:model.live="kirim_ulang_berkala"
                    label="Ulangi Pengiriman Berkala (Khusus Tunggakan)"
                    description="Kirimkan pengingat ulang setiap N hari jika tagihan masih belum lunas."
                />

                @if($kirim_ulang_berkala)
                    <div>
                        <flux:input
                            type="number"
                            wire:model="interval_hari"
                            label="Interval Pengulangan (Hari)"
                            placeholder="Contoh: 3 (Ulangi tiap 3 hari)"
                            min="1"
                            max="30"
                        />
                        @error('interval_hari') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
                    </div>
                @endif
            </div>

            <div>
                <flux:checkbox
                    wire:model="is_aktif"
                    label="Aktifkan Aturan Ini"
                    description="Jika tidak dicentang, aturan ini tidak akan dijalankan oleh scheduler."
                />
            </div>

            <div class="flex justify-end gap-2 pt-2">
                <flux:button wire:click="$set('showModal', false)" variant="subtle">Batal</flux:button>
                <flux:button type="submit" variant="primary">Simpan Aturan</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
