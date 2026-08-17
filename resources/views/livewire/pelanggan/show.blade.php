<div class="space-y-6">
    {{-- Header --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-3">
            <flux:button :href="route('pelanggan.index')" wire:navigate variant="ghost" size="sm" icon="arrow-left" />
            <div>
                <div class="flex items-center gap-2.5">
                    <flux:heading size="xl">{{ $pelanggan->namaLengkap() }}</flux:heading>
                    <flux:badge size="sm" color="zinc" class="font-mono">{{ $pelanggan->no_reg }}</flux:badge>
                    <flux:badge size="sm" :color="$pelanggan->status->color()">
                        {{ $pelanggan->status->label() }}
                    </flux:badge>
                </div>
                <flux:subheading>Didaftarkan pada {{ $pelanggan->created_at?->translatedFormat('d F Y, H:i') ?? '—' }} oleh {{ $pelanggan->pembuat?->name ?? 'Sistem' }}</flux:subheading>
            </div>
        </div>

        @can('update', $pelanggan)
            <div class="flex items-center gap-2">
                <flux:button :href="route('pelanggan.edit', $pelanggan)" wire:navigate variant="primary" icon="pencil-square">
                    Edit Pelanggan
                </flux:button>
            </div>
        @endcan
    </div>

    {{-- Tabs Navigation --}}
    <div class="border-b border-zinc-200 dark:border-zinc-700">
        <nav class="-mb-px flex gap-6" aria-label="Tabs">
            <button
                type="button"
                wire:click="setTab('overview')"
                class="flex items-center gap-2 border-b-2 py-3 text-sm font-medium transition-colors {{ $activeTab === 'overview' ? 'border-primary-600 text-primary-600 dark:border-primary-400 dark:text-primary-400' : 'border-transparent text-zinc-500 hover:border-zinc-300 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200' }}"
            >
                <flux:icon name="user" class="size-4" />
                Informasi & Lokasi
            </button>

            <button
                type="button"
                wire:click="setTab('subscriptions')"
                class="flex items-center gap-2 border-b-2 py-3 text-sm font-medium transition-colors {{ $activeTab === 'subscriptions' ? 'border-primary-600 text-primary-600 dark:border-primary-400 dark:text-primary-400' : 'border-transparent text-zinc-500 hover:border-zinc-300 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200' }}"
            >
                <flux:icon name="rss" class="size-4" />
                Layanan Internet
                @if ($pelanggan->layanans->isNotEmpty())
                    <span class="rounded-full bg-zinc-200 px-1.5 py-0.2 text-xs font-semibold text-zinc-700 dark:bg-zinc-700 dark:text-zinc-300">{{ $pelanggan->layanans->count() }}</span>
                @endif
            </button>

            <button
                type="button"
                wire:click="setTab('audit')"
                class="flex items-center gap-2 border-b-2 py-3 text-sm font-medium transition-colors {{ $activeTab === 'audit' ? 'border-primary-600 text-primary-600 dark:border-primary-400 dark:text-primary-400' : 'border-transparent text-zinc-500 hover:border-zinc-300 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200' }}"
            >
                <flux:icon name="clock" class="size-4" />
                Riwayat Aktivitas
                @if ($activityLogs->isNotEmpty())
                    <span class="rounded-full bg-zinc-200 px-1.5 py-0.2 text-xs font-semibold text-zinc-700 dark:bg-zinc-700 dark:text-zinc-300">{{ $activityLogs->count() }}</span>
                @endif
            </button>
        </nav>
    </div>

    {{-- Tab 1: Informasi & Lokasi --}}
    @if ($activeTab === 'overview')
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            {{-- Kolom Kiri: Detail Kontak & Alamat --}}
            <div class="space-y-6 lg:col-span-2">
                {{-- Card Kontak --}}
                <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                    <flux:heading size="base" class="mb-4">Kontak & Identitas</flux:heading>

                    <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <dt class="text-xs font-medium text-zinc-400">Nama Lengkap</dt>
                            <dd class="mt-1 text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $pelanggan->namaLengkap() }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium text-zinc-400">Nomor WhatsApp / HP</dt>
                            <dd class="mt-1 flex items-center gap-2 text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                {{ $pelanggan->no_hp }}
                                <a
                                    href="https://wa.me/{{ $pelanggan->no_hp }}"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="inline-flex items-center gap-1 rounded bg-emerald-50 px-1.5 py-0.5 text-xs text-emerald-700 hover:bg-emerald-100 dark:bg-emerald-950 dark:text-emerald-300"
                                    title="Hubungi via WhatsApp"
                                >
                                    <flux:icon name="chat-bubble-left-right" class="size-3" />
                                    Chat WA
                                </a>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium text-zinc-400">Alamat Email</dt>
                            <dd class="mt-1 text-sm text-zinc-700 dark:text-zinc-300">{{ $pelanggan->email ?: '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium text-zinc-400">Telepon Rumah</dt>
                            <dd class="mt-1 text-sm text-zinc-700 dark:text-zinc-300">{{ $pelanggan->telepon_rumah ?: '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium text-zinc-400">Tipe Pelanggan</dt>
                            <dd class="mt-1 text-sm text-zinc-700 dark:text-zinc-300">{{ $pelanggan->tipe_pelanggan->label() }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium text-zinc-400">Kode Pembayaran (VA)</dt>
                            <dd class="mt-1 font-mono text-sm font-semibold text-zinc-800 dark:text-zinc-200">{{ $pelanggan->kode_pembayaran }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium text-zinc-400">Status Akun</dt>
                            <dd class="mt-1">
                                <flux:badge size="sm" :color="$pelanggan->status->color()">
                                    {{ $pelanggan->status->label() }}
                                </flux:badge>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium text-zinc-400">Perumahan / Area</dt>
                            <dd class="mt-1 text-sm text-zinc-700 dark:text-zinc-300">{{ $pelanggan->perumahan?->nama_perumahan ?? '—' }}</dd>
                        </div>
                    </dl>
                </div>

                {{-- Card Alamat --}}
                <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                    <flux:heading size="base" class="mb-4">Alamat Pemasangan</flux:heading>

                    <dl class="space-y-4">
                        <div>
                            <dt class="text-xs font-medium text-zinc-400">Alamat Lengkap</dt>
                            <dd class="mt-1 text-sm text-zinc-800 dark:text-zinc-200">{{ $pelanggan->alamat_lengkap }}</dd>
                        </div>
                        <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                            <div>
                                <dt class="text-xs font-medium text-zinc-400">RT / RW</dt>
                                <dd class="mt-1 text-sm text-zinc-700 dark:text-zinc-300">{{ $pelanggan->rt ?? '-' }} / {{ $pelanggan->rw ?? '-' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-medium text-zinc-400">No. Rumah</dt>
                                <dd class="mt-1 text-sm text-zinc-700 dark:text-zinc-300">{{ $pelanggan->no_rumah ?? '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-medium text-zinc-400">Kode Pos</dt>
                                <dd class="mt-1 text-sm text-zinc-700 dark:text-zinc-300">{{ $pelanggan->kode_pos ?? '—' }}</dd>
                            </div>
                        </div>
                    </dl>
                </div>
            </div>

            {{-- Kolom Kanan: Peta Titik Koordinat & Informasi Sistem --}}
            <div class="space-y-6">
                {{-- Card Peta Koordinat --}}
                <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                    <flux:heading size="base" class="mb-3">Titik Koordinat Lokasi</flux:heading>

                    @if ($pelanggan->latitude && $pelanggan->longitude)
                        <div class="space-y-3">
                            <div class="flex items-center justify-between text-xs text-zinc-500">
                                <span>Lat: <strong class="font-mono text-zinc-700 dark:text-zinc-300">{{ $pelanggan->latitude }}</strong></span>
                                <span>Lng: <strong class="font-mono text-zinc-700 dark:text-zinc-300">{{ $pelanggan->longitude }}</strong></span>
                            </div>
                            <div>
                                <a
                                    href="https://www.google.com/maps?q={{ $pelanggan->latitude }},{{ $pelanggan->longitude }}"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="inline-flex w-full items-center justify-center gap-1.5 rounded-lg border border-zinc-200 px-3 py-1.5 text-xs font-medium text-zinc-700 hover:bg-zinc-50 dark:border-zinc-700 dark:text-zinc-300 dark:hover:bg-zinc-800"
                                >
                                    <flux:icon name="arrow-top-right-on-square" class="size-3.5" />
                                    Buka di Google Maps
                                </a>
                            </div>
                        </div>
                    @else
                        <div class="flex flex-col items-center justify-center py-8 text-center text-zinc-400">
                            <flux:icon name="map-pin" class="size-8 stroke-1 text-zinc-300 dark:text-zinc-600" />
                            <span class="mt-2 text-xs">Koordinat belum ditentukan untuk pelanggan ini.</span>
                        </div>
                    @endif
                </div>

                {{-- Card Info Internal --}}
                <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                    <flux:heading size="base" class="mb-3">Informasi Internal</flux:heading>

                    <dl class="space-y-3 text-xs">
                        <div class="flex justify-between">
                            <dt class="text-zinc-400">Didaftarkan Oleh</dt>
                            <dd class="font-medium text-zinc-800 dark:text-zinc-200">{{ $pelanggan->pembuat?->name ?? '—' }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-zinc-400">Tanggal Dibuat</dt>
                            <dd class="text-zinc-700 dark:text-zinc-300">{{ $pelanggan->created_at?->format('d/m/Y H:i') ?? '—' }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-zinc-400">Terakhir Diperbarui</dt>
                            <dd class="text-zinc-700 dark:text-zinc-300">{{ $pelanggan->updated_at?->format('d/m/Y H:i') ?? '—' }}</dd>
                        </div>
                    </dl>
                </div>
            </div>
        </div>
    @endif

    {{-- Tab 2: Layanan Internet --}}
    @if ($activeTab === 'subscriptions')
        <div class="space-y-4">
            <div class="flex justify-between items-center">
                <flux:heading size="base">Daftar Layanan Internet</flux:heading>
                @can('create', App\Models\LayananPelanggan::class)
                    <flux:button :href="route('layanan-pelanggan.create')" wire:navigate variant="primary" size="sm" icon="plus">
                        Tambah Layanan
                    </flux:button>
                @endcan
            </div>

            @if ($pelanggan->layanans->isEmpty())
                <div class="rounded-xl border border-dashed border-zinc-300 bg-zinc-50 p-12 text-center dark:border-zinc-700 dark:bg-zinc-900/50">
                    <flux:icon name="rss" class="mx-auto size-8 text-zinc-400" />
                    <flux:heading size="base" class="mt-2">Belum ada layanan aktif</flux:heading>
                    <flux:subheading class="mt-1">Pelanggan ini belum memiliki langganan paket internet.</flux:subheading>
                </div>
            @else
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    @foreach ($pelanggan->layanans as $layanan)
                        <div class="rounded-xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900 space-y-3">
                            <div class="flex justify-between items-start">
                                <div>
                                    <div class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $layanan->paketLayanan->nama_paket }}</div>
                                    <div class="text-xs font-mono text-zinc-500">{{ $layanan->site_id }}</div>
                                </div>
                                <flux:badge size="sm" :color="$layanan->status->color()">
                                    {{ $layanan->status->label() }}
                                </flux:badge>
                            </div>
                            <dl class="grid grid-cols-2 gap-2 text-xs">
                                <div>
                                    <dt class="text-zinc-400">Router</dt>
                                    <dd class="font-medium text-zinc-700 dark:text-zinc-300">{{ $layanan->router->nama_router }}</dd>
                                </div>
                                <div>
                                    <dt class="text-zinc-400">PPP Username</dt>
                                    <dd class="font-mono text-zinc-700 dark:text-zinc-300">{{ $layanan->ppp_username }}</dd>
                                </div>
                                <div>
                                    <dt class="text-zinc-400">Mulai</dt>
                                    <dd class="text-zinc-700 dark:text-zinc-300">{{ $layanan->tanggal_mulai->format('d/m/Y') }}</dd>
                                </div>
                                <div>
                                    <dt class="text-zinc-400">Jatuh Tempo</dt>
                                    <dd class="text-zinc-700 dark:text-zinc-300">{{ $layanan->tanggal_expired?->format('d/m/Y') ?? '—' }}</dd>
                                </div>
                            </dl>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    @endif

    {{-- Tab 3: Riwayat Aktivitas (Activity Log) --}}
    @if ($activeTab === 'audit')
        <div class="rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <div class="p-6 pb-2">
                <flux:heading size="base">Log Aktivitas Data Pelanggan</flux:heading>
                <flux:subheading>Catatan perubahan data dan riwayat administratif pelanggan.</flux:subheading>
            </div>

            <flux:table>
                <flux:table.columns>
                    <flux:table.column>Waktu</flux:table.column>
                    <flux:table.column>Pengguna</flux:table.column>
                    <flux:table.column>Deskripsi</flux:table.column>
                    <flux:table.column>Detail</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($activityLogs as $log)
                        <flux:table.row :key="$log->id">
                            <flux:table.cell class="text-xs text-zinc-500">
                                {{ $log->created_at->translatedFormat('d M Y, H:i') }}
                            </flux:table.cell>
                            <flux:table.cell class="font-medium text-xs">
                                {{ $log->causer?->name ?? 'Sistem' }}
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:badge size="sm" color="zinc">
                                    {{ $log->description }}
                                </flux:badge>
                            </flux:table.cell>
                            <flux:table.cell class="text-xs text-zinc-600 dark:text-zinc-300">
                                @if ($log->properties->isNotEmpty())
                                    <span class="font-mono text-[11px]">{{ $log->properties->toJson() }}</span>
                                @else
                                    —
                                @endif
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="4" class="py-8 text-center text-zinc-400">
                                Belum ada riwayat aktivitas tercatat untuk pelanggan ini.
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </div>
    @endif
</div>
