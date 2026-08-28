<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-zinc-900 dark:text-white">SysBlast - Monitoring Antrian Blast</h1>
            <p class="text-sm text-zinc-500 dark:text-zinc-400 mt-1">Pantau status antrean pesan WhatsApp dan SMS, log respon gateway, dan lakukan retry pengiriman pesan.</p>
        </div>
        <div class="flex items-center gap-2 flex-wrap">
            <flux:button wire:click="prosesAntrianSekarang" wire:loading.attr="disabled" variant="subtle" icon="arrow-path" size="sm">
                <span wire:loading.remove wire:target="prosesAntrianSekarang">Proses Antrean Sekarang</span>
                <span wire:loading wire:target="prosesAntrianSekarang">Memproses...</span>
            </flux:button>
        </div>
    </div>

    <!-- Stat Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <flux:card class="p-4 flex items-center gap-4">
            <div class="p-3 rounded-xl bg-blue-50 dark:bg-blue-950/50 text-blue-600 dark:text-blue-400">
                <flux:icon name="queue-list" class="size-6" />
            </div>
            <div>
                <p class="text-xs text-zinc-500 dark:text-zinc-400 font-medium">Total Pesan Terdaftar</p>
                <h3 class="text-xl font-bold text-zinc-900 dark:text-white mt-0.5">{{ $totalAntrian }}</h3>
            </div>
        </flux:card>

        <flux:card class="p-4 flex items-center gap-4">
            <div class="p-3 rounded-xl bg-emerald-50 dark:bg-emerald-950/50 text-emerald-600 dark:text-emerald-400">
                <flux:icon name="check-circle" class="size-6" />
            </div>
            <div>
                <p class="text-xs text-zinc-500 dark:text-zinc-400 font-medium">Berhasil Terkirim</p>
                <h3 class="text-xl font-bold text-emerald-600 dark:text-emerald-400 mt-0.5">{{ $totalTerkirim }}</h3>
            </div>
        </flux:card>

        <flux:card class="p-4 flex items-center gap-4">
            <div class="p-3 rounded-xl bg-amber-50 dark:bg-amber-950/50 text-amber-600 dark:text-amber-400">
                <flux:icon name="clock" class="size-6" />
            </div>
            <div>
                <p class="text-xs text-zinc-500 dark:text-zinc-400 font-medium">Antrean Menunggu</p>
                <h3 class="text-xl font-bold text-amber-600 dark:text-amber-400 mt-0.5">{{ $totalMenunggu }}</h3>
            </div>
        </flux:card>

        <flux:card class="p-4 flex items-center gap-4">
            <div class="p-3 rounded-xl bg-rose-50 dark:bg-rose-950/50 text-rose-600 dark:text-rose-400">
                <flux:icon name="exclamation-circle" class="size-6" />
            </div>
            <div>
                <p class="text-xs text-zinc-500 dark:text-zinc-400 font-medium">Gagal / Error</p>
                <h3 class="text-xl font-bold text-rose-600 dark:text-rose-400 mt-0.5">{{ $totalGagal }}</h3>
            </div>
        </flux:card>
    </div>

    <!-- Filter Bar -->
    <div class="bg-white dark:bg-zinc-800 p-4 rounded-xl border border-zinc-200 dark:border-zinc-700 shadow-sm space-y-3">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
            <div class="lg:col-span-2">
                <flux:input
                    wire:model.live.debounce.300ms="search"
                    icon="magnifying-glass"
                    placeholder="Cari nomor, pesan, error..."
                    clearable
                />
            </div>

            <div>
                <flux:select wire:model.live="status" placeholder="Semua Status">
                    <flux:select.option value="">Semua Status</flux:select.option>
                    @foreach($statusCases as $st)
                        <flux:select.option value="{{ $st->value }}">{{ $st->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            <div>
                <flux:select wire:model.live="sysblas_id" placeholder="Semua Gateway">
                    <flux:select.option value="">Semua Gateway</flux:select.option>
                    @foreach($sysblasKoneksis as $k)
                        <flux:select.option value="{{ $k->id }}">{{ $k->nama }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            <div>
                <flux:input
                    type="date"
                    wire:model.live="tanggal_dari"
                    placeholder="Tanggal Dari"
                />
            </div>
        </div>
    </div>

    <!-- Table -->
    <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-zinc-50 dark:bg-zinc-900/50 border-b border-zinc-200 dark:border-zinc-700 text-zinc-600 dark:text-zinc-400 font-medium text-xs">
                    <tr>
                        <th class="px-4 py-3">Waktu & Jadwal</th>
                        <th class="px-4 py-3">Nomor Tujuan</th>
                        <th class="px-4 py-3">Jenis & Gateway</th>
                        <th class="px-4 py-3">Preview Pesan</th>
                        <th class="px-4 py-3 text-center">Status</th>
                        <th class="px-4 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse($antrianList as $item)
                        <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-700/30 transition-colors">
                            <td class="px-4 py-3 text-xs">
                                <div class="font-mono font-bold text-zinc-900 dark:text-white">
                                    {{ $item->created_at?->format('d M Y H:i') }}
                                </div>
                                <div class="text-[11px] text-zinc-400">
                                    Target: {{ $item->tanggal_kirim?->format('d M Y') }}
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="font-mono font-semibold text-zinc-900 dark:text-white text-xs">
                                    {{ $item->no_hp_tujuan }}
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-1.5 flex-wrap">
                                    <flux:badge size="xs" color="indigo">{{ $item->jenis }}</flux:badge>
                                </div>
                                <div class="text-[11px] text-zinc-500 dark:text-zinc-400 mt-0.5 truncate max-w-[150px]">
                                    {{ $item->sysblas?->nama ?? 'Default Gateway' }}
                                </div>
                            </td>
                            <td class="px-4 py-3 max-w-xs">
                                <p class="text-xs text-zinc-700 dark:text-zinc-300 truncate">
                                    {{ Str::limit($item->pesan, 70) }}
                                </p>
                                @if($item->pesan_error)
                                    <p class="text-[11px] text-rose-600 dark:text-rose-400 truncate mt-0.5 font-medium">
                                        Error: {{ $item->pesan_error }}
                                    </p>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-center">
                                <flux:badge size="xs" :color="$item->status->color()">
                                    {{ $item->status->label() }}
                                </flux:badge>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex items-center justify-end gap-1">
                                    <flux:button wire:click="openDetailModal({{ $item->id }})" variant="subtle" size="xs" icon="eye" title="Lihat Detail Pesan">
                                        Detail
                                    </flux:button>
                                    @if($item->status === \App\Enums\Wa\StatusAntrianWa::Gagal)
                                        <flux:button wire:click="retry({{ $item->id }})" variant="subtle" size="xs" icon="arrow-path" class="text-emerald-600 hover:text-emerald-700" title="Kirim Ulang">
                                            Retry
                                        </flux:button>
                                    @elseif($item->status === \App\Enums\Wa\StatusAntrianWa::Menunggu)
                                        <flux:button wire:click="batalkan({{ $item->id }})" wire:confirm="Batalkan antrean pesan ini?" variant="subtle" size="xs" icon="x-circle" class="text-amber-600 hover:text-amber-700" title="Batalkan">
                                            Batal
                                        </flux:button>
                                    @endif
                                    <flux:button wire:click="hapus({{ $item->id }})" wire:confirm="Hapus data antrean ini?" variant="subtle" size="xs" icon="trash" class="text-rose-600 hover:text-rose-700" title="Hapus">
                                        Hapus
                                    </flux:button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-8 text-zinc-400 text-sm italic">
                                Tidak ada data antrean pesan yang sesuai dengan filter.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($antrianList->hasPages())
            <div class="p-4 border-t border-zinc-100 dark:border-zinc-700">
                {{ $antrianList->links() }}
            </div>
        @endif
    </div>

    <!-- Modal Detail Antrian & Payload Log -->
    <flux:modal :open="$showDetailModal" wire:model.self="showDetailModal" class="max-w-2xl w-full">
        <div class="p-6 space-y-5">
            <!-- Header Modal (pr-8 agar tidak bertabrakan dengan tombol X bawaan Flux) -->
            <div class="flex items-center gap-3.5 pb-4 pr-8 border-b border-zinc-200/80 dark:border-zinc-700/80">
                <div class="p-2.5 rounded-xl bg-emerald-500/10 dark:bg-emerald-500/20 text-emerald-600 dark:text-emerald-400 ring-1 ring-emerald-500/20 shrink-0">
                    <flux:icon name="chat-bubble-left-ellipsis" class="size-6" />
                </div>
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2 flex-wrap">
                        <h3 class="text-base sm:text-lg font-bold text-zinc-900 dark:text-white">Detail Pesan Antrean Blast</h3>
                        @if($selectedAntrian)
                            <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-mono font-semibold bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 border border-zinc-200 dark:border-zinc-700">
                                #{{ $selectedAntrian->id }}
                            </span>
                        @endif
                    </div>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5 truncate">
                        @if($selectedAntrian)
                            Terdaftar pada {{ $selectedAntrian->created_at?->translatedFormat('d M Y, H:i') ?? '-' }} WIB
                        @else
                            Informasi detail pengiriman dan respon gateway
                        @endif
                    </p>
                </div>
            </div>

            @if($selectedAntrian)
                <div class="space-y-4 text-xs">
                    <!-- Stat / Metadata Grid 2x2 agar tidak sempit/overflow -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <!-- Nomor Tujuan -->
                        <div class="p-3.5 bg-zinc-50 dark:bg-zinc-900/60 rounded-xl border border-zinc-200/70 dark:border-zinc-700/60 flex flex-col justify-between space-y-2" x-data="{ copied: false }">
                            <div class="flex items-center justify-between">
                                <span class="text-[11px] font-medium text-zinc-500 dark:text-zinc-400">Nomor Tujuan & Penerima</span>
                                <span x-show="copied" x-cloak class="text-[10px] text-emerald-600 dark:text-emerald-400 font-semibold">Nomor disalin!</span>
                                <span x-show="!copied" class="text-[10px] text-zinc-400">WhatsApp Destination</span>
                            </div>
                            <div class="flex items-center justify-between gap-2 pt-0.5">
                                <span class="font-mono font-bold text-sm sm:text-base text-zinc-900 dark:text-white tracking-wide">
                                    {{ $selectedAntrian->no_hp_tujuan }}
                                </span>
                                <div class="flex items-center gap-1 shrink-0">
                                    <button
                                        type="button"
                                        @click="navigator.clipboard.writeText('{{ $selectedAntrian->no_hp_tujuan }}'); copied = true; setTimeout(() => copied = false, 1800)"
                                        class="inline-flex items-center gap-1 px-2 py-1 rounded-md text-[11px] font-medium bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 hover:bg-zinc-100 dark:hover:bg-zinc-700/70 text-zinc-600 dark:text-zinc-300 transition-colors shadow-xs"
                                        title="Salin Nomor"
                                    >
                                        <flux:icon x-show="!copied" name="clipboard-document" class="size-3.5" />
                                        <flux:icon x-show="copied" x-cloak name="check" class="size-3.5 text-emerald-500" />
                                        <span>Salin</span>
                                    </button>
                                    <a
                                        href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $selectedAntrian->no_hp_tujuan) }}"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="p-1 rounded-md bg-emerald-50 dark:bg-emerald-950/60 border border-emerald-200 dark:border-emerald-800/60 hover:bg-emerald-100 dark:hover:bg-emerald-900/60 text-emerald-600 dark:text-emerald-400 transition-colors shadow-xs"
                                        title="Buka Chat di WhatsApp"
                                    >
                                        <flux:icon name="arrow-top-right-on-square" class="size-4" />
                                    </a>
                                </div>
                            </div>
                        </div>

                        <!-- Status Pengiriman -->
                        <div class="p-3.5 bg-zinc-50 dark:bg-zinc-900/60 rounded-xl border border-zinc-200/70 dark:border-zinc-700/60 flex flex-col justify-between space-y-2">
                            <div class="flex items-center justify-between">
                                <span class="text-[11px] font-medium text-zinc-500 dark:text-zinc-400">Status Pengiriman</span>
                                <span class="text-[10px] text-zinc-400">Delivery Status</span>
                            </div>
                            <div class="flex items-center justify-between gap-2 pt-0.5">
                                <flux:badge size="sm" :color="$selectedAntrian->status->color()" class="font-semibold">
                                    {{ $selectedAntrian->status->label() }}
                                </flux:badge>
                                <span class="text-[11px] font-medium text-zinc-500 dark:text-zinc-400">
                                    @if($selectedAntrian->dikirim_pada)
                                        Terkirim: {{ $selectedAntrian->dikirim_pada->format('H:i:s') }} WIB
                                    @elseif($selectedAntrian->dijadwalkan_pada)
                                        Jadwal: {{ $selectedAntrian->dijadwalkan_pada->format('d M H:i') }}
                                    @else
                                        Target: {{ $selectedAntrian->tanggal_kirim?->format('d M Y') }}
                                    @endif
                                </span>
                            </div>
                        </div>

                        <!-- Jenis Pesan -->
                        <div class="p-3.5 bg-zinc-50 dark:bg-zinc-900/60 rounded-xl border border-zinc-200/70 dark:border-zinc-700/60 flex flex-col justify-between space-y-2">
                            <div class="flex items-center justify-between">
                                <span class="text-[11px] font-medium text-zinc-500 dark:text-zinc-400">Jenis Pesan</span>
                                <span class="text-[10px] text-zinc-400">Category</span>
                            </div>
                            <div class="flex items-center justify-between gap-2 pt-0.5 flex-wrap">
                                <flux:badge size="sm" color="indigo" class="font-medium">
                                    {{ $selectedAntrian->jenis }}
                                </flux:badge>
                                <span class="text-[11px] text-zinc-500 dark:text-zinc-400 truncate">
                                    @if($selectedAntrian->referensi_tipe)
                                        Ref: {{ class_basename($selectedAntrian->referensi_tipe) }} #{{ $selectedAntrian->referensi_id }}
                                    @else
                                        Pesan Langsung
                                    @endif
                                </span>
                            </div>
                        </div>

                        <!-- Percobaan Kirim -->
                        <div class="p-3.5 bg-zinc-50 dark:bg-zinc-900/60 rounded-xl border border-zinc-200/70 dark:border-zinc-700/60 flex flex-col justify-between space-y-2">
                            <div class="flex items-center justify-between">
                                <span class="text-[11px] font-medium text-zinc-500 dark:text-zinc-400">Percobaan Kirim</span>
                                <span class="text-[10px] text-zinc-400">Retry Counter</span>
                            </div>
                            <div class="flex items-center justify-between gap-2 pt-0.5">
                                <div class="flex items-center gap-1.5">
                                    <span class="font-bold text-sm text-zinc-900 dark:text-white">
                                        {{ $selectedAntrian->percobaan_ke }}x
                                    </span>
                                    <span class="text-[11px] text-zinc-400">percobaan</span>
                                </div>
                                <span class="text-[11px] text-zinc-500 dark:text-zinc-400">
                                    Update: {{ $selectedAntrian->updated_at?->format('d M H:i') }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Gateway yang Digunakan -->
                    <div class="p-3.5 bg-gradient-to-r from-zinc-50 to-white dark:from-zinc-900/80 dark:to-zinc-800/50 rounded-xl border border-zinc-200 dark:border-zinc-700/80 flex items-center justify-between gap-3 flex-wrap">
                        <div class="flex items-center gap-3 min-w-0">
                            <div class="p-2 rounded-lg bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-300 shrink-0">
                                <flux:icon name="server-stack" class="size-5" />
                            </div>
                            <div class="min-w-0">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="font-bold text-xs sm:text-sm text-zinc-900 dark:text-white">
                                        {{ $selectedAntrian->sysblas?->nama ?? 'Default System Gateway' }}
                                    </span>
                                    @if($selectedAntrian->sysblas)
                                        <flux:badge size="xs" :color="$selectedAntrian->sysblas->provider->color()">
                                            {{ $selectedAntrian->sysblas->provider->label() }}
                                        </flux:badge>
                                    @endif
                                </div>
                                <div class="flex items-center gap-2 text-[11px] text-zinc-400 mt-0.5 font-mono flex-wrap">
                                    @if($selectedAntrian->sysblas?->nomor)
                                        <span>Sender: {{ $selectedAntrian->sysblas->nomor }}</span>
                                        <span>•</span>
                                    @endif
                                    <span class="truncate">{{ $selectedAntrian->sysblas?->url_api ?? 'Konfigurasi Default Services' }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Format Teks Pesan Lengkap (WhatsApp Bubble) -->
                    <div class="space-y-1.5" x-data="{ msgCopied: false }">
                        <div class="flex items-center justify-between text-xs">
                            <span class="font-semibold text-zinc-700 dark:text-zinc-300 flex items-center gap-1.5">
                                <flux:icon name="chat-bubble-left" class="size-4 text-emerald-500" />
                                Isi Pesan WhatsApp
                            </span>
                            <div class="flex items-center gap-2.5">
                                <span class="text-[11px] text-zinc-400 font-mono">
                                    {{ mb_strlen($selectedAntrian->pesan) }} karakter
                                </span>
                                <button
                                    type="button"
                                    @click="navigator.clipboard.writeText($refs.messageContent.innerText.trim()); msgCopied = true; setTimeout(() => msgCopied = false, 2000)"
                                    class="inline-flex items-center gap-1 text-[11px] font-medium text-emerald-600 dark:text-emerald-400 hover:text-emerald-700 dark:hover:text-emerald-300 transition-colors"
                                >
                                    <flux:icon x-show="!msgCopied" name="clipboard-document" class="size-3.5" />
                                    <flux:icon x-show="msgCopied" x-cloak name="check" class="size-3.5 text-emerald-500" />
                                    <span x-text="msgCopied ? 'Tersalin!' : 'Salin Pesan'">Salin Pesan</span>
                                </button>
                            </div>
                        </div>

                        <!-- Chat Bubble Box -->
                        <div class="p-4 bg-emerald-50/60 dark:bg-zinc-900/90 rounded-2xl rounded-tl-sm border border-emerald-200/70 dark:border-zinc-700/80 shadow-sm relative text-left">
                            <div x-ref="messageContent" class="text-xs sm:text-sm text-zinc-800 dark:text-zinc-200 whitespace-pre-wrap font-sans leading-relaxed break-words text-left selection:bg-emerald-200 dark:selection:bg-emerald-900">
                                {{ $selectedAntrian->pesan }}
                            </div>
                            <div class="flex items-center justify-end gap-1 text-[10px] text-zinc-400 dark:text-zinc-500 mt-2 font-mono">
                                <span>{{ $selectedAntrian->dikirim_pada?->format('H:i') ?? $selectedAntrian->created_at?->format('H:i') }}</span>
                                @if($selectedAntrian->status === \App\Enums\Wa\StatusAntrianWa::Terkirim)
                                    <flux:icon name="check" class="size-3 text-emerald-500" />
                                @endif
                            </div>
                        </div>
                    </div>

                    <!-- Pesan Error (Jika Gagal) -->
                    @if($selectedAntrian->pesan_error)
                        <div class="p-3.5 rounded-xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800/70 text-rose-800 dark:text-rose-200 text-xs flex items-start gap-3">
                            <div class="p-1 rounded-md bg-rose-100 dark:bg-rose-900/60 text-rose-600 dark:text-rose-400 shrink-0 mt-0.5">
                                <flux:icon name="exclamation-triangle" class="size-4" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <span class="font-bold block text-rose-900 dark:text-rose-100">Pesan Kesalahan / Alasan Gagal:</span>
                                <p class="mt-0.5 text-rose-700 dark:text-rose-300 font-mono text-[11px] leading-relaxed break-words">
                                    {{ $selectedAntrian->pesan_error }}
                                </p>
                            </div>
                        </div>
                    @endif

                    <!-- Raw Response Gateway (JSON Log) -->
                    @if($selectedAntrian->response_log)
                        <div class="space-y-1.5" x-data="{ jsonCopied: false }">
                            <div class="flex items-center justify-between text-xs">
                                <span class="font-semibold text-zinc-700 dark:text-zinc-300 flex items-center gap-1.5">
                                    <flux:icon name="code-bracket" class="size-4 text-zinc-500" />
                                    Response Gateway (JSON Log)
                                </span>
                                <button
                                    type="button"
                                    @click="navigator.clipboard.writeText($refs.jsonPayload.innerText.trim()); jsonCopied = true; setTimeout(() => jsonCopied = false, 2000)"
                                    class="inline-flex items-center gap-1 text-[11px] font-medium text-zinc-500 hover:text-zinc-800 dark:text-zinc-400 dark:hover:text-zinc-200 transition-colors"
                                >
                                    <flux:icon x-show="!jsonCopied" name="clipboard-document" class="size-3.5" />
                                    <flux:icon x-show="jsonCopied" x-cloak name="check" class="size-3.5 text-emerald-500" />
                                    <span x-text="jsonCopied ? 'Tersalin!' : 'Salin JSON'">Salin JSON</span>
                                </button>
                            </div>

                            <div class="rounded-xl bg-zinc-950 border border-zinc-800 overflow-hidden shadow-inner">
                                <div class="px-3 py-1.5 bg-zinc-900/90 border-b border-zinc-800 flex items-center justify-between">
                                    <div class="flex items-center gap-1.5">
                                        <span class="size-2 rounded-full bg-rose-500/80 inline-block"></span>
                                        <span class="size-2 rounded-full bg-amber-500/80 inline-block"></span>
                                        <span class="size-2 rounded-full bg-emerald-500/80 inline-block"></span>
                                        <span class="text-[10px] font-mono text-zinc-400 ml-2">raw_response.json</span>
                                    </div>
                                </div>
                                <pre x-ref="jsonPayload" class="p-3.5 text-emerald-400 text-[11px] overflow-x-auto max-h-48 font-mono leading-relaxed select-all">{{ json_encode($selectedAntrian->response_log, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                            </div>
                        </div>
                    @endif
                </div>
            @endif

            <!-- Modal Footer -->
            <div class="flex items-center justify-between pt-3 border-t border-zinc-200/80 dark:border-zinc-700/80">
                <div>
                    @if($selectedAntrian)
                        @if($selectedAntrian->status === \App\Enums\Wa\StatusAntrianWa::Gagal)
                            <flux:button
                                wire:click="retry({{ $selectedAntrian->id }})"
                                wire:loading.attr="disabled"
                                variant="primary"
                                size="sm"
                                icon="arrow-path"
                            >
                                <span wire:loading.remove wire:target="retry({{ $selectedAntrian->id }})">Kirim Ulang (Retry)</span>
                                <span wire:loading wire:target="retry({{ $selectedAntrian->id }})">Mengantrekan...</span>
                            </flux:button>
                        @elseif($selectedAntrian->status === \App\Enums\Wa\StatusAntrianWa::Menunggu)
                            <flux:button
                                wire:click="batalkan({{ $selectedAntrian->id }})"
                                wire:confirm="Batalkan antrean pesan ini?"
                                wire:loading.attr="disabled"
                                variant="danger"
                                size="sm"
                                icon="x-circle"
                            >
                                <span wire:loading.remove wire:target="batalkan({{ $selectedAntrian->id }})">Batalkan Antrean</span>
                                <span wire:loading wire:target="batalkan({{ $selectedAntrian->id }})">Membatalkan...</span>
                            </flux:button>
                        @endif
                    @endif
                </div>
                <div class="flex items-center gap-2">
                    <flux:button wire:click="$set('showDetailModal', false)" variant="subtle" size="sm">
                        Tutup
                    </flux:button>
                </div>
            </div>
        </div>
    </flux:modal>
</div>
