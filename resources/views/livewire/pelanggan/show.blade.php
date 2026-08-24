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
                <flux:subheading>Didaftarkan pada {{ $pelanggan->created_at?->translatedFormat('d F Y, H:i') ?? '—' }}
                    oleh {{ $pelanggan->pembuat?->name ?? 'Sistem' }}</flux:subheading>
            </div>
        </div>

        <div class="flex items-center gap-2">
            @canImpersonate
                @if ($pelanggan->akunPelanggan && $pelanggan->akunPelanggan->canBeImpersonated())
                    <flux:button
                        :href="route('impersonate', ['id' => $pelanggan->akunPelanggan->id, 'guardName' => 'pelanggan'])"
                        variant="subtle"
                        icon="arrow-right-end-on-rectangle"
                    >
                        Buka Portal (Login as)
                    </flux:button>
                @endif
            @endCanImpersonate

            @can('update', $pelanggan)
                <flux:button :href="route('pelanggan.edit', $pelanggan)" wire:navigate variant="primary"
                    icon="pencil-square">
                    Edit Pelanggan
                </flux:button>
            @endcan
        </div>
    </div>

    {{-- Tabs Navigation --}}
    <div class="border-b border-zinc-200 dark:border-zinc-700">
        <nav class="-mb-px flex gap-6" aria-label="Tabs">
            <button type="button" wire:click="setTab('overview')"
                class="flex items-center gap-2 border-b-2 py-3 text-sm font-medium transition-colors {{ $activeTab === 'overview' ? 'border-primary-600 text-primary-600 dark:border-primary-400 dark:text-primary-400' : 'border-transparent text-zinc-500 hover:border-zinc-300 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200' }}">
                <flux:icon name="user" class="size-4" />
                Informasi & Lokasi
            </button>

            <button type="button" wire:click="setTab('subscriptions')"
                class="flex items-center gap-2 border-b-2 py-3 text-sm font-medium transition-colors {{ $activeTab === 'subscriptions' ? 'border-primary-600 text-primary-600 dark:border-primary-400 dark:text-primary-400' : 'border-transparent text-zinc-500 hover:border-zinc-300 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200' }}">
                <flux:icon name="rss" class="size-4" />
                Layanan Internet
                @if ($pelanggan->layanans->isNotEmpty())
                    <span
                        class="rounded-full bg-zinc-200 px-1.5 py-0.2 text-xs font-semibold text-zinc-700 dark:bg-zinc-700 dark:text-zinc-300">{{ $pelanggan->layanans->count() }}</span>
                @endif
            </button>

            <button type="button" wire:click="setTab('audit')"
                class="flex items-center gap-2 border-b-2 py-3 text-sm font-medium transition-colors {{ $activeTab === 'audit' ? 'border-primary-600 text-primary-600 dark:border-primary-400 dark:text-primary-400' : 'border-transparent text-zinc-500 hover:border-zinc-300 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200' }}">
                <flux:icon name="clock" class="size-4" />
                Riwayat Aktivitas
                @if ($activityLogs->isNotEmpty())
                    <span
                        class="rounded-full bg-zinc-200 px-1.5 py-0.2 text-xs font-semibold text-zinc-700 dark:bg-zinc-700 dark:text-zinc-300">{{ $activityLogs->count() }}</span>
                @endif
            </button>
        </nav>
    </div>

    {{-- Tab 1: Informasi & Lokasi --}}
    @if ($activeTab === 'overview')
        <div class="space-y-6">
            {{-- Bagian Atas: Kontak, Alamat, & Informasi Internal --}}
            <div class="grid grid-cols-2 gap-6 lg:grid-cols-3">
                {{-- Kolom Kiri: Detail Kontak & Alamat --}}
                <div class="space-y-6 lg:col-span-2">
                    {{-- Card Kontak --}}
                    <div
                        class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                        <flux:heading size="base" class="mb-4">Kontak & Identitas</flux:heading>

                        <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <dt class="text-xs font-medium text-zinc-400">Nama Lengkap</dt>
                                <dd class="mt-1 text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                    {{ $pelanggan->namaLengkap() }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-medium text-zinc-400">Nomor WhatsApp / HP</dt>
                                <dd
                                    class="mt-1 flex items-center gap-2 text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                    {{ $pelanggan->no_hp }}
                                    <a href="https://wa.me/{{ $pelanggan->no_hp }}" target="_blank"
                                        rel="noopener noreferrer"
                                        class="inline-flex items-center gap-1 rounded bg-emerald-50 px-1.5 py-0.5 text-xs text-emerald-700 hover:bg-emerald-100 dark:bg-emerald-950 dark:text-emerald-300"
                                        title="Hubungi via WhatsApp">
                                        <flux:icon name="chat-bubble-left-right" class="size-3" />
                                        Chat WA
                                    </a>
                                </dd>
                            </div>
                            <div>
                                <dt class="text-xs font-medium text-zinc-400">Alamat Email</dt>
                                <dd class="mt-1 text-sm text-zinc-700 dark:text-zinc-300">
                                    {{ $pelanggan->email ?: '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-medium text-zinc-400">Telepon Rumah</dt>
                                <dd class="mt-1 text-sm text-zinc-700 dark:text-zinc-300">
                                    {{ $pelanggan->telepon_rumah ?: '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-medium text-zinc-400">Tipe Pelanggan</dt>
                                <dd class="mt-1 text-sm text-zinc-700 dark:text-zinc-300">
                                    {{ $pelanggan->tipe_pelanggan->label() }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-medium text-zinc-400">Kode Pembayaran (VA)</dt>
                                <dd class="mt-1 font-mono text-sm font-semibold text-zinc-800 dark:text-zinc-200">
                                    {{ $pelanggan->kode_pembayaran }}</dd>
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
                                <dd class="mt-1 text-sm text-zinc-700 dark:text-zinc-300">
                                    {{ $pelanggan->perumahan?->nama_perumahan ?? '—' }}</dd>
                            </div>
                        </dl>
                    </div>

                    {{-- Card Alamat --}}
                    <div
                        class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                        <flux:heading size="base" class="mb-4">Alamat Pemasangan</flux:heading>

                        <dl class="space-y-4">
                            <div>
                                <dt class="text-xs font-medium text-zinc-400">Alamat Lengkap</dt>
                                <dd class="mt-1 text-sm text-zinc-800 dark:text-zinc-200">
                                    {{ $pelanggan->alamat_lengkap }}</dd>
                            </div>
                            <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                                <div>
                                    <dt class="text-xs font-medium text-zinc-400">RT / RW</dt>
                                    <dd class="mt-1 text-sm text-zinc-700 dark:text-zinc-300">
                                        {{ $pelanggan->rt ?? '-' }} / {{ $pelanggan->rw ?? '-' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-xs font-medium text-zinc-400">No. Rumah</dt>
                                    <dd class="mt-1 text-sm text-zinc-700 dark:text-zinc-300">
                                        {{ $pelanggan->no_rumah ?? '—' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-xs font-medium text-zinc-400">Kode Pos</dt>
                                    <dd class="mt-1 text-sm text-zinc-700 dark:text-zinc-300">
                                        {{ $pelanggan->kode_pos ?? '—' }}</dd>
                                </div>
                            </div>
                        </dl>
                    </div>
                </div>

                {{-- Kolom Kanan: Informasi Internal --}}
                <div class="space-y-6">
                    <div
                        class="rounded-xl h-full border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                        <flux:heading size="base" class="mb-4">Informasi Internal</flux:heading>

                        <dl class="space-y-4 text-xs">
                            <div>
                                <dt class="text-zinc-400">Didaftarkan Oleh</dt>
                                <dd class="mt-0.5 font-medium text-zinc-800 dark:text-zinc-200">
                                    {{ $pelanggan->pembuat?->name ?? '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-zinc-400">Tanggal Dibuat</dt>
                                <dd class="mt-0.5 text-zinc-700 dark:text-zinc-300">
                                    {{ $pelanggan->created_at?->format('d/m/Y H:i') ?? '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-zinc-400">Terakhir Diperbarui</dt>
                                <dd class="mt-0.5 text-zinc-700 dark:text-zinc-300">
                                    {{ $pelanggan->updated_at?->format('d/m/Y H:i') ?? '—' }}</dd>
                            </div>
                        </dl>
                    </div>
                </div>
            </div>

            {{-- Card Titik Koordinat Lokasi (Full Width) --}}
            <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-center gap-3">
                        <flux:heading size="base">Titik Koordinat Lokasi</flux:heading>
                        @if (is_numeric($pelanggan->latitude) && is_numeric($pelanggan->longitude))
                            <flux:badge size="sm" color="emerald" class="font-mono text-xs">
                                <flux:icon name="map-pin" class="mr-1 size-3" />
                                Terpetakan
                            </flux:badge>
                        @endif
                    </div>

                    @if (is_numeric($pelanggan->latitude) && is_numeric($pelanggan->longitude))
                        <div class="flex flex-wrap items-center gap-2">
                            <a href="https://www.google.com/maps?q={{ $pelanggan->latitude }},{{ $pelanggan->longitude }}"
                                target="_blank" rel="noopener noreferrer"
                                class="inline-flex items-center gap-1.5 rounded-lg border border-zinc-200 bg-white px-3 py-1.5 text-xs font-medium text-zinc-700 shadow-sm transition hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-700">
                                <flux:icon name="arrow-top-right-on-square" class="size-3.5" />
                                Buka di Google Maps
                            </a>
                            <a href="https://www.openstreetmap.org/?mlat={{ $pelanggan->latitude }}&mlon={{ $pelanggan->longitude }}#map=16/{{ $pelanggan->latitude }}/{{ $pelanggan->longitude }}"
                                target="_blank" rel="noopener noreferrer"
                                class="inline-flex items-center gap-1.5 rounded-lg border border-zinc-200 bg-white px-3 py-1.5 text-xs font-medium text-zinc-700 shadow-sm transition hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-700">
                                <flux:icon name="map" class="size-3.5" />
                                Buka di OpenStreetMap
                            </a>
                            @can('update', $pelanggan)
                                <flux:button :href="route('pelanggan.edit', $pelanggan)" wire:navigate size="xs"
                                    variant="subtle" icon="pencil-square">
                                    Atur Titik Koordinat
                                </flux:button>
                            @endcan
                        </div>
                    @endif
                </div>

                @if (is_numeric($pelanggan->latitude) && is_numeric($pelanggan->longitude))
                    <div class="space-y-4">
                        {{-- Peta Leaflet Berukuran Besar --}}
                        <x-map-view :lat="$pelanggan->latitude" :lng="$pelanggan->longitude" :popup-title="$pelanggan->namaLengkap()" :popup-subtitle="$pelanggan->alamat_lengkap"
                            height="380px" />

                        {{-- Coordinate Details Bar --}}
                        <div
                            class="grid grid-cols-1 gap-3 rounded-lg bg-zinc-50 p-3.5 text-xs dark:bg-zinc-800/50 sm:grid-cols-3">
                            <div>
                                <span class="text-[11px] text-zinc-400">Latitude</span>
                                <div class="font-mono font-semibold text-zinc-800 dark:text-zinc-200">
                                    {{ number_format((float) $pelanggan->latitude, 7, '.', '') }}</div>
                            </div>
                            <div>
                                <span class="text-[11px] text-zinc-400">Longitude</span>
                                <div class="font-mono font-semibold text-zinc-800 dark:text-zinc-200">
                                    {{ number_format((float) $pelanggan->longitude, 7, '.', '') }}</div>
                            </div>
                            <div>
                                <span class="text-[11px] text-zinc-400">Koordinat Format (Lat, Lng)</span>
                                <div class="font-mono text-zinc-700 dark:text-zinc-300">{{ $pelanggan->latitude }},
                                    {{ $pelanggan->longitude }}</div>
                            </div>
                        </div>
                    </div>
                @else
                    <div class="flex flex-col items-center justify-center py-12 text-center">
                        <div
                            class="flex size-14 items-center justify-center rounded-full bg-zinc-100 dark:bg-zinc-800">
                            <flux:icon name="map-pin" class="size-7 text-zinc-400 dark:text-zinc-500" />
                        </div>
                        <span class="mt-3 text-sm font-medium text-zinc-700 dark:text-zinc-300">Koordinat belum ditentukan</span>
                        <span class="mt-1 text-xs text-zinc-400 dark:text-zinc-500">Tentukan titik koordinat untuk memudahkan teknisi instalasi di lapangan dan pemetaan jaringan.</span>
                        @can('update', $pelanggan)
                            <div class="mt-4">
                                <flux:button :href="route('pelanggan.edit', $pelanggan)" wire:navigate size="sm"
                                    variant="primary" icon="pencil-square">
                                    Atur Titik Koordinat
                                </flux:button>
                            </div>
                        @endcan
                    </div>
                @endif
            </div>
        </div>
    @endif

    {{-- Tab 2: Layanan Internet (Router & Paket Aktif) --}}
    @if ($activeTab === 'subscriptions')
        <div class="space-y-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <flux:heading size="base">Router & Paket Aktif Pelanggan</flux:heading>
                    <flux:subheading class="text-xs">Kelola router gateway BRAS, profil bandwidth paket langganan, dan pantau status realtime sesi PPP MikroTik.</flux:subheading>
                </div>
                <div class="flex items-center gap-2">
                    <flux:button
                        type="button"
                        wire:click="refreshPppStatus"
                        size="sm"
                        variant="subtle"
                        icon="arrow-path"
                        wire:loading.attr="disabled"
                    >
                        <span wire:loading.remove wire:target="refreshPppStatus">Segarkan Status PPP</span>
                        <span wire:loading wire:target="refreshPppStatus">Menghubungi Router...</span>
                    </flux:button>
                    @can('create', App\Models\LayananPelanggan::class)
                        <flux:button :href="route('layanan-pelanggan.create')" wire:navigate variant="primary" size="sm"
                            icon="plus">
                            Tambah Registrasi Billing
                        </flux:button>
                    @endcan
                </div>
            </div>

            @if ($pelanggan->layanans->isEmpty())
                <div
                    class="rounded-xl border border-dashed border-zinc-300 bg-zinc-50 p-12 text-center dark:border-zinc-700 dark:bg-zinc-900/50">
                    <flux:icon name="rss" class="mx-auto size-8 text-zinc-400" />
                    <flux:heading size="base" class="mt-2">Belum ada layanan aktif</flux:heading>
                    <flux:subheading class="mt-1">Pelanggan ini belum memiliki langganan paket internet atau akun PPP terdaftar.
                    </flux:subheading>
                </div>
            @else
                <div class="space-y-6">
                    @foreach ($pelanggan->layanans as $layanan)
                        @php
                            $statusPpp = $pppStatuses[$layanan->id] ?? null;
                            $isConnected = $statusPpp['is_connected'] ?? false;
                            $isDisabled = $statusPpp['is_disabled'] ?? ($layanan->status->value === 'isolir' || $layanan->status->value === 'nonaktif');
                            $routerOnline = $statusPpp['router_online'] ?? true;
                        @endphp
                        <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                            {{-- Header Card Layanan --}}
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between border-b border-zinc-100 pb-4 dark:border-zinc-800">
                                <div class="flex items-center gap-3">
                                    <div class="flex size-10 items-center justify-center rounded-lg bg-primary-50 text-primary-600 dark:bg-primary-950 dark:text-primary-400">
                                        <flux:icon name="server-stack" class="size-5" />
                                    </div>
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <flux:heading size="base">{{ $layanan->paketLayanan->nama_paket }}</flux:heading>
                                            <flux:badge size="sm" color="zinc" class="font-mono text-xs">{{ $layanan->site_id }}</flux:badge>
                                            @if ($isConnected)
                                                <flux:badge size="sm" color="emerald" class="animate-pulse">
                                                    <span class="inline-block size-1.5 rounded-full bg-emerald-500 mr-1.5"></span>
                                                    Connected (Online)
                                                </flux:badge>
                                            @else
                                                <flux:badge size="sm" color="zinc">
                                                    <span class="inline-block size-1.5 rounded-full bg-zinc-400 mr-1.5"></span>
                                                    Disconnected (Offline)
                                                </flux:badge>
                                            @endif
                                            @if ($isDisabled)
                                                <flux:badge size="sm" color="rose">
                                                    Terisolir / Disabled
                                                </flux:badge>
                                            @endif
                                        </div>
                                        <flux:subheading class="text-xs">
                                            Sinkronisasi status realtime dengan MikroTik BRAS Gateway
                                        </flux:subheading>
                                    </div>
                                </div>

                                <div class="flex items-center gap-2">
                                    <flux:badge size="sm" :color="$layanan->status->color()">
                                        {{ $layanan->status->label() }}
                                    </flux:badge>
                                </div>
                            </div>

                            {{-- Grid Informasi Utama Layanan & Billing --}}
                            <div class="mt-5 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
                                {{-- 1. Router --}}
                                <div class="space-y-1">
                                    <span class="text-xs font-medium text-zinc-400">Router BRAS Gateway</span>
                                    <div class="flex items-center gap-2 text-sm font-semibold text-zinc-800 dark:text-zinc-200">
                                        <flux:icon name="cpu-chip" class="size-4 text-zinc-400" />
                                        @can('view', $layanan->router)
                                            <a href="{{ route('router.edit', $layanan->router) }}" wire:navigate class="hover:underline text-primary-600 dark:text-primary-400">
                                                {{ $layanan->router->nama_router }}
                                            </a>
                                        @else
                                            <span>{{ $layanan->router->nama_router }}</span>
                                        @endcan
                                    </div>
                                    <div class="font-mono text-[11px] text-zinc-500">
                                        {{ $layanan->router->ip_address }}:{{ $layanan->router->port }}
                                    </div>
                                </div>

                                {{-- 2. Paket Aktif --}}
                                <div class="space-y-1">
                                    <span class="text-xs font-medium text-zinc-400">Paket Langganan Aktif</span>
                                    <div class="text-sm font-semibold text-zinc-800 dark:text-zinc-200">
                                        {{ $layanan->paketLayanan->nama_paket }}
                                    </div>
                                    <div class="flex items-center gap-1.5 text-xs text-zinc-500">
                                        <flux:badge size="sm" color="zinc" class="text-[11px]">
                                            {{ $layanan->paketLayanan->profilBandwidth?->labelKecepatan() ?? '-' }}
                                        </flux:badge>
                                        <span>• Rp {{ number_format($layanan->total_tarif, 0, ',', '.') }}/bln</span>
                                    </div>
                                </div>

                                {{-- 3. Username PPP --}}
                                <div class="space-y-1">
                                    <span class="text-xs font-medium text-zinc-400">Username PPP (Secret)</span>
                                    <div class="flex items-center gap-2">
                                        <span class="font-mono text-sm font-bold text-zinc-900 dark:text-zinc-100">
                                            {{ $layanan->ppp_username }}
                                        </span>
                                    </div>
                                    <div class="text-[11px] text-zinc-500 font-mono">
                                        Pass: {{ $layanan->ppp_password_terenkripsi }}
                                    </div>
                                </div>

                                {{-- 4. Status Layanan Billing --}}
                                <div class="space-y-1">
                                    <span class="text-xs font-medium text-zinc-400">Status Billing & Jatuh Tempo</span>
                                    <div>
                                        <flux:badge size="sm" :color="$layanan->status->color()">
                                            {{ $layanan->status->label() }}
                                        </flux:badge>
                                    </div>
                                    <div class="text-[11px] text-zinc-500">
                                        Jatuh Tempo: {{ $layanan->tanggal_expired?->format('d/m/Y') ?? '—' }}
                                    </div>
                                </div>
                            </div>

                            {{-- Sub-Section: Status PPP Realtime (MikroTik) --}}
                            <div class="mt-6 rounded-lg bg-zinc-50 p-4 dark:bg-zinc-800/60">
                                <div class="mb-3 flex items-center justify-between">
                                    <span class="text-xs font-bold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                                        Status Realtime PPP (MikroTik RouterOS)
                                    </span>
                                    @if (! $routerOnline)
                                        <span class="text-xs text-amber-600 dark:text-amber-400 flex items-center gap-1">
                                            <flux:icon name="exclamation-triangle" class="size-3.5" />
                                            Router tidak dapat dihubungi
                                        </span>
                                    @endif
                                </div>

                                <dl class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6 text-xs">
                                    {{-- Status --}}
                                    <div>
                                        <dt class="text-zinc-400">Status</dt>
                                        <dd class="mt-1 font-semibold">
                                            @if ($isConnected)
                                                <span class="text-emerald-600 dark:text-emerald-400 flex items-center gap-1">
                                                    <span class="size-2 rounded-full bg-emerald-500 inline-block"></span>
                                                    Connected
                                                </span>
                                            @else
                                                <span class="text-zinc-500 dark:text-zinc-400 flex items-center gap-1">
                                                    <span class="size-2 rounded-full bg-zinc-400 inline-block"></span>
                                                    Disconnected
                                                </span>
                                            @endif
                                        </dd>
                                    </div>

                                    {{-- Profile --}}
                                    <div>
                                        <dt class="text-zinc-400">Profile</dt>
                                        <dd class="mt-1 font-medium font-mono text-zinc-800 dark:text-zinc-200">
                                            {{ $statusPpp['profile'] ?? $layanan->paketLayanan->profilBandwidth?->nama_bandwidth ?? '—' }}
                                        </dd>
                                    </div>

                                    {{-- Service --}}
                                    <div>
                                        <dt class="text-zinc-400">Service</dt>
                                        <dd class="mt-1 font-medium font-mono uppercase text-zinc-800 dark:text-zinc-200">
                                            {{ $statusPpp['service'] ?? $layanan->jenis_koneksi?->value ?? 'pppoe' }}
                                        </dd>
                                    </div>

                                    {{-- IP Address / Remote IP --}}
                                    <div>
                                        <dt class="text-zinc-400">IP Remote (ONT)</dt>
                                        <dd class="mt-1 font-mono text-xs">
                                            @if ($isConnected && ! empty($statusPpp['ip_address']))
                                                <a href="http://{{ $statusPpp['ip_address'] }}" target="_blank" rel="noopener noreferrer" class="font-bold text-primary-600 dark:text-primary-400 hover:underline inline-flex items-center gap-1" title="Remote ONT Web GUI">
                                                    {{ $statusPpp['ip_address'] }}
                                                    <flux:icon name="arrow-top-right-on-square" class="size-3" />
                                                </a>
                                            @elseif (! $isConnected)
                                                <div class="flex flex-col gap-0.5">
                                                    <span class="inline-flex items-center gap-1 rounded bg-rose-50 px-1.5 py-0.5 text-[11px] font-semibold text-rose-700 dark:bg-rose-950 dark:text-rose-300 w-fit">
                                                        <span class="size-1.5 rounded-full bg-rose-500"></span>
                                                        Belum tersambung (Offline)
                                                    </span>
                                                    @if (! empty($layanan->ip_static))
                                                        <span class="text-[10px] text-zinc-400 font-normal">Target Statis: {{ $layanan->ip_static }}</span>
                                                    @endif
                                                </div>
                                            @else
                                                <span class="text-zinc-400">—</span>
                                            @endif
                                        </dd>
                                    </div>

                                    {{-- Uptime --}}
                                    <div>
                                        <dt class="text-zinc-400">Uptime</dt>
                                        <dd class="mt-1 font-medium font-mono text-zinc-800 dark:text-zinc-200">
                                            {{ $statusPpp['uptime'] ?? '—' }}
                                        </dd>
                                    </div>

                                    {{-- Last Disconnect / Logged Out --}}
                                    <div>
                                        <dt class="text-zinc-400">Last DN / Logout</dt>
                                        <dd class="mt-1 text-zinc-700 dark:text-zinc-300">
                                            {{ $statusPpp['last_logged_out'] ?? '—' }}
                                        </dd>
                                    </div>
                                </dl>

                                {{-- Additional Row: Gateway, Caller ID MAC, Disabled --}}
                                <dl class="mt-3 pt-3 border-t border-zinc-200/60 dark:border-zinc-700/60 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6 text-xs">
                                    <div>
                                        <dt class="text-zinc-400">Gateway (Local IP)</dt>
                                        <dd class="mt-0.5 font-mono text-zinc-700 dark:text-zinc-300">
                                            {{ $statusPpp['local_address'] ?? $layanan->resolveLocalAddress() ?? '—' }}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt class="text-zinc-400">Caller ID (MAC ONT)</dt>
                                        <dd class="mt-0.5 font-mono text-zinc-700 dark:text-zinc-300">
                                            {{ $statusPpp['caller_id'] ?? '—' }}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt class="text-zinc-400">Status Secret (Disabled)</dt>
                                        <dd class="mt-0.5">
                                            @if ($isDisabled)
                                                <span class="inline-flex items-center rounded bg-rose-50 px-1.5 py-0.5 text-[11px] font-medium text-rose-700 dark:bg-rose-950 dark:text-rose-300">
                                                    true (Isolir)
                                                </span>
                                            @else
                                                <span class="inline-flex items-center rounded bg-emerald-50 px-1.5 py-0.5 text-[11px] font-medium text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300">
                                                    false (Aktif)
                                                </span>
                                            @endif
                                        </dd>
                                    </div>
                                    <div>
                                        <dt class="text-zinc-400">Port ODP</dt>
                                        <dd class="mt-0.5 text-zinc-700 dark:text-zinc-300">
                                            {{ $layanan->odpPort?->odp?->nama_odp ?? '—' }} (Port {{ $layanan->odpPort?->nomor_port ?? '—' }})
                                        </dd>
                                    </div>
                                    <div>
                                        <dt class="text-zinc-400">Tanggal Mulai</dt>
                                        <dd class="mt-0.5 text-zinc-700 dark:text-zinc-300">
                                            {{ $layanan->tanggal_mulai->format('d/m/Y') }}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt class="text-zinc-400">Auto Isolir</dt>
                                        <dd class="mt-0.5 text-zinc-700 dark:text-zinc-300">
                                            {{ $layanan->auto_isolir ? 'Aktif' : 'Nonaktif' }}
                                        </dd>
                                    </div>
                                </dl>
                            </div>
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

            <div class="px-6 pb-6">
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
        </div>
    @endif
</div>
