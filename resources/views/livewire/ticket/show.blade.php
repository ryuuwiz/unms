<div class="max-w-6xl mx-auto space-y-6">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div class="flex items-center gap-3">
            <flux:button href="{{ route('ticket.index') }}" variant="subtle" icon="arrow-left" size="sm" wire:navigate />
            <div>
                <div class="flex items-center gap-2 flex-wrap">
                    <h1 class="text-2xl font-bold font-mono text-zinc-900 dark:text-white">{{ $ticket->nomor_ticket }}</h1>
                    <flux:badge size="sm" :color="$ticket->status->color()">
                        {{ $ticket->status->label() }}
                    </flux:badge>
                    <flux:badge size="sm" :color="$ticket->prioritas->color()">
                        {{ $ticket->prioritas->label() }}
                    </flux:badge>
                    <flux:badge size="sm" :color="$ticket->jenis->color()" :icon="$ticket->jenis->icon()">
                        {{ $ticket->jenis->label() }}
                    </flux:badge>
                </div>
                <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-1">
                    Dibuat pada {{ $ticket->created_at->format('d M Y, H:i') }} oleh {{ $ticket->dibuatOleh?->name ?? 'Sistem' }}
                    @if($ticket->divisis->isNotEmpty())
                        • Divisi: {{ $ticket->divisis->map(fn($d) => $d->divisi->label())->implode(', ') }}
                    @endif
                </p>
            </div>
        </div>

        <div class="flex items-center gap-2 flex-wrap">
            <flux:button wire:click="openCatatanModal" variant="subtle" icon="chat-bubble-left-ellipsis" size="sm">
                Tambah Catatan
            </flux:button>

            @can('assignPic', $ticket)
                <flux:button wire:click="openAssignPicModal" variant="subtle" icon="user-plus" size="sm">
                    Assign PIC
                </flux:button>
            @endcan

            @if(!$ticket->status->isTerminal() && count($transisiValid) > 0)
                <flux:button wire:click="openUbahStatusModal" variant="primary" icon="arrow-path" size="sm">
                    Ubah Status
                </flux:button>
            @endif
        </div>
    </div>

    <!-- Alert / Banner Notifikasi Khusus -->
    @if($ticket->perlu_aktivasi_manual)
        <div class="p-4 bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800/60 rounded-xl flex items-start gap-3">
            <flux:icon name="exclamation-triangle" class="size-5 text-amber-600 dark:text-amber-400 shrink-0 mt-0.5" />
            <div>
                <h4 class="text-sm font-bold text-amber-900 dark:text-amber-200">Perlu Data Registrasi Billing</h4>
                <p class="text-xs text-amber-700 dark:text-amber-300 mt-0.5">
                    Pemasangan telah selesai di lapangan. Admin perlu membuat Data Registrasi Billing agar PPP Secret terprovisi, layanan aktif, dan tagihan pertama terbit.
                </p>
                @can('create', \App\Models\LayananPelanggan::class)
                    <flux:button size="sm" variant="primary" class="mt-2" :href="route('layanan-pelanggan.create', ['pelanggan' => $ticket->pelanggan_id, 'ticket_id' => $ticket->id])" wire:navigate>
                        Buat Data Registrasi Billing
                    </flux:button>
                @endcan
            </div>
        </div>
    @endif

    @if($ticket->perluInvoicePindahAlamat())
        <div class="p-4 bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800/60 rounded-xl flex items-start gap-3">
            <flux:icon name="exclamation-triangle" class="size-5 text-amber-600 dark:text-amber-400 shrink-0 mt-0.5" />
            <div>
                <h4 class="text-sm font-bold text-amber-900 dark:text-amber-200">Perlu Invoice Biaya Pindah Alamat</h4>
                <p class="text-xs text-amber-700 dark:text-amber-300 mt-0.5">
                    Pindah alamat telah selesai. Admin perlu menerbitkan invoice manual untuk biaya pindah alamat pelanggan ini.
                </p>
                @can('create', \App\Models\Invoice::class)
                    <flux:button size="sm" variant="primary" class="mt-2" :href="route('invoice.create', $ticket->pelanggan_id)" wire:navigate>
                        Buat Invoice Manual
                    </flux:button>
                @endcan
            </div>
        </div>
    @endif

    @if($ticket->isOverdue())
        <div class="p-4 bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800/60 rounded-xl flex items-start gap-3">
            <flux:icon name="clock" class="size-5 text-rose-600 dark:text-rose-400 shrink-0 mt-0.5" />
            <div>
                <h4 class="text-sm font-bold text-rose-900 dark:text-rose-200">Batas Waktu SLA Terlewat (Overdue)</h4>
                <p class="text-xs text-rose-700 dark:text-rose-300 mt-0.5">
                    Target penyelesaian tiket ini adalah <strong>{{ $ticket->sla_target_selesai?->format('d M Y, H:i') }}</strong> ({{ $ticket->sisaWaktuSla() }}). Harap segera lakukan tindak lanjut prioritas.
                </p>
            </div>
        </div>
    @endif

    <!-- Konten Informasi Utama Tiket (Sesuai data_unms.md) -->
    <div class="space-y-6">
        <!-- 1. Ringkasan Ticket & Infra Jaringan (Full Width) -->
        <div class="bg-white dark:bg-zinc-800 p-6 rounded-xl border border-zinc-200 dark:border-zinc-700 shadow-sm space-y-4">
            <div class="flex items-center justify-between border-b border-zinc-100 dark:border-zinc-700/60 pb-3">
                <h3 class="font-bold text-base text-zinc-900 dark:text-white flex items-center gap-2">
                    <flux:icon name="ticket" class="size-5 text-blue-600 dark:text-blue-400" />
                    Ringkasan Ticket & Infra
                </h3>
                <div class="text-xs font-medium text-zinc-500">
                    Target SLA: <span class="font-semibold text-zinc-800 dark:text-zinc-200">{{ $ticket->sla_target_selesai?->format('d M Y, H:i') ?? '-' }}</span>
                </div>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4 text-xs">
                <div>
                    <span class="text-zinc-500 block mb-1">Jenis Tiket</span>
                    <span class="font-semibold text-zinc-900 dark:text-white text-sm">{{ $ticket->jenis->label() }}</span>
                </div>
                <div>
                    <span class="text-zinc-500 block mb-1">Prioritas</span>
                    <span class="font-semibold text-zinc-900 dark:text-white text-sm">{{ $ticket->prioritas->label() }}</span>
                </div>
                <div>
                    <span class="text-zinc-500 block mb-1">Divisi</span>
                    <div class="flex flex-wrap gap-1">
                        @forelse($ticket->divisis as $d)
                            <flux:badge size="xs">{{ $d->divisi->label() }}</flux:badge>
                        @empty
                            <span class="text-zinc-400">-</span>
                        @endforelse
                    </div>
                </div>
                <div>
                    <span class="text-zinc-500 block mb-1">Waktu Dibuat</span>
                    <span class="font-medium text-zinc-800 dark:text-zinc-200 text-xs">{{ $ticket->created_at->format('d/m/Y H:i') }}</span>
                </div>
                <div>
                    <span class="text-zinc-500 block mb-1">Jadwal Lapangan</span>
                    <span class="font-medium text-zinc-800 dark:text-zinc-200 text-xs">
                        {{ $ticket->dijadwalkan_pada ? $ticket->dijadwalkan_pada->format('d/m/Y H:i') : 'Belum dijadwalkan' }}
                    </span>
                </div>
                <div>
                    <span class="text-zinc-500 block mb-1">PIC Staf</span>
                    <span class="font-semibold text-zinc-900 dark:text-white text-sm">
                        {{ $ticket->pic?->name ?? 'Belum Ditugaskan' }}
                    </span>
                </div>
            </div>

            <!-- Bagian Infra Jaringan -->
            <div class="pt-3 border-t border-zinc-100 dark:border-zinc-700/60">
                <h4 class="text-xs font-bold text-zinc-700 dark:text-zinc-300 uppercase tracking-wider mb-2">Infrastruktur Jaringan</h4>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 bg-zinc-50 dark:bg-zinc-900/50 p-3.5 rounded-lg text-xs">
                    <div>
                        <span class="text-zinc-500 block">Router Gateway:</span>
                        <span class="font-semibold text-zinc-900 dark:text-white text-sm">
                            {{ $ticket->layananPelanggan?->router?->nama_router ?? '-' }}
                        </span>
                        @if($ticket->layananPelanggan?->router)
                            <div class="text-[11px] text-zinc-500 font-mono mt-0.5">{{ $ticket->layananPelanggan->router->ip_address }}</div>
                        @endif
                    </div>
                    <div>
                        <span class="text-zinc-500 block">Titik ODP:</span>
                        <span class="font-semibold text-zinc-900 dark:text-white text-sm">
                            {{ $ticket->layananPelanggan?->odpPort?->odp?->nama_odp ?? '-' }}
                        </span>
                        @if($ticket->layananPelanggan?->odpPort?->odp)
                            <div class="text-[11px] text-zinc-500 mt-0.5">Kapasitas: {{ $ticket->layananPelanggan->odpPort->odp->kapasitas_port }} Port</div>
                        @endif
                    </div>
                    <div>
                        <span class="text-zinc-500 block">Port ODP:</span>
                        <span class="font-semibold text-zinc-900 dark:text-white text-sm">
                            {{ $ticket->layananPelanggan?->odpPort ? 'Port ' . $ticket->layananPelanggan->odpPort->nomor_port : '-' }}
                        </span>
                        @if($ticket->layananPelanggan?->odpPort)
                            <div class="text-[11px] text-zinc-500 mt-0.5">Status: {{ $ticket->layananPelanggan->odpPort->status->label() }}</div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        @if ($ticket->jenis->value === 'pemasangan' && $ticket->layanan_pelanggan_id)
            <!-- Status Per-Divisi & Aktivasi Pemasangan -->
            <div class="bg-white dark:bg-zinc-800 p-6 rounded-xl border border-zinc-200 dark:border-zinc-700 shadow-sm space-y-5">
                <div class="flex items-center justify-between border-b border-zinc-100 dark:border-zinc-700/60 pb-3">
                    <h3 class="font-bold text-base text-zinc-900 dark:text-white flex items-center gap-2">
                        <flux:icon name="clipboard-document-check" class="size-5 text-emerald-600 dark:text-emerald-400" />
                        Status Per-Divisi Pemasangan
                    </h3>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs">
                    @foreach (\App\Models\Ticket::DIVISI_WAJIB_PEMASANGAN as $divisi)
                        @php $statusDivisi = $ticket->statusDivisi($divisi); @endphp
                        <div class="p-3 rounded-lg border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-900/40 space-y-2">
                            <div class="font-semibold text-zinc-800 dark:text-zinc-200">{{ $divisi->label() }}</div>
                            <flux:badge size="xs" :color="$statusDivisi->color()">{{ $statusDivisi->label() }}</flux:badge>
                            @can('ubahStatusDivisi', [$ticket, $divisi])
                                @if ($statusDivisi !== \App\Enums\Ticket\StatusDivisiTicket::Selesai)
                                    <flux:button size="xs" variant="primary" class="w-full" wire:click="tandaiDivisiSelesai('{{ $divisi->value }}')" wire:confirm="Tandai {{ $divisi->label() }} selesai?">
                                        Tandai Selesai
                                    </flux:button>
                                @endif
                            @endcan
                        </div>
                    @endforeach
                </div>

                <!-- Teknisi Tahap 1: ODP+Port & Foto Pemasangan -->
                <div class="pt-4 border-t border-zinc-100 dark:border-zinc-700/60 space-y-3">
                    <h4 class="text-sm font-bold text-zinc-800 dark:text-zinc-200">Progress Lapangan Teknisi (Tahap 1)</h4>

                    @can('ubahStatusDivisi', [$ticket, \App\Enums\Ticket\DivisiTicket::Teknisi])
                        <form wire:submit="simpanProgressLapangan" class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <flux:field>
                                <flux:label>ODP</flux:label>
                                <flux:select wire:model.live="odp_id" placeholder="Pilih ODP...">
                                    <flux:select.option value="">-- Pilih ODP --</flux:select.option>
                                    @foreach ($odps as $odp)
                                        <flux:select.option value="{{ $odp->id }}">{{ $odp->nama_odp }}</flux:select.option>
                                    @endforeach
                                </flux:select>
                                <flux:error name="odp_id" />
                            </flux:field>

                            <flux:field>
                                <flux:label>Port ODP</flux:label>
                                <flux:select wire:model="odp_port_id" placeholder="Pilih Port..." :disabled="! $odp_id">
                                    <flux:select.option value="">-- Pilih Port --</flux:select.option>
                                    @foreach ($odpPorts as $port)
                                        <flux:select.option value="{{ $port->id }}">Port {{ $port->nomor_port }}</flux:select.option>
                                    @endforeach
                                </flux:select>
                                <flux:error name="odp_port_id" />
                            </flux:field>

                            <div class="sm:col-span-2">
                                <flux:field>
                                    <flux:label>Foto Bukti Pemasangan (bisa lebih dari satu)</flux:label>
                                    <input type="file" wire:model="fotoPemasangan" multiple accept="image/*" class="block w-full text-xs text-zinc-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100" />
                                    <flux:error name="fotoPemasangan.*" />
                                </flux:field>
                            </div>

                            <div class="sm:col-span-2">
                                <flux:button type="submit" size="sm" variant="primary">Simpan Progress</flux:button>
                            </div>
                        </form>
                    @endcan

                    @if ($ticket->getMedia('foto_pemasangan')->isNotEmpty())
                        <div class="flex flex-wrap gap-2 pt-2">
                            @foreach ($ticket->getMedia('foto_pemasangan') as $media)
                                <a href="{{ $media->getUrl() }}" target="_blank">
                                    <img src="{{ $media->getUrl() }}" class="h-20 w-20 object-cover rounded-lg border border-zinc-200 dark:border-zinc-700" />
                                </a>
                            @endforeach
                        </div>
                    @endif
                </div>

                <!-- Aktivasi Pemasangan (NOC) -->
                <div class="pt-4 border-t border-zinc-100 dark:border-zinc-700/60">
                    @if ($ticket->pemasangan?->sudahDiaktivasi())
                        <div class="p-3 rounded-lg bg-emerald-50 dark:bg-emerald-950/30 text-emerald-800 dark:text-emerald-300 text-xs flex items-center gap-2">
                            <flux:icon name="check-circle" class="size-4" />
                            Diaktivasi pada {{ $ticket->pemasangan->diaktivasi_pada->format('d M Y, H:i') }} oleh {{ $ticket->pemasangan->diaktivasiOleh?->name ?? '-' }}.
                            Router: <strong>{{ $ticket->layananPelanggan?->router?->nama_router ?? '-' }}</strong>,
                            PPP: <strong>{{ $ticket->layananPelanggan?->ppp_username ?? '-' }}</strong>
                        </div>
                    @else
                        @can('aktivasiPemasangan', $ticket)
                            <flux:button
                                wire:click="openAktivasiModal"
                                variant="primary"
                                icon="bolt"
                                :disabled="! $ticket->siapDiaktivasi()"
                            >
                                Aktivasi Pemasangan
                            </flux:button>
                            @if (! $ticket->siapDiaktivasi())
                                <p class="text-xs text-zinc-500 mt-1">Belum bisa diaktivasi: Teknisi harus memilih ODP+Port dan mengunggah minimal 1 foto pemasangan dulu.</p>
                            @endif
                        @endcan
                    @endif
                </div>

                <!-- Teknisi Tahap 2: Bukti Akhir (setelah Aktivasi) -->
                @if ($ticket->pemasangan?->sudahDiaktivasi())
                    <div class="pt-4 border-t border-zinc-100 dark:border-zinc-700/60 space-y-3">
                        <h4 class="text-sm font-bold text-zinc-800 dark:text-zinc-200">Bukti Akhir Teknisi (Tahap 2)</h4>

                        @can('ubahStatusDivisi', [$ticket, \App\Enums\Ticket\DivisiTicket::Teknisi])
                            <form wire:submit="simpanFotoTahapDua" class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                <flux:field>
                                    <flux:label>Foto Speedtest</flux:label>
                                    <input type="file" wire:model="fotoSpeedtest" multiple accept="image/*" class="block w-full text-xs text-zinc-500" />
                                    <flux:error name="fotoSpeedtest.*" />
                                </flux:field>
                                <flux:field>
                                    <flux:label>Foto Tanda Tangan MOU</flux:label>
                                    <input type="file" wire:model="fotoMou" accept="image/*" class="block w-full text-xs text-zinc-500" />
                                    <flux:error name="fotoMou" />
                                </flux:field>
                                <flux:field>
                                    <flux:label>Foto Bersama Pelanggan & Teknisi</flux:label>
                                    <input type="file" wire:model="fotoBersama" multiple accept="image/*" class="block w-full text-xs text-zinc-500" />
                                    <flux:error name="fotoBersama.*" />
                                </flux:field>
                                <div class="sm:col-span-3">
                                    <flux:button type="submit" size="sm" variant="primary">Simpan Bukti</flux:button>
                                </div>
                            </form>
                        @endcan

                        <div class="grid grid-cols-3 gap-3 text-xs">
                            @foreach (['foto_speedtest' => 'Speedtest', 'foto_tanda_tangan_mou' => 'Tanda Tangan MOU', 'foto_bersama_pelanggan_teknisi' => 'Foto Bersama'] as $collection => $label)
                                <div>
                                    <span class="text-zinc-500 block mb-1">{{ $label }}</span>
                                    <div class="flex flex-wrap gap-1">
                                        @forelse ($ticket->getMedia($collection) as $media)
                                            <a href="{{ $media->getUrl() }}" target="_blank">
                                                <img src="{{ $media->getUrl() }}" class="h-14 w-14 object-cover rounded border border-zinc-200 dark:border-zinc-700" />
                                            </a>
                                        @empty
                                            <span class="text-zinc-400 italic">Belum ada</span>
                                        @endforelse
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        @endif

        <!-- 2. Deskripsi Tiket (Full Width) -->
        <div class="bg-white dark:bg-zinc-800 p-6 rounded-xl border border-zinc-200 dark:border-zinc-700 shadow-sm space-y-3">
            <h3 class="font-bold text-base text-zinc-900 dark:text-white flex items-center gap-2">
                <flux:icon name="document-text" class="size-5 text-zinc-600 dark:text-zinc-400" />
                Deskripsi Tiket
            </h3>
            <div class="p-4 bg-zinc-50 dark:bg-zinc-900/50 rounded-lg text-sm text-zinc-800 dark:text-zinc-200 leading-relaxed whitespace-pre-wrap">
                {{ $ticket->deskripsi }}
            </div>

            @if($ticket->getFirstMedia('foto_kendala'))
                <div class="pt-2">
                    <span class="text-xs font-semibold text-zinc-600 dark:text-zinc-400 block mb-2">Lampiran Foto Kendala:</span>
                    <a href="{{ $ticket->getFirstMediaUrl('foto_kendala') }}" target="_blank" class="inline-block group">
                        <img src="{{ $ticket->getFirstMediaUrl('foto_kendala') }}" alt="Foto Kendala" class="h-32 w-auto object-cover rounded-lg border border-zinc-200 dark:border-zinc-700 shadow-sm group-hover:opacity-90 transition-opacity" />
                    </a>
                </div>
            @endif
        </div>

        <!-- 3. Grid 3 Kartu Informasi: Pelanggan, Layanan, Tim/Sales -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <!-- Info Pelanggan -->
            <div class="bg-white dark:bg-zinc-800 p-6 rounded-xl border border-zinc-200 dark:border-zinc-700 shadow-sm space-y-4">
                <div class="flex items-center justify-between border-b border-zinc-100 dark:border-zinc-700/60 pb-3">
                    <h3 class="font-bold text-sm text-zinc-900 dark:text-white flex items-center gap-2">
                        <flux:icon name="user" class="size-4 text-blue-600" />
                        Informasi Pelanggan
                    </h3>
                    @if($ticket->pelanggan)
                        <a href="{{ route('pelanggan.show', $ticket->pelanggan) }}" wire:navigate class="text-xs text-blue-600 dark:text-blue-400 hover:underline">
                            Profil →
                        </a>
                    @endif
                </div>

                @if($ticket->pelanggan)
                    <div class="space-y-2.5 text-xs divide-y divide-zinc-100 dark:divide-zinc-700/50">
                        <div>
                            <span class="text-zinc-500">Identitas Pelanggan:</span>
                            <div class="font-semibold text-zinc-900 dark:text-white text-sm">{{ $ticket->pelanggan->identitasLengkap() }}</div>
                        </div>
                        <div class="pt-2">
                            <span class="text-zinc-500">Kontak:</span>
                            <div class="font-medium text-zinc-900 dark:text-white">{{ $ticket->pelanggan->no_hp }}</div>
                            <div class="text-zinc-500 truncate">{{ $ticket->pelanggan->email ?? '-' }}</div>
                        </div>
                        <div class="pt-2 flex justify-between items-center">
                            <span class="text-zinc-500">Tipe / NIK:</span>
                            <span class="font-medium text-zinc-800 dark:text-zinc-200">
                                {{ $ticket->pelanggan->tipe_pelanggan?->label() ?? 'Rumah' }} • {{ $ticket->pelanggan->nik ?? '-' }}
                            </span>
                        </div>
                    </div>
                @else
                    <div class="text-xs text-zinc-400 italic">Data pelanggan tidak tersedia.</div>
                @endif
            </div>

            <!-- Info Layanan -->
            <div class="bg-white dark:bg-zinc-800 p-6 rounded-xl border border-zinc-200 dark:border-zinc-700 shadow-sm space-y-4">
                <h3 class="font-bold text-sm text-zinc-900 dark:text-white flex items-center gap-2 border-b border-zinc-100 dark:border-zinc-700/60 pb-3">
                    <flux:icon name="signal" class="size-4 text-emerald-600" />
                    Informasi Layanan
                </h3>

                @if($ticket->layananPelanggan)
                    <div class="space-y-2.5 text-xs divide-y divide-zinc-100 dark:divide-zinc-700/50">
                        <div>
                            <span class="text-zinc-500">PPP Username / Site ID:</span>
                            <div class="font-semibold text-zinc-900 dark:text-white font-mono text-sm">{{ $ticket->layananPelanggan->ppp_username }}</div>
                            <div class="text-zinc-500 font-mono">Site ID: {{ $ticket->layananPelanggan->site_id }}</div>
                        </div>
                        <div class="pt-2">
                            <span class="text-zinc-500">Paket & Bandwidth:</span>
                            <div class="font-medium text-zinc-900 dark:text-white">{{ $ticket->layananPelanggan->paketLayanan?->nama_paket ?? '-' }}</div>
                            <div class="text-zinc-500">{{ $ticket->layananPelanggan->paketLayanan?->profilBandwidth?->nama_bandwidth ?? '-' }}</div>
                        </div>
                        <div class="pt-2 flex justify-between items-center">
                            <span class="text-zinc-500">Status Layanan:</span>
                            <flux:badge size="xs" :color="$ticket->layananPelanggan->statusBadgeColor()">
                                {{ $ticket->layananPelanggan->statusBadgeLabel() }}
                            </flux:badge>
                        </div>
                    </div>
                @else
                    <div class="text-xs text-zinc-400 italic py-4">Layanan belum terdaftar (Pemasangan Prospek Baru).</div>
                @endif
            </div>

            <!-- Sales / Tim yang Menangani -->
            <div class="bg-white dark:bg-zinc-800 p-6 rounded-xl border border-zinc-200 dark:border-zinc-700 shadow-sm space-y-4">
                <h3 class="font-bold text-sm text-zinc-900 dark:text-white flex items-center gap-2 border-b border-zinc-100 dark:border-zinc-700/60 pb-3">
                    <flux:icon name="identification" class="size-4 text-amber-600" />
                    Tim yang Menangani
                </h3>

                <div class="space-y-3 text-xs">
                    <div>
                        <span class="text-zinc-500 block">Pembuat Tiket:</span>
                        <span class="font-semibold text-zinc-900 dark:text-white text-sm">
                            {{ $ticket->dibuatOleh?->name ?? 'Sistem Otomatis' }}
                        </span>
                        @if($ticket->dibuatOleh)
                            <div class="text-zinc-500">{{ $ticket->dibuatOleh->email }}</div>
                        @endif
                    </div>

                    <div class="pt-2 border-t border-zinc-100 dark:border-zinc-700/50">
                        <span class="text-zinc-500 block">Sales Pendaftar Pelanggan:</span>
                        <span class="font-semibold text-zinc-900 dark:text-white text-sm">
                            {{ $ticket->pelanggan?->dibuatOleh?->name ?? '-' }}
                        </span>
                    </div>

                    <div class="pt-2 border-t border-zinc-100 dark:border-zinc-700/50">
                        <span class="text-zinc-500 block mb-1">PIC Lapangan (Teknisi):</span>
                        <div class="flex items-center gap-2">
                            @if ($ticket->pic)
                                <flux:avatar size="xs" :src="$ticket->pic->fotoProfilUrl()" :initials="$ticket->pic->initials()" />
                            @endif
                            <span class="font-semibold text-zinc-900 dark:text-white">
                                {{ $ticket->pic?->name ?? 'Belum Ditugaskan' }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 4. Alamat Layanan & Peta Lokasi Lapangan (Full Width dengan Leaflet Map) -->
        <div class="bg-white dark:bg-zinc-800 p-6 rounded-xl border border-zinc-200 dark:border-zinc-700 shadow-sm space-y-5">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-zinc-100 dark:border-zinc-700/60 pb-4">
                <div class="flex items-center gap-2.5">
                    <div class="p-2 rounded-lg bg-rose-50 dark:bg-rose-950/60 text-rose-600 dark:text-rose-400">
                        <flux:icon name="map-pin" class="size-5" />
                    </div>
                    <div>
                        <h3 class="font-bold text-base text-zinc-900 dark:text-white">
                            Alamat Layanan & Peta Lokasi Lapangan
                        </h3>
                        <p class="text-xs text-zinc-500 dark:text-zinc-400">
                            Titik koordinat GPS dan visualisasi peta interaktif Leaflet untuk tim lapangan / teknisi.
                        </p>
                    </div>
                </div>

                @if($ticket->pelanggan && $ticket->pelanggan->latitude && $ticket->pelanggan->longitude)
                    <div class="flex items-center gap-2">
                        <flux:button href="https://www.google.com/maps?q={{ $ticket->pelanggan->latitude }},{{ $ticket->pelanggan->longitude }}" target="_blank" variant="subtle" icon="arrow-top-right-on-square" size="xs">
                            Buka di Google Maps
                        </flux:button>
                    </div>
                @endif
            </div>

            @if($ticket->pelanggan)
                <!-- Grid Info Alamat & Perumahan -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-xs">
                    <div class="md:col-span-2 p-3.5 bg-zinc-50 dark:bg-zinc-900/50 rounded-lg space-y-1">
                        <span class="text-zinc-500 block font-medium">Alamat Lengkap:</span>
                        <p class="font-semibold text-zinc-900 dark:text-white leading-relaxed text-sm">
                            {{ $ticket->pelanggan->alamat_lengkap }}
                        </p>
                    </div>

                    <div class="p-3.5 bg-zinc-50 dark:bg-zinc-900/50 rounded-lg space-y-1">
                        <span class="text-zinc-500 block font-medium">Wilayah / Perumahan:</span>
                        @if($ticket->pelanggan->perumahan)
                            <div class="font-semibold text-zinc-900 dark:text-white text-xs">
                                {{ $ticket->pelanggan->perumahan->nama_perumahan }}
                            </div>
                            <div class="text-zinc-600 dark:text-zinc-400 text-[11px] leading-snug">
                                RT {{ $ticket->pelanggan->rt ?? '0' }}/RW {{ $ticket->pelanggan->rw ?? '0' }}, No. {{ $ticket->pelanggan->no_rumah ?? '-' }}<br>
                                Kel. {{ $ticket->pelanggan->perumahan->kelurahan?->nama_kelurahan }}, Kec. {{ $ticket->pelanggan->perumahan->kelurahan?->kecamatan?->nama_kecamatan }}, {{ $ticket->pelanggan->perumahan->kelurahan?->kecamatan?->kota?->nama_kota }}
                            </div>
                        @else
                            <div class="text-zinc-400 italic">Non-Perumahan</div>
                        @endif
                    </div>
                </div>

                <!-- Peta Leaflet Map Berukuran Besar -->
                @if (is_numeric($ticket->pelanggan->latitude) && is_numeric($ticket->pelanggan->longitude))
                    <div class="space-y-3">
                        <x-map-view
                            :lat="$ticket->pelanggan->latitude"
                            :lng="$ticket->pelanggan->longitude"
                            :popup-title="$ticket->pelanggan->namaLengkap()"
                            :popup-subtitle="$ticket->pelanggan->alamat_lengkap"
                            height="360px"
                        />

                        <!-- Coordinate Details Bar -->
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 rounded-lg bg-zinc-50 dark:bg-zinc-900/50 p-3 text-xs border border-zinc-100 dark:border-zinc-700/50">
                            <div>
                                <span class="text-[11px] text-zinc-400 block">Latitude</span>
                                <div class="font-mono font-semibold text-zinc-800 dark:text-zinc-200">
                                    {{ number_format((float) $ticket->pelanggan->latitude, 7, '.', '') }}
                                </div>
                            </div>
                            <div>
                                <span class="text-[11px] text-zinc-400 block">Longitude</span>
                                <div class="font-mono font-semibold text-zinc-800 dark:text-zinc-200">
                                    {{ number_format((float) $ticket->pelanggan->longitude, 7, '.', '') }}
                                </div>
                            </div>
                            <div>
                                <span class="text-[11px] text-zinc-400 block">Format Koordinat (Lat, Lng)</span>
                                <div class="font-mono text-zinc-700 dark:text-zinc-300">
                                    {{ $ticket->pelanggan->latitude }}, {{ $ticket->pelanggan->longitude }}
                                </div>
                            </div>
                        </div>
                    </div>
                @else
                    <div class="flex flex-col items-center justify-center py-10 text-center rounded-lg border border-dashed border-zinc-200 dark:border-zinc-700 bg-zinc-50/50 dark:bg-zinc-900/30">
                        <div class="flex size-12 items-center justify-center rounded-full bg-zinc-100 dark:bg-zinc-800">
                            <flux:icon name="map-pin" class="size-6 text-zinc-400 dark:text-zinc-500" />
                        </div>
                        <span class="mt-3 text-sm font-medium text-zinc-700 dark:text-zinc-300">Koordinat GPS Belum Ditentukan</span>
                        <span class="mt-1 text-xs text-zinc-400 dark:text-zinc-500 max-w-sm">
                            Titik koordinat pelanggan ini belum diatur. Atur koordinat pada data master pelanggan untuk menampilkan peta Leaflet.
                        </span>
                        @can('update', $ticket->pelanggan)
                            <div class="mt-3">
                                <flux:button href="{{ route('pelanggan.edit', $ticket->pelanggan) }}" wire:navigate size="xs" variant="primary" icon="pencil-square">
                                    Atur Titik Koordinat Pelanggan
                                </flux:button>
                            </div>
                        @endcan
                    </div>
                @endif
            @else
                <div class="text-xs text-zinc-400 italic py-4">Data pelanggan tidak tersedia.</div>
            @endif
        </div>

        <!-- 4. Histori Proses Tiket (Diletakkan di Paling Bawah - Full Width Horizontal & Vertical) -->
        <div class="bg-white dark:bg-zinc-800 p-6 rounded-xl border border-zinc-200 dark:border-zinc-700 shadow-sm space-y-6">
            <div class="flex items-center justify-between border-b border-zinc-100 dark:border-zinc-700/60 pb-4">
                <div class="flex items-center gap-3">
                    <div class="p-2 rounded-lg bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400">
                        <flux:icon name="clock" class="size-5" />
                    </div>
                    <div>
                        <h3 class="font-bold text-lg text-zinc-900 dark:text-white">
                            Histori Proses Tiket
                        </h3>
                        <p class="text-xs text-zinc-500 dark:text-zinc-400">
                            Log lengkap perjalanan, penugasan, perubahan status, dan catatan operasional lapangan.
                        </p>
                    </div>
                </div>
                <flux:badge size="sm" color="zinc">{{ $ticket->histori->count() }} Riwayat Tercatat</flux:badge>
            </div>

            <!-- Timeline Vertikal Full-Width -->
            <div class="relative pl-8 sm:pl-10 space-y-8 before:absolute before:left-3.5 sm:before:left-4.5 before:top-3 before:bottom-3 before:w-0.5 before:bg-zinc-200 dark:before:bg-zinc-700">
                @forelse($ticket->histori as $hist)
                    <div class="relative group">
                        <!-- Bullet Point Node -->
                        <div class="absolute -left-8 sm:-left-10 top-1 size-7 rounded-full border-4 border-white dark:border-zinc-800 bg-indigo-600 dark:bg-indigo-500 shadow flex items-center justify-center">
                            <div class="size-1.5 rounded-full bg-white"></div>
                        </div>

                        <div class="bg-zinc-50/80 dark:bg-zinc-900/60 p-4 sm:p-5 rounded-xl border border-zinc-100 dark:border-zinc-700/60 hover:border-zinc-300 dark:hover:border-zinc-600 transition-colors space-y-3">
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="font-bold text-sm text-zinc-900 dark:text-white">
                                        {{ $hist->olehPengguna?->name ?? 'Sistem' }}
                                    </span>
                                    @if($hist->olehPengguna?->roles->first())
                                        <flux:badge size="xs" color="zinc">
                                            {{ $hist->olehPengguna->roles->first()->name }}
                                        </flux:badge>
                                    @endif
                                </div>

                                <div class="flex items-center gap-2 text-xs text-zinc-500 dark:text-zinc-400 font-medium">
                                    <flux:icon name="calendar" class="size-3.5" />
                                    <span>{{ $hist->created_at->format('d M Y, H:i:s') }}</span>
                                    <span class="text-zinc-400">({{ $hist->created_at->diffForHumans() }})</span>
                                </div>
                            </div>

                            <!-- Transisi Status Badge -->
                            <div class="flex items-center gap-2 flex-wrap text-xs">
                                <span class="text-zinc-500 font-medium">Status:</span>
                                @if($hist->status_lama && $hist->status_lama !== $hist->status_baru)
                                    <flux:badge size="xs" color="zinc">{{ $hist->status_lama->label() }}</flux:badge>
                                    <flux:icon name="arrow-right" class="size-3 text-zinc-400" />
                                @endif
                                <flux:badge size="xs" :color="$hist->status_baru->color()">
                                    {{ $hist->status_baru->label() }}
                                </flux:badge>
                            </div>

                            <!-- Catatan Histori -->
                            @if($hist->catatan)
                                <div class="p-3 bg-white dark:bg-zinc-800 rounded-lg text-xs sm:text-sm text-zinc-800 dark:text-zinc-200 leading-relaxed border border-zinc-200/70 dark:border-zinc-700 whitespace-pre-wrap">
                                    {{ $hist->catatan }}
                                </div>
                            @endif

                            <!-- Foto Bukti Pengerjaan -->
                            @if($hist->getFirstMedia('foto_pengerjaan'))
                                <div class="pt-1">
                                    <span class="text-xs font-medium text-zinc-500 block mb-1">Bukti Foto / Pengerjaan:</span>
                                    <a href="{{ $hist->getFirstMediaUrl('foto_pengerjaan') }}" target="_blank" class="inline-block group">
                                        <img src="{{ $hist->getFirstMediaUrl('foto_pengerjaan') }}" alt="Bukti Pengerjaan" class="h-28 w-auto object-cover rounded-lg border border-zinc-200 dark:border-zinc-700 group-hover:opacity-90 shadow-sm" />
                                    </a>
                                </div>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="text-sm text-zinc-400 italic py-4">Belum ada catatan histori proses.</div>
                @endforelse
            </div>
        </div>
    </div>

    <!-- Modal Ubah Status -->
    <flux:modal :open="$showUbahStatusModal" wire:model.self="showUbahStatusModal" class="max-w-md">
        <form wire:submit="prosesUbahStatus" class="p-6 space-y-4">
            <div class="flex items-center gap-3 text-blue-600">
                <flux:icon name="arrow-path" class="size-6" />
                <h3 class="text-lg font-bold text-zinc-900 dark:text-white">Ubah Status Tiket</h3>
            </div>

            <p class="text-xs text-zinc-500 dark:text-zinc-400">
                Pilih status tujuan baru sesuai alur kerja. Transisi akan dicatat otomatis di histori tiket.
            </p>

            <div>
                <flux:select wire:model="statusBaru" label="Status Tujuan" required>
                    @foreach($transisiValid as $target)
                        <flux:select.option value="{{ $target->value }}">
                            {{ $target->label() }}
                        </flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            <div>
                <flux:textarea
                    wire:model="catatanStatus"
                    label="Catatan Tindak Lanjut {{ $statusBaru === 'batal' ? '(Wajib Diisi)' : '(Opsional)' }}"
                    placeholder="Contoh: Pekerjaan lapangan telah selesai diverifikasi, atau alasan pembatalan..."
                    rows="3"
                />
            </div>

            <div class="flex justify-end gap-2 pt-2">
                <flux:button wire:click="$set('showUbahStatusModal', false)" variant="subtle">Batal</flux:button>
                <flux:button type="submit" variant="primary">Simpan Status</flux:button>
            </div>
        </form>
    </flux:modal>

    <!-- Modal Assign PIC -->
    <flux:modal :open="$showAssignPicModal" wire:model.self="showAssignPicModal" class="max-w-md">
        <form wire:submit="prosesAssignPic" class="p-6 space-y-4">
            <div class="flex items-center gap-3 text-amber-600">
                <flux:icon name="user-plus" class="size-6" />
                <h3 class="text-lg font-bold text-zinc-900 dark:text-white">Tugaskan PIC Tiket</h3>
            </div>

            <p class="text-xs text-zinc-500 dark:text-zinc-400">
                Tugaskan staf teknisi atau NOC yang bertanggung jawab untuk menyelesaikan tiket ini.
            </p>

            <div>
                <flux:select wire:model="selectedPicId" label="Pilih Staf PIC">
                    <flux:select.option value="">-- Kosongkan PIC --</flux:select.option>
                    @foreach($staffList as $stf)
                        <flux:select.option value="{{ $stf->id }}">
                            {{ $stf->name }}
                        </flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            <div>
                <flux:textarea
                    wire:model="catatanAssign"
                    label="Catatan Penugasan (Opsional)"
                    placeholder="Instruksi khusus kepada teknisi..."
                    rows="2"
                />
            </div>

            <div class="flex justify-end gap-2 pt-2">
                <flux:button wire:click="$set('showAssignPicModal', false)" variant="subtle">Batal</flux:button>
                <flux:button type="submit" variant="primary">Tugaskan</flux:button>
            </div>
        </form>
    </flux:modal>

    <!-- Modal Aktivasi Pemasangan -->
    <flux:modal :open="$showAktivasiModal" wire:model.self="showAktivasiModal" class="max-w-md">
        <form wire:submit="prosesAktivasi" class="p-6 space-y-4">
            <div class="flex items-center gap-3 text-emerald-600">
                <flux:icon name="bolt" class="size-6" />
                <h3 class="text-lg font-bold text-zinc-900 dark:text-white">Aktivasi Pemasangan</h3>
            </div>

            <p class="text-xs text-zinc-500 dark:text-zinc-400">
                Pilih Router & IP Pool. Username PPP akan digenerate otomatis oleh sistem.
            </p>

            <flux:field>
                <flux:label>Router Gateway</flux:label>
                <flux:select wire:model.live="aktivasiRouterId" placeholder="Pilih router...">
                    <flux:select.option value="">-- Pilih Router --</flux:select.option>
                    @foreach ($onlineRouters as $r)
                        <flux:select.option value="{{ $r->id }}">{{ $r->nama_router }} ({{ $r->ip_address }})</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="aktivasiRouterId" />
            </flux:field>

            <flux:field>
                <flux:label>IP Pool</flux:label>
                <flux:select wire:model="aktivasiIpPoolId" placeholder="Pilih IP Pool..." :disabled="! $aktivasiRouterId">
                    <flux:select.option value="">-- Pilih IP Pool --</flux:select.option>
                    @foreach ($aktivasiIpPools as $pool)
                        <flux:select.option value="{{ $pool->id }}">{{ $pool->nama_pool }} ({{ $pool->ip_network }}/{{ $pool->cidr }})</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="aktivasiIpPoolId" />
            </flux:field>

            <div class="flex justify-end gap-2 pt-2">
                <flux:button wire:click="$set('showAktivasiModal', false)" variant="subtle">Batal</flux:button>
                <flux:button type="submit" variant="primary">Aktivasi</flux:button>
            </div>
        </form>
    </flux:modal>

    <!-- Modal Tambah Catatan Lapangan -->
    <flux:modal :open="$showCatatanModal" wire:model.self="showCatatanModal" class="max-w-md">
        <form wire:submit="simpanCatatan" class="p-6 space-y-4">
            <div class="flex items-center gap-3 text-indigo-600">
                <flux:icon name="chat-bubble-left-ellipsis" class="size-6" />
                <h3 class="text-lg font-bold text-zinc-900 dark:text-white">Tambah Catatan Lapangan</h3>
            </div>

            <p class="text-xs text-zinc-500 dark:text-zinc-400">
                Tambahkan progres, kendala lapangan, atau catatan koordinasi tanpa mengubah status tiket saat ini.
            </p>

            <div>
                <flux:textarea
                    wire:model="catatanProses"
                    label="Catatan / Update Progress"
                    placeholder="Contoh: Teknisi sedang dalam perjalanan ke lokasi pelanggan..."
                    rows="4"
                    required
                />
                @error('catatanProses') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
            </div>

            <div>
                <flux:field>
                    <flux:label>Foto Bukti Pengerjaan (Opsional)</flux:label>
                    <input
                        type="file"
                        wire:model="fotoPengerjaan"
                        accept="image/png, image/jpeg, image/webp"
                        class="block w-full text-xs text-zinc-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 dark:file:bg-zinc-700 dark:file:text-zinc-200"
                    />
                    <flux:description>Foto hasil instalasi, redaman OPM, atau router terpasang.</flux:description>
                    <flux:error name="fotoPengerjaan" />
                </flux:field>

                @if ($fotoPengerjaan)
                    <div class="mt-2 flex items-center gap-3 p-2 bg-zinc-50 dark:bg-zinc-900 rounded-lg border border-zinc-200 dark:border-zinc-700 text-xs">
                        <span class="text-emerald-600 font-medium">✓ Foto terpilih:</span>
                        <span class="text-zinc-600 dark:text-zinc-300 truncate">{{ $fotoPengerjaan->getClientOriginalName() }}</span>
                    </div>
                @endif
            </div>

            <div>
                <flux:checkbox
                    wire:model="catatanIsInternal"
                    label="Catatan Internal"
                    description="Jika dicentang, catatan ini hanya dapat dilihat oleh staf internal."
                />
            </div>

            <div class="flex justify-end gap-2 pt-2">
                <flux:button wire:click="$set('showCatatanModal', false)" variant="subtle">Batal</flux:button>
                <flux:button type="submit" variant="primary">Simpan Catatan</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
