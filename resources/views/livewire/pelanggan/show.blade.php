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
        <nav class="-mb-px flex flex-wrap gap-6" aria-label="Tabs">
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
                    <span class="rounded-full bg-zinc-200 px-1.5 py-0.2 text-xs font-semibold text-zinc-700 dark:bg-zinc-700 dark:text-zinc-300">{{ $pelanggan->layanans->count() }}</span>
                @endif
            </button>

            <button type="button" wire:click="setTab('billing')"
                class="flex items-center gap-2 border-b-2 py-3 text-sm font-medium transition-colors {{ $activeTab === 'billing' ? 'border-primary-600 text-primary-600 dark:border-primary-400 dark:text-primary-400' : 'border-transparent text-zinc-500 hover:border-zinc-300 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200' }}">
                <flux:icon name="banknotes" class="size-4" />
                Tagihan & Pembayaran
                @if ($invoicesAktif->isNotEmpty())
                    <span class="rounded-full bg-rose-100 px-1.5 py-0.2 text-xs font-semibold text-rose-700 dark:bg-rose-950 dark:text-rose-300">{{ $invoicesAktif->count() }}</span>
                @elseif ($invoicesLunas->isNotEmpty())
                    <span class="rounded-full bg-zinc-200 px-1.5 py-0.2 text-xs font-semibold text-zinc-700 dark:bg-zinc-700 dark:text-zinc-300">{{ $invoicesLunas->count() }}</span>
                @endif
            </button>

            <button type="button" wire:click="setTab('dokumen')"
                class="flex items-center gap-2 border-b-2 py-3 text-sm font-medium transition-colors {{ $activeTab === 'dokumen' ? 'border-primary-600 text-primary-600 dark:border-primary-400 dark:text-primary-400' : 'border-transparent text-zinc-500 hover:border-zinc-300 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200' }}">
                <flux:icon name="document-duplicate" class="size-4" />
                Dokumen & Legalitas
                @if ($dokumens->isNotEmpty() || $ktpMedia)
                    <span class="rounded-full bg-zinc-200 px-1.5 py-0.2 text-xs font-semibold text-zinc-700 dark:bg-zinc-700 dark:text-zinc-300">{{ $dokumens->count() + ($ktpMedia ? 1 : 0) }}</span>
                @endif
            </button>

            <button type="button" wire:click="setTab('audit')"
                class="flex items-center gap-2 border-b-2 py-3 text-sm font-medium transition-colors {{ $activeTab === 'audit' ? 'border-primary-600 text-primary-600 dark:border-primary-400 dark:text-primary-400' : 'border-transparent text-zinc-500 hover:border-zinc-300 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200' }}">
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
        <div class="space-y-6">
            {{-- Bagian Atas: Kontak & Identitas, Alamat, dan Akun Portal / Internal --}}
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                {{-- Kolom 1 & 2: Detail Kontak & Identitas + Alamat --}}
                <div class="space-y-6 lg:col-span-2">
                    {{-- Card Data Pelanggan (Kontak & Identitas) --}}
                    <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                        <div class="flex items-center justify-between mb-4 border-b border-zinc-100 pb-3 dark:border-zinc-800">
                            <flux:heading size="base">Data Pelanggan & Identitas</flux:heading>
                            <flux:badge size="sm" :color="$pelanggan->tipe_pelanggan->value === 'bisnis' ? 'purple' : 'zinc'">
                                {{ $pelanggan->tipe_pelanggan->label() }}
                            </flux:badge>
                        </div>

                        <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <dt class="text-xs font-medium text-zinc-400">Nomor Registrasi (No. Reg)</dt>
                                <dd class="mt-1 font-mono text-sm font-bold text-zinc-900 dark:text-zinc-100">
                                    {{ $pelanggan->no_reg }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-xs font-medium text-zinc-400">Nama Lengkap Pelanggan</dt>
                                <dd class="mt-1 text-sm font-semibold text-zinc-900 dark:text-zinc-100">
                                    {{ $pelanggan->namaLengkap() }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-xs font-medium text-zinc-400">Nomor HP / WhatsApp</dt>
                                <dd class="mt-1 flex items-center gap-2 text-sm font-medium text-zinc-900 dark:text-zinc-100">
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
                                    {{ $pelanggan->email ?: '—' }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-xs font-medium text-zinc-400">Telepon Rumah</dt>
                                <dd class="mt-1 text-sm text-zinc-700 dark:text-zinc-300">
                                    {{ $pelanggan->telepon_rumah ?: '—' }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-xs font-medium text-zinc-400">Nomor Induk Kependudukan (NIK)</dt>
                                <dd class="mt-1 flex items-center gap-2 text-sm font-mono font-medium text-zinc-900 dark:text-zinc-100">
                                    @if ($pelanggan->nik)
                                        @if ($showNik)
                                            <span>{{ $pelanggan->nik }}</span>
                                        @else
                                            <span>{{ substr($pelanggan->nik, 0, 4) . '••••••••' . substr($pelanggan->nik, -4) }}</span>
                                        @endif
                                        <button
                                            type="button"
                                            wire:click="toggleShowNik"
                                            class="text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200 transition"
                                            title="{{ $showNik ? 'Sembunyikan NIK' : 'Tampilkan NIK' }}"
                                        >
                                            <flux:icon :name="$showNik ? 'eye-slash' : 'eye'" class="size-4" />
                                        </button>
                                    @else
                                        <span class="text-zinc-400">—</span>
                                    @endif
                                </dd>
                            </div>
                            <div>
                                <dt class="text-xs font-medium text-zinc-400">Kode Pembayaran (Virtual Account)</dt>
                                <dd class="mt-1 font-mono text-sm font-bold text-primary-700 dark:text-primary-400">
                                    {{ $pelanggan->kode_pembayaran }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-xs font-medium text-zinc-400">Status Akun</dt>
                                <dd class="mt-1">
                                    <flux:badge size="sm" :color="$pelanggan->status->color()">
                                        {{ $pelanggan->status->label() }}
                                    </flux:badge>
                                </dd>
                            </div>
                        </dl>
                    </div>

                    {{-- Card Alamat & Wilayah --}}
                    <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                        <flux:heading size="base" class="mb-4 border-b border-zinc-100 pb-3 dark:border-zinc-800">Alamat & Lokasi Pemasangan</flux:heading>

                        <dl class="space-y-4">
                            <div>
                                <dt class="text-xs font-medium text-zinc-400">Alamat Lengkap</dt>
                                <dd class="mt-1 text-sm text-zinc-800 dark:text-zinc-200 font-medium">
                                    {{ $pelanggan->alamat_lengkap }}
                                </dd>
                            </div>

                            <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                                <div>
                                    <dt class="text-xs font-medium text-zinc-400">RT / RW</dt>
                                    <dd class="mt-1 text-sm text-zinc-700 dark:text-zinc-300">
                                        {{ $pelanggan->rt ?? '-' }} / {{ $pelanggan->rw ?? '-' }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-xs font-medium text-zinc-400">No. Rumah</dt>
                                    <dd class="mt-1 text-sm text-zinc-700 dark:text-zinc-300">
                                        {{ $pelanggan->no_rumah ?? '—' }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-xs font-medium text-zinc-400">Kode Pos</dt>
                                    <dd class="mt-1 text-sm text-zinc-700 dark:text-zinc-300">
                                        {{ $pelanggan->kode_pos ?? '—' }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-xs font-medium text-zinc-400">Perumahan / Cluster</dt>
                                    <dd class="mt-1 text-sm font-medium text-zinc-800 dark:text-zinc-200">
                                        {{ $pelanggan->perumahan?->nama_perumahan ?? '—' }}
                                    </dd>
                                </div>
                            </div>

                            @if ($pelanggan->perumahan)
                                <div class="rounded-lg bg-zinc-50 p-3 text-xs dark:bg-zinc-800/50">
                                    <span class="text-zinc-400">Struktur Wilayah Administratif:</span>
                                    <div class="mt-1 font-medium text-zinc-700 dark:text-zinc-300">
                                        Kel. {{ $pelanggan->perumahan->kelurahan?->nama_kelurahan ?? '—' }} •
                                        Kec. {{ $pelanggan->perumahan->kelurahan?->kecamatan?->nama_kecamatan ?? '—' }} •
                                        Kota {{ $pelanggan->perumahan->kelurahan?->kecamatan?->kota?->nama_kota ?? '—' }}
                                    </div>
                                </div>
                            @endif
                        </dl>
                    </div>
                </div>

                {{-- Kolom 3: Akun Portal Pelanggan & Informasi Internal --}}
                <div class="space-y-6">
                    {{-- Card Akun Portal Pelanggan --}}
                    <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                        <div class="flex items-center gap-2 mb-4 border-b border-zinc-100 pb-3 dark:border-zinc-800">
                            <flux:icon name="user-circle" class="size-5 text-primary-600 dark:text-primary-400" />
                            <div>
                                <flux:heading size="base">Akun Portal Pelanggan</flux:heading>
                                <flux:subheading class="text-xs">Akses pelanggan ke Self-Care Client Area</flux:subheading>
                            </div>
                        </div>

                        <dl class="space-y-3.5 text-xs">
                            <div>
                                <dt class="text-zinc-400">Username Portal (Email)</dt>
                                <dd class="mt-0.5 font-medium text-sm text-zinc-800 dark:text-zinc-200">
                                    {{ $pelanggan->akunPelanggan?->email ?? $pelanggan->email ?? '— (Belum didaftarkan)' }}
                                </dd>
                            </div>

                            <div>
                                <dt class="text-zinc-400">Password Akun Portal</dt>
                                <dd class="mt-0.5 flex items-center justify-between">
                                    <span class="font-mono text-zinc-700 dark:text-zinc-300">Default: <strong>12345678</strong></span>
                                    @can('update', $pelanggan)
                                        <button
                                            type="button"
                                            wire:click="resetPasswordPortal"
                                            wire:confirm="Apakah Anda yakin ingin mereset password akun portal pelanggan ini ke default (12345678)?"
                                            class="text-[11px] text-primary-600 hover:underline dark:text-primary-400 font-semibold"
                                        >
                                            Reset Password
                                        </button>
                                    @endcan
                                </dd>
                            </div>

                            <div>
                                <dt class="text-zinc-400">Status Akun Portal</dt>
                                <dd class="mt-0.5">
                                    @if ($pelanggan->akunPelanggan)
                                        <flux:badge size="sm" color="emerald">Aktif & Terdaftar</flux:badge>
                                    @else
                                        <flux:badge size="sm" color="amber">Belum Ada Akun</flux:badge>
                                    @endif
                                </dd>
                            </div>
                        </dl>

                        <div class="mt-5 border-t border-zinc-100 pt-4 dark:border-zinc-800">
                            @canImpersonate
                                @if ($pelanggan->akunPelanggan && $pelanggan->akunPelanggan->canBeImpersonated())
                                    <flux:button
                                        :href="route('impersonate', ['id' => $pelanggan->akunPelanggan->id, 'guardName' => 'pelanggan'])"
                                        variant="subtle"
                                        class="w-full"
                                        icon="arrow-right-end-on-rectangle"
                                    >
                                        Buka Portal (Login as)
                                    </flux:button>
                                @endif
                            @endCanImpersonate
                        </div>
                    </div>

                    {{-- Card Informasi Internal --}}
                    <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                        <flux:heading size="base" class="mb-4 border-b border-zinc-100 pb-3 dark:border-zinc-800">Informasi Registrasi</flux:heading>

                        <dl class="space-y-3.5 text-xs">
                            <div>
                                <dt class="text-zinc-400">Didaftarkan Oleh</dt>
                                <dd class="mt-0.5 font-medium text-zinc-800 dark:text-zinc-200">
                                    {{ $pelanggan->pembuat?->name ?? 'Sistem' }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-zinc-400">Waktu Dibuat</dt>
                                <dd class="mt-0.5 text-zinc-700 dark:text-zinc-300">
                                    {{ $pelanggan->created_at?->translatedFormat('d F Y, H:i') ?? '—' }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-zinc-400">Terakhir Diperbarui</dt>
                                <dd class="mt-0.5 text-zinc-700 dark:text-zinc-300">
                                    {{ $pelanggan->updated_at?->translatedFormat('d F Y, H:i') ?? '—' }}
                                </dd>
                            </div>
                        </dl>
                    </div>
                </div>
            </div>

            {{-- Card Titik Koordinat Lokasi & Peta Leaflet (Full Width) --}}
            <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-center gap-3">
                        <flux:heading size="base">Titik Lokasi Pelanggan (Peta)</flux:heading>
                        @if (is_numeric($pelanggan->latitude) && is_numeric($pelanggan->longitude))
                            <flux:badge size="sm" color="emerald" class="font-mono text-xs">
                                <flux:icon name="map-pin" class="mr-1 size-3" />
                                Terpetakan
                            </flux:badge>
                        @endif
                    </div>

                    @if (is_numeric($pelanggan->latitude) && is_numeric($pelanggan->longitude))
                        <div class="flex flex-wrap items-center gap-2">
                            <a href="{{ route('maps.estimasi-kabel', ['lat' => $pelanggan->latitude, 'lng' => $pelanggan->longitude]) }}"
                                wire:navigate
                                class="inline-flex items-center gap-1.5 rounded-lg border border-primary-200 bg-primary-50 px-3 py-1.5 text-xs font-semibold text-primary-700 shadow-sm transition hover:bg-primary-100 dark:border-primary-800 dark:bg-primary-950 dark:text-primary-300 dark:hover:bg-primary-900">
                                <flux:icon name="calculator" class="size-3.5" />
                                Hitung Estimasi Kabel
                            </a>
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
                        <div class="rounded-lg border border-zinc-200/80 bg-zinc-50/50 p-2.5 text-xs text-zinc-600 dark:border-zinc-800 dark:bg-zinc-900/50 dark:text-zinc-400 flex items-center justify-between">
                            <span class="flex items-center gap-1.5">
                                <flux:icon name="information-circle" class="size-4 text-zinc-400" />
                                Peta hanya untuk visualisasi lokasi pelanggan. Perubahan koordinat dilakukan dari menu edit pelanggan.
                            </span>
                            @can('update', $pelanggan)
                                <a href="{{ route('pelanggan.edit', $pelanggan) }}" wire:navigate class="text-primary-600 hover:underline dark:text-primary-400 font-medium">Edit Titik Lokasi →</a>
                            @endcan
                        </div>

                        {{-- Peta Leaflet --}}
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
                    <flux:heading size="base">Router & Paket Aktif Pelanggan (Layanan / Pemasangan)</flux:heading>
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
                                            <flux:heading size="base">{{ $layanan->label_layanan ?: $layanan->paketLayanan->nama_paket }}</flux:heading>
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
                                    <flux:badge size="sm" :color="$layanan->statusBadgeColor()">
                                        {{ $layanan->statusBadgeLabel() }}
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
                                        <flux:badge size="sm" :color="$layanan->statusBadgeColor()">
                                            {{ $layanan->statusBadgeLabel() }}
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

    {{-- Tab 3: Tagihan & Pembayaran (Billing Keuangan) --}}
    @if ($activeTab === 'billing')
        <div class="space-y-8">
            {{-- 1. Tagihan Aktif (Belum Lunas / Pending) --}}
            <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between border-b border-zinc-100 pb-4 dark:border-zinc-800">
                    <div class="flex items-center gap-3">
                        <div class="flex size-9 items-center justify-center rounded-lg bg-amber-50 text-amber-600 dark:bg-amber-950/50 dark:text-amber-400">
                            <flux:icon name="exclamation-circle" class="size-5" />
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <flux:heading size="base">Tagihan Aktif (Belum Lunas / Pending)</flux:heading>
                                <flux:badge size="sm" :color="$invoicesAktif->isNotEmpty() ? 'rose' : 'zinc'">
                                    {{ $invoicesAktif->count() }} Tagihan
                                </flux:badge>
                            </div>
                            <flux:description class="text-xs">Daftar invoice berjalan yang menunggu pembayaran atau tertunda.</flux:description>
                        </div>
                    </div>

                    @can('create', App\Models\Invoice::class)
                        <flux:button :href="route('invoice.create')" wire:navigate size="sm" variant="subtle" icon="plus">
                            Terbitkan Invoice
                        </flux:button>
                    @endcan
                </div>

                <div class="mt-4">
                    @if ($invoicesAktif->isNotEmpty())
                        <div class="overflow-x-auto">
                            <flux:table>
                                <flux:table.columns>
                                    <flux:table.column>No. Invoice</flux:table.column>
                                    <flux:table.column>Layanan</flux:table.column>
                                    <flux:table.column>Detail</flux:table.column>
                                    <flux:table.column>Tanggal</flux:table.column>
                                    <flux:table.column>Metode</flux:table.column>
                                    <flux:table.column>Teller / Ref</flux:table.column>
                                    <flux:table.column>Jumlah</flux:table.column>
                                    <flux:table.column>Promo</flux:table.column>
                                    <flux:table.column>Status</flux:table.column>
                                    <flux:table.column class="text-right">Proses</flux:table.column>
                                </flux:table.columns>

                                <flux:table.rows>
                                    @foreach ($invoicesAktif as $inv)
                                        @php
                                            $pgTrx = $inv->transaksiPaymentGateways->first();
                                        @endphp
                                        <flux:table.row :key="$inv->id">
                                            {{-- No Invoice --}}
                                            <flux:table.cell>
                                                <a href="{{ route('invoice.show', $inv) }}" wire:navigate class="font-mono text-xs font-bold text-primary-600 hover:underline dark:text-primary-400">
                                                    {{ $inv->no_invoice }}
                                                </a>
                                            </flux:table.cell>

                                            {{-- Layanan --}}
                                            <flux:table.cell class="text-xs">
                                                <div class="font-medium text-zinc-800 dark:text-zinc-200">
                                                    {{ $inv->layananPelanggan?->paketLayanan?->nama_paket ?? 'Layanan' }}
                                                </div>
                                                <div class="font-mono text-[11px] text-zinc-400">
                                                    {{ $inv->layananPelanggan?->site_id ?? '—' }}
                                                </div>
                                            </flux:table.cell>

                                            {{-- Detail (Periode Tagihan) --}}
                                            <flux:table.cell class="text-xs">
                                                <span class="font-medium text-zinc-700 dark:text-zinc-300">
                                                    {{ $inv->formattedPeriodeTagihan() }}
                                                </span>
                                            </flux:table.cell>

                                            {{-- Tanggal (Jatuh Tempo) --}}
                                            <flux:table.cell class="text-xs">
                                                <div class="text-zinc-800 dark:text-zinc-200">
                                                    {{ $inv->tanggal_jatuh_tempo->format('d/m/Y') }}
                                                </div>
                                                <span class="text-[11px] text-zinc-400">Terbit: {{ $inv->tanggal_terbit->format('d/m/Y') }}</span>
                                            </flux:table.cell>

                                            {{-- Metode --}}
                                            <flux:table.cell class="text-xs">
                                                @if ($pgTrx)
                                                    <flux:badge size="sm" color="indigo">
                                                        {{ strtoupper($pgTrx->gateway) }} ({{ strtoupper($pgTrx->channel?->value ?? 'VA') }})
                                                    </flux:badge>
                                                @elseif ($inv->metode_pembayaran)
                                                    <span class="capitalize text-zinc-700 dark:text-zinc-300">{{ $inv->metode_pembayaran?->label() ?? $inv->metode_pembayaran }}</span>
                                                @else
                                                    <span class="text-zinc-400">—</span>
                                                @endif
                                            </flux:table.cell>

                                            {{-- Teller / Ref --}}
                                            <flux:table.cell class="text-xs">
                                                @if ($pgTrx && $pgTrx->nomor_pembayaran)
                                                    <div class="font-mono text-[11px] font-semibold text-zinc-800 dark:text-zinc-200">
                                                        VA: {{ $pgTrx->nomor_pembayaran }}
                                                    </div>
                                                @elseif ($inv->xendit_invoice_id)
                                                    <div class="font-mono text-[11px] text-zinc-500">
                                                        Xendit: {{ substr($inv->xendit_invoice_id, -8) }}
                                                    </div>
                                                @else
                                                    <span class="text-zinc-400">—</span>
                                                @endif
                                            </flux:table.cell>

                                            {{-- Jumlah --}}
                                            <flux:table.cell>
                                                <div class="font-mono text-xs font-bold text-zinc-900 dark:text-zinc-100">
                                                    {{ $inv->formattedJumlahSetelahPromo() }}
                                                </div>
                                                @if ($inv->jumlah != $inv->jumlah_setelah_promo)
                                                    <span class="text-[11px] line-through text-zinc-400 font-mono">
                                                        {{ $inv->formattedJumlah() }}
                                                    </span>
                                                @endif
                                            </flux:table.cell>

                                            {{-- Promo --}}
                                            <flux:table.cell class="text-xs">
                                                @if ($inv->promo)
                                                    <flux:badge size="sm" color="emerald" class="text-[11px]">
                                                        {{ $inv->promo->kode_promo }}
                                                    </flux:badge>
                                                @else
                                                    <span class="text-zinc-400">—</span>
                                                @endif
                                            </flux:table.cell>

                                            {{-- Status --}}
                                            <flux:table.cell>
                                                <flux:badge size="sm" :color="$inv->status->color()">
                                                    {{ $inv->status->label() }}
                                                </flux:badge>
                                            </flux:table.cell>

                                            {{-- Proses --}}
                                            <flux:table.cell class="text-right">
                                                <div class="flex items-center justify-end gap-1.5">
                                                    @can('create', App\Models\Pembayaran::class)
                                                        <flux:button
                                                            type="button"
                                                            wire:click="openBayarModal({{ $inv->id }})"
                                                            size="xs"
                                                            variant="primary"
                                                            icon="banknotes"
                                                        >
                                                            Bayar
                                                        </flux:button>
                                                    @endcan

                                                    @if ($inv->hasActiveXenditInvoice())
                                                        <flux:button
                                                            :href="$inv->xendit_invoice_url"
                                                            target="_blank"
                                                            size="xs"
                                                            variant="subtle"
                                                            icon="arrow-top-right-on-square"
                                                            title="Buka Tautan Pembayaran Xendit"
                                                        />
                                                    @endif

                                                    <flux:button
                                                        :href="route('invoice.show', $inv)"
                                                        wire:navigate
                                                        size="xs"
                                                        variant="ghost"
                                                        icon="eye"
                                                        title="Lihat Detail Invoice"
                                                    />
                                                </div>
                                            </flux:table.cell>
                                        </flux:table.row>
                                    @endforeach
                                </flux:table.rows>
                            </flux:table>
                        </div>
                    @else
                        <div class="rounded-lg border-2 border-dashed border-zinc-200 p-8 text-center dark:border-zinc-800">
                            <flux:icon name="check-circle" class="mx-auto size-8 text-emerald-500 dark:text-emerald-400" />
                            <p class="mt-2 text-xs font-medium text-zinc-700 dark:text-zinc-300">Tidak ada tagihan aktif yang tertunda.</p>
                            <span class="text-[11px] text-zinc-400">Seluruh tagihan pelanggan ini sudah lunas atau belum diterbitkan.</span>
                        </div>
                    @endif
                </div>
            </div>

            {{-- 2. Riwayat Pembayaran Lunas --}}
            <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <div class="flex items-center justify-between border-b border-zinc-100 pb-4 dark:border-zinc-800">
                    <div class="flex items-center gap-3">
                        <div class="flex size-9 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600 dark:bg-emerald-950/50 dark:text-emerald-400">
                            <flux:icon name="check-badge" class="size-5" />
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <flux:heading size="base">Riwayat Pembayaran Lunas</flux:heading>
                                <span class="text-xs text-zinc-500 font-medium">({{ $invoicesLunas->count() }} Transaksi)</span>
                            </div>
                            <flux:description class="text-xs">Catatan pembayaran dan pelunasan tagihan yang berhasil diproses.</flux:description>
                        </div>
                    </div>
                </div>

                <div class="mt-4">
                    @if ($invoicesLunas->isNotEmpty())
                        <div class="overflow-x-auto">
                            <flux:table>
                                <flux:table.columns>
                                    <flux:table.column>No. Invoice</flux:table.column>
                                    <flux:table.column>Layanan</flux:table.column>
                                    <flux:table.column>Detail</flux:table.column>
                                    <flux:table.column>Tanggal</flux:table.column>
                                    <flux:table.column>Metode</flux:table.column>
                                    <flux:table.column>Teller / Ref</flux:table.column>
                                    <flux:table.column>Jumlah (Rp.)</flux:table.column>
                                    <flux:table.column>Promo</flux:table.column>
                                    <flux:table.column>Status</flux:table.column>
                                    <flux:table.column class="text-right">Aksi</flux:table.column>
                                </flux:table.columns>

                                <flux:table.rows>
                                    @foreach ($invoicesLunas as $inv)
                                        @php
                                            $lastPayment = $inv->pembayarans->first();
                                        @endphp
                                        <flux:table.row :key="$inv->id">
                                            {{-- No Invoice --}}
                                            <flux:table.cell>
                                                <a href="{{ route('invoice.show', $inv) }}" wire:navigate class="font-mono text-xs font-bold text-primary-600 hover:underline dark:text-primary-400">
                                                    {{ $inv->no_invoice }}
                                                </a>
                                            </flux:table.cell>

                                            {{-- Layanan --}}
                                            <flux:table.cell class="text-xs">
                                                <div class="font-medium text-zinc-800 dark:text-zinc-200">
                                                    {{ $inv->layananPelanggan?->paketLayanan?->nama_paket ?? 'Layanan' }}
                                                </div>
                                                <div class="font-mono text-[11px] text-zinc-400">
                                                    {{ $inv->layananPelanggan?->site_id ?? '—' }}
                                                </div>
                                            </flux:table.cell>

                                            {{-- Detail (Periode Tagihan) --}}
                                            <flux:table.cell class="text-xs">
                                                <span class="font-medium text-zinc-700 dark:text-zinc-300">
                                                    {{ $inv->formattedPeriodeTagihan() }}
                                                </span>
                                            </flux:table.cell>

                                            {{-- Tanggal (Tanggal Lunas / Bayar) --}}
                                            <flux:table.cell class="text-xs">
                                                <div class="font-medium text-zinc-800 dark:text-zinc-200">
                                                    {{ $inv->tanggal_lunas?->format('d/m/Y') ?? $lastPayment?->dibayar_pada?->format('d/m/Y') ?? '—' }}
                                                </div>
                                                @if ($lastPayment?->dibayar_pada)
                                                    <span class="text-[11px] text-zinc-400">{{ $lastPayment->dibayar_pada->format('H:i') }} WIB</span>
                                                @endif
                                            </flux:table.cell>

                                            {{-- Metode --}}
                                            <flux:table.cell class="text-xs">
                                                <flux:badge size="sm" color="zinc">
                                                    {{ $lastPayment?->metode?->label() ?? $inv->metode_pembayaran?->label() ?? 'Lunas' }}
                                                </flux:badge>
                                            </flux:table.cell>

                                            {{-- Teller / Ref --}}
                                            <flux:table.cell class="text-xs">
                                                <div class="text-zinc-700 dark:text-zinc-300">
                                                    {{ $lastPayment?->dicatatOleh?->name ?? 'Sistem Gateway' }}
                                                </div>
                                                @if ($lastPayment?->referensi_transaksi)
                                                    <div class="font-mono text-[11px] text-zinc-400">Ref: {{ $lastPayment->referensi_transaksi }}</div>
                                                @endif
                                            </flux:table.cell>

                                            {{-- Jumlah --}}
                                            <flux:table.cell class="font-mono text-xs font-bold text-emerald-700 dark:text-emerald-400">
                                                {{ $inv->formattedJumlahSetelahPromo() }}
                                            </flux:table.cell>

                                            {{-- Promo --}}
                                            <flux:table.cell class="text-xs">
                                                @if ($inv->promo)
                                                    <flux:badge size="sm" color="emerald" class="text-[11px]">
                                                        {{ $inv->promo->kode_promo }}
                                                    </flux:badge>
                                                @else
                                                    <span class="text-zinc-400">—</span>
                                                @endif
                                            </flux:table.cell>

                                            {{-- Status --}}
                                            <flux:table.cell>
                                                <flux:badge size="sm" color="emerald">
                                                    Lunas
                                                </flux:badge>
                                            </flux:table.cell>

                                            {{-- Aksi --}}
                                            <flux:table.cell class="text-right">
                                                <flux:button
                                                    :href="route('invoice.show', $inv)"
                                                    wire:navigate
                                                    size="xs"
                                                    variant="ghost"
                                                    icon="arrow-top-right-on-square"
                                                    title="Lihat Kuitansi / Invoice"
                                                />
                                            </flux:table.cell>
                                        </flux:table.row>
                                    @endforeach
                                </flux:table.rows>
                            </flux:table>
                        </div>
                    @else
                        <div class="rounded-lg border-2 border-dashed border-zinc-200 p-8 text-center dark:border-zinc-800">
                            <flux:icon name="banknotes" class="mx-auto size-8 text-zinc-300 dark:text-zinc-600" />
                            <p class="mt-2 text-xs text-zinc-500">Belum ada riwayat invoice yang telah lunas.</p>
                        </div>
                    @endif
                </div>
            </div>

            {{-- 3. Riwayat Invoice Dihapus --}}
            <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <div class="flex items-center justify-between border-b border-zinc-100 pb-4 dark:border-zinc-800">
                    <div class="flex items-center gap-3">
                        <div class="flex size-9 items-center justify-center rounded-lg bg-zinc-100 text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400">
                            <flux:icon name="trash" class="size-5" />
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <flux:heading size="base">Riwayat Invoice Dihapus / Dibatalkan</flux:heading>
                                <span class="text-xs text-zinc-500 font-medium">({{ $invoicesDihapus->count() }} Data)</span>
                            </div>
                            <flux:description class="text-xs">Daftar invoice tagihan yang pernah dibatalkan atau dihapus dari sistem (audit trail).</flux:description>
                        </div>
                    </div>
                </div>

                <div class="mt-4">
                    @if ($invoicesDihapus->isNotEmpty())
                        <div class="overflow-x-auto">
                            <flux:table>
                                <flux:table.columns>
                                    <flux:table.column>No. Invoice</flux:table.column>
                                    <flux:table.column>Layanan</flux:table.column>
                                    <flux:table.column>Detail</flux:table.column>
                                    <flux:table.column>Tanggal</flux:table.column>
                                    <flux:table.column>Jumlah</flux:table.column>
                                    <flux:table.column>Dihapus Oleh</flux:table.column>
                                    <flux:table.column>Keterangan</flux:table.column>
                                </flux:table.columns>

                                <flux:table.rows>
                                    @foreach ($invoicesDihapus as $delInv)
                                        <flux:table.row :key="$delInv->id">
                                            {{-- No Invoice --}}
                                            <flux:table.cell class="font-mono text-xs font-medium text-zinc-500">
                                                {{ $delInv->no_invoice }}
                                            </flux:table.cell>

                                            {{-- Layanan --}}
                                            <flux:table.cell class="text-xs text-zinc-600 dark:text-zinc-400">
                                                {{ $delInv->layananPelanggan?->paketLayanan?->nama_paket ?? '—' }}
                                            </flux:table.cell>

                                            {{-- Detail --}}
                                            <flux:table.cell class="text-xs text-zinc-600 dark:text-zinc-400">
                                                {{ $delInv->formattedPeriodeTagihan() }}
                                            </flux:table.cell>

                                            {{-- Tanggal --}}
                                            <flux:table.cell class="text-xs text-zinc-500">
                                                <div>{{ $delInv->deleted_at?->translatedFormat('d M Y, H:i') ?? '—' }}</div>
                                            </flux:table.cell>

                                            {{-- Jumlah --}}
                                            <flux:table.cell class="font-mono text-xs text-zinc-600 dark:text-zinc-400">
                                                {{ $delInv->formattedJumlahSetelahPromo() }}
                                            </flux:table.cell>

                                            {{-- Dihapus Oleh --}}
                                            <flux:table.cell class="text-xs text-zinc-700 dark:text-zinc-300 font-medium">
                                                {{ $delInv->dihapusOleh?->name ?? 'Sistem' }}
                                            </flux:table.cell>

                                            {{-- Keterangan --}}
                                            <flux:table.cell class="text-xs text-zinc-500 italic max-w-xs truncate" title="{{ $delInv->keterangan_hapus }}">
                                                {{ $delInv->keterangan_hapus ?: '—' }}
                                            </flux:table.cell>
                                        </flux:table.row>
                                    @endforeach
                                </flux:table.rows>
                            </flux:table>
                        </div>
                    @else
                        <div class="rounded-lg border border-dashed border-zinc-200 p-6 text-center text-xs text-zinc-400 dark:border-zinc-800">
                            Tidak ada data invoice yang dihapus untuk pelanggan ini.
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif

    {{-- Tab 4: Dokumen & Legalitas --}}
    @if ($activeTab === 'dokumen')
        <div class="space-y-6">
            {{-- Toolbar & Actions --}}
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <flux:heading size="base">Dokumen Identitas & Legalitas</flux:heading>
                    <flux:subheading>Seluruh berkas disimpan terenkripsi di penyimpanan privat dan dilindungi tanda air dinamis (UU PDP).</flux:subheading>
                </div>

                <div class="flex items-center gap-2">
                    @can('update', $pelanggan)
                        <flux:button wire:click="openUploadKtpModal" variant="subtle" icon="identification">
                            {{ $ktpMedia ? 'Ganti Foto KTP' : 'Unggah Foto KTP' }}
                        </flux:button>
                    @endcan

                    @can('uploadDokumen', $pelanggan)
                        <flux:button wire:click="openUploadDocModal" variant="primary" icon="plus">
                            Unggah Dokumen Baru
                        </flux:button>
                    @endcan
                </div>
            </div>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                {{-- Card KTP Pelanggan --}}
                <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                    <div class="flex items-center justify-between pb-4 border-b border-zinc-100 dark:border-zinc-800">
                        <div class="flex items-center gap-2">
                            <div class="flex size-8 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600 dark:bg-indigo-950/50 dark:text-indigo-400">
                                <flux:icon name="identification" class="size-5" />
                            </div>
                            <div>
                                <flux:heading size="sm">Kartu Identitas (KTP)</flux:heading>
                                <flux:description class="text-xs">Foto identitas resmi</flux:description>
                            </div>
                        </div>

                        @if ($ktpMedia)
                            <flux:badge color="green" size="sm" icon="lock-closed">Terenkripsi</flux:badge>
                        @else
                            <flux:badge color="zinc" size="sm">Belum Ada</flux:badge>
                        @endif
                    </div>

                    <div class="mt-4 space-y-4">
                        @if ($ktpMedia)
                            {{-- Masked Preview Box --}}
                            <div class="relative overflow-hidden rounded-lg border border-zinc-200 bg-zinc-100 p-6 text-center dark:border-zinc-800 dark:bg-zinc-950/60">
                                <div class="flex flex-col items-center justify-center gap-2">
                                    <div class="flex size-12 items-center justify-center rounded-full bg-emerald-100 text-emerald-600 dark:bg-emerald-950/60 dark:text-emerald-400">
                                        <flux:icon name="shield-check" class="size-6" />
                                    </div>
                                    <span class="text-xs font-medium text-zinc-700 dark:text-zinc-300">Berkas KTP Tersimpan Aman</span>
                                    <span class="text-[11px] text-zinc-500 font-mono">{{ $ktpMedia->file_name }} ({{ number_format($ktpMedia->size / 1024, 1) }} KB)</span>
                                </div>
                            </div>

                            <div class="space-y-2">
                                @can('viewKtp', $pelanggan)
                                    <flux:button wire:click="openKtpModal" variant="primary" class="w-full" icon="eye">
                                        Buka Foto KTP (Watermarked)
                                    </flux:button>
                                @else
                                    <div class="rounded-lg bg-amber-50 p-2.5 text-center text-xs text-amber-700 dark:bg-amber-950/30 dark:text-amber-400">
                                        Anda tidak memiliki hak akses untuk melihat foto KTP.
                                    </div>
                                @endcan
                            </div>
                        @else
                            <div class="rounded-lg border-2 border-dashed border-zinc-200 p-6 text-center dark:border-zinc-800">
                                <flux:icon name="identification" class="mx-auto size-8 text-zinc-300 dark:text-zinc-600" />
                                <p class="mt-2 text-xs text-zinc-500">Belum ada foto KTP yang diunggah untuk pelanggan ini.</p>
                                @can('update', $pelanggan)
                                    <flux:button wire:click="openUploadKtpModal" variant="ghost" size="sm" class="mt-3" icon="arrow-up-tray">
                                        Unggah Sekarang
                                    </flux:button>
                                @endcan
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Kolom Tabel Dokumen Pendukung & MOU --}}
                <div class="lg:col-span-2 rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                    <div class="flex items-center justify-between pb-4 border-b border-zinc-100 dark:border-zinc-800">
                        <div class="flex items-center gap-2">
                            <div class="flex size-8 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600 dark:bg-emerald-950/50 dark:text-emerald-400">
                                <flux:icon name="document-text" class="size-5" />
                            </div>
                            <div>
                                <flux:heading size="sm">Dokumen MOU & Legalitas Pendukung</flux:heading>
                                <flux:description class="text-xs">Kontrak kerja sama, berita acara, dan berkas syarat administrasi</flux:description>
                            </div>
                        </div>

                        <span class="text-xs text-zinc-500 font-medium">{{ $dokumens->count() }} Dokumen</span>
                    </div>

                    <div class="mt-4">
                        @if ($dokumens->isNotEmpty())
                            <div class="overflow-x-auto">
                                <flux:table>
                                    <flux:table.columns>
                                        <flux:table.column>Dokumen</flux:table.column>
                                        <flux:table.column>Jenis</flux:table.column>
                                        <flux:table.column>Keterangan</flux:table.column>
                                        <flux:table.column>Diunggah</flux:table.column>
                                        <flux:table.column class="text-right">Aksi</flux:table.column>
                                    </flux:table.columns>

                                    <flux:table.rows>
                                        @foreach ($dokumens as $doc)
                                            @php
                                                $jenis = $doc->getCustomProperty('jenis_dokumen', 'Dokumen');
                                                $nomor = $doc->getCustomProperty('nomor_dokumen');
                                                $ket = $doc->getCustomProperty('keterangan');
                                                $uploader = $doc->getCustomProperty('uploaded_by', 'Staf');
                                                $isPdf = str_contains((string) $doc->mime_type, 'pdf');
                                            @endphp
                                            <flux:table.row :key="$doc->id">
                                                <flux:table.cell>
                                                    <div class="flex items-center gap-2">
                                                        <flux:icon :name="$isPdf ? 'document-text' : 'photo'" class="size-4 text-zinc-500 shrink-0" />
                                                        <div class="flex flex-col">
                                                            <span class="font-medium text-xs text-zinc-900 dark:text-zinc-100 truncate max-w-[180px]">{{ $doc->file_name }}</span>
                                                            <span class="text-[11px] text-zinc-400">{{ number_format($doc->size / 1024, 1) }} KB</span>
                                                        </div>
                                                    </div>
                                                </flux:table.cell>

                                                <flux:table.cell>
                                                    <flux:badge size="sm" :color="match ($jenis) {
                                                        'MOU / Kontrak' => 'indigo',
                                                        'Formulir Berlangganan' => 'emerald',
                                                        'Surat Kuasa' => 'amber',
                                                        'Berita Acara Pemasangan' => 'cyan',
                                                        default => 'zinc',
                                                    }">
                                                        {{ $jenis }}
                                                    </flux:badge>
                                                    @if ($nomor)
                                                        <span class="block text-[11px] font-mono text-zinc-500 mt-0.5">{{ $nomor }}</span>
                                                    @endif
                                                </flux:table.cell>

                                                <flux:table.cell class="text-xs text-zinc-600 dark:text-zinc-400">
                                                    {{ $ket ?: '—' }}
                                                </flux:table.cell>

                                                <flux:table.cell class="text-xs text-zinc-500">
                                                    <div>{{ $doc->created_at->translatedFormat('d M Y, H:i') }}</div>
                                                    <div class="text-[11px] text-zinc-400">Oleh: {{ $uploader }}</div>
                                                </flux:table.cell>

                                                <flux:table.cell class="text-right">
                                                    <div class="flex items-center justify-end gap-1.5">
                                                        @can('viewDokumen', $pelanggan)
                                                            <flux:button
                                                                :href="route('pelanggan.dokumen.stream', [$pelanggan, $doc])"
                                                                target="_blank"
                                                                variant="ghost"
                                                                size="sm"
                                                                icon="arrow-down-tray"
                                                                title="Lihat / Unduh Dokumen"
                                                            />
                                                        @endcan

                                                        @can('deleteDokumen', $pelanggan)
                                                            <flux:button
                                                                wire:click="deleteDokumen({{ $doc->id }})"
                                                                wire:confirm="Apakah Anda yakin ingin menghapus berkas dokumen {{ $doc->file_name }}?"
                                                                variant="ghost"
                                                                size="sm"
                                                                icon="trash"
                                                                class="text-red-500 hover:text-red-700"
                                                                title="Hapus Dokumen"
                                                            />
                                                        @endcan
                                                    </div>
                                                </flux:table.cell>
                                            </flux:table.row>
                                        @endforeach
                                    </flux:table.rows>
                                </flux:table>
                            </div>
                        @else
                            <div class="rounded-lg border-2 border-dashed border-zinc-200 p-8 text-center dark:border-zinc-800">
                                <flux:icon name="document-text" class="mx-auto size-8 text-zinc-300 dark:text-zinc-600" />
                                <p class="mt-2 text-xs text-zinc-500">Belum ada berkas MOU atau dokumen legalitas yang diunggah.</p>
                                @can('uploadDokumen', $pelanggan)
                                    <flux:button wire:click="openUploadDocModal" variant="ghost" size="sm" class="mt-3" icon="plus">
                                        Unggah Dokumen MOU
                                    </flux:button>
                                @endcan
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Tab 5: Riwayat Aktivitas (Activity Log) --}}
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

    {{-- ─── Modal 1: Preview KTP Terenkripsi dengan Dynamic Watermark ─── --}}
    @if ($showKtpModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-black/60 p-4 backdrop-blur-sm">
            <div class="relative w-full max-w-2xl rounded-2xl border border-zinc-200 bg-white p-6 shadow-2xl dark:border-zinc-700 dark:bg-zinc-900 space-y-4">
                <div class="flex items-center justify-between border-b border-zinc-100 pb-3 dark:border-zinc-800">
                    <div class="flex items-center gap-2">
                        <flux:icon name="identification" class="size-5 text-indigo-600 dark:text-indigo-400" />
                        <flux:heading size="lg">Foto KTP Pelanggan (Terenkripsi)</flux:heading>
                    </div>
                    <flux:button wire:click="closeKtpModal" variant="ghost" size="sm" icon="x-mark" />
                </div>

                {{-- Alert UU PDP Notice --}}
                <div class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-xs text-amber-800 dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-300">
                    <div class="flex items-center gap-2 font-semibold">
                        <flux:icon name="shield-exclamation" class="size-4 shrink-0" />
                        <span>Perlindungan Data Pribadi (UU PDP) & Tanda Air Digital</span>
                    </div>
                    <p class="mt-1 text-[11px] leading-relaxed">
                        Dokumen ini telah disematkan tanda air dinamis berisi identitas Anda (<strong>{{ auth()->user()->name }}</strong>) dan timestamp akses. Setiap aktivitas preview terekam dalam audit trail sistem.
                    </p>
                </div>

                {{-- Image Container --}}
                <div class="flex justify-center rounded-xl bg-zinc-950 p-2 shadow-inner">
                    <img
                        src="{{ route('pelanggan.ktp.preview', $pelanggan) }}"
                        alt="Foto KTP {{ $pelanggan->namaLengkap() }}"
                        class="max-h-[60vh] w-auto rounded-lg object-contain"
                    />
                </div>

                <div class="flex justify-end pt-2">
                    <flux:button wire:click="closeKtpModal" variant="primary">Tutup</flux:button>
                </div>
            </div>
        </div>
    @endif

    {{-- ─── Modal 2: Unggah / Ganti Foto KTP ─── --}}
    @if ($showUploadKtpModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-black/60 p-4 backdrop-blur-sm">
            <div class="relative w-full max-w-md rounded-2xl border border-zinc-200 bg-white p-6 shadow-2xl dark:border-zinc-700 dark:bg-zinc-900 space-y-4">
                <div class="flex items-center justify-between border-b border-zinc-100 pb-3 dark:border-zinc-800">
                    <flux:heading size="lg">{{ $ktpMedia ? 'Ganti Foto KTP' : 'Unggah Foto KTP' }}</flux:heading>
                    <flux:button wire:click="closeUploadKtpModal" variant="ghost" size="sm" icon="x-mark" />
                </div>

                <form wire:submit="saveKtp" class="space-y-4">
                    <flux:field>
                        <flux:label>Pilih Foto KTP Baru</flux:label>
                        <flux:input wire:model="newKtpFile" type="file" accept="image/jpeg,image/png,image/webp" />
                        <flux:description>Format: JPG, PNG, WEBP (Maksimal 5MB). Otomatis dienkripsi at-rest.</flux:description>
                        <flux:error name="newKtpFile" />
                    </flux:field>

                    @if ($newKtpFile)
                        <div class="rounded-lg border border-indigo-200 bg-indigo-50/50 p-2.5 text-xs text-indigo-700 dark:border-indigo-900/50 dark:bg-indigo-950/20 dark:text-indigo-400">
                            Berkas siap dienkripsi: <strong>{{ $newKtpFile->getClientOriginalName() }}</strong>
                        </div>
                    @endif

                    <div class="flex justify-end gap-2 pt-2">
                        <flux:button wire:click="closeUploadKtpModal" type="button" variant="ghost">Batal</flux:button>
                        <flux:button type="submit" variant="primary" icon="lock-closed">Simpan & Enkripsi</flux:button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- ─── Modal 3: Unggah Dokumen MOU / Pendukung ─── --}}
    @if ($showUploadDocModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-black/60 p-4 backdrop-blur-sm">
            <div class="relative w-full max-w-lg rounded-2xl border border-zinc-200 bg-white p-6 shadow-2xl dark:border-zinc-700 dark:bg-zinc-900 space-y-4">
                <div class="flex items-center justify-between border-b border-zinc-100 pb-3 dark:border-zinc-800">
                    <div class="flex items-center gap-2">
                        <flux:icon name="document-plus" class="size-5 text-emerald-600 dark:text-emerald-400" />
                        <flux:heading size="lg">Unggah Dokumen Legalitas / MOU</flux:heading>
                    </div>
                    <flux:button wire:click="closeUploadDocModal" variant="ghost" size="sm" icon="x-mark" />
                </div>

                <form wire:submit="saveDokumen" class="space-y-4">
                    <flux:field>
                        <flux:label>Berkas Dokumen</flux:label>
                        <flux:input wire:model="docFile" type="file" accept="application/pdf,image/jpeg,image/png,image/webp" />
                        <flux:description>Format: PDF, JPG, PNG, WEBP (Maks. 10MB). Disimpan terenkripsi.</flux:description>
                        <flux:error name="docFile" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Jenis Dokumen</flux:label>
                        <flux:select wire:model="docJenis">
                            <flux:select.option value="MOU / Kontrak">MOU / Kontrak Kerja Sama</flux:select.option>
                            <flux:select.option value="Formulir Berlangganan">Formulir Berlangganan</flux:select.option>
                            <flux:select.option value="Surat Kuasa">Surat Kuasa</flux:select.option>
                            <flux:select.option value="Berita Acara Pemasangan">Berita Acara Pemasangan</flux:select.option>
                            <flux:select.option value="Lainnya">Dokumen Lainnya</flux:select.option>
                        </flux:select>
                        <flux:error name="docJenis" />
                    </flux:field>

                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <flux:field>
                            <flux:label>Nomor Dokumen <span class="text-zinc-400 font-normal">(opsional)</span></flux:label>
                            <flux:input wire:model="docNomor" placeholder="MOU/2026/08/001" />
                            <flux:error name="docNomor" />
                        </flux:field>

                        <flux:field>
                            <flux:label>Keterangan <span class="text-zinc-400 font-normal">(opsional)</span></flux:label>
                            <flux:input wire:model="docKeterangan" placeholder="Keterangan singkat..." />
                            <flux:error name="docKeterangan" />
                        </flux:field>
                    </div>

                    <div class="flex justify-end gap-2 pt-2">
                        <flux:button wire:click="closeUploadDocModal" type="button" variant="ghost">Batal</flux:button>
                        <flux:button type="submit" variant="primary" icon="lock-closed">Unggah & Enkripsi</flux:button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- ─── Modal 4: Modal Pencatatan Pembayaran Cepat Tagihan Aktif ─── --}}
    @if ($showBayarModal && $selectedInvoice)
        <div class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-black/60 p-4 backdrop-blur-sm">
            <div class="relative w-full max-w-lg rounded-2xl border border-zinc-200 bg-white p-6 shadow-2xl dark:border-zinc-700 dark:bg-zinc-900 space-y-5">
                <div class="flex items-center justify-between border-b border-zinc-100 pb-3 dark:border-zinc-800">
                    <div class="flex items-center gap-2">
                        <div class="flex size-8 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600 dark:bg-emerald-950 dark:text-emerald-400">
                            <flux:icon name="banknotes" class="size-5" />
                        </div>
                        <div>
                            <flux:heading size="lg">Catat Pembayaran Tagihan</flux:heading>
                            <flux:description class="text-xs">Invoice: <strong class="font-mono text-zinc-900 dark:text-zinc-100">{{ $selectedInvoice->no_invoice }}</strong></flux:description>
                        </div>
                    </div>
                    <flux:button wire:click="closeBayarModal" variant="ghost" size="sm" icon="x-mark" />
                </div>

                {{-- Summary Box --}}
                <div class="rounded-xl bg-zinc-50 p-4 text-xs dark:bg-zinc-800/60 space-y-2">
                    <div class="flex justify-between">
                        <span class="text-zinc-500">Layanan & Paket:</span>
                        <span class="font-semibold text-zinc-800 dark:text-zinc-200">{{ $selectedInvoice->layananPelanggan?->paketLayanan?->nama_paket ?? 'Layanan Internet' }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-zinc-500">Periode Tagihan:</span>
                        <span class="font-medium text-zinc-700 dark:text-zinc-300">{{ $selectedInvoice->formattedPeriodeTagihan() }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-zinc-500">Jatuh Tempo:</span>
                        <span class="text-zinc-700 dark:text-zinc-300">{{ $selectedInvoice->tanggal_jatuh_tempo->format('d F Y') }}</span>
                    </div>
                    <div class="flex justify-between border-t border-zinc-200/60 pt-2 dark:border-zinc-700/60">
                        <span class="font-semibold text-zinc-700 dark:text-zinc-300">Total Harus Dibayar:</span>
                        <span class="font-mono text-sm font-bold text-primary-600 dark:text-primary-400">{{ $selectedInvoice->formattedJumlahSetelahPromo() }}</span>
                    </div>
                </div>

                <form wire:submit="prosesBayarInvoice" class="space-y-4">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <flux:field>
                            <flux:label>Metode Pembayaran</flux:label>
                            <flux:select wire:model="bayarMetode">
                                <flux:select.option value="manual_admin">Tunai / Kasir (Admin)</flux:select.option>
                                <flux:select.option value="transfer">Transfer Bank Manual</flux:select.option>
                            </flux:select>
                            <flux:error name="bayarMetode" />
                        </flux:field>

                        <flux:field>
                            <flux:label>Jumlah Dibayar (Rp)</flux:label>
                            <flux:input wire:model="bayarJumlah" type="number" step="1000" />
                            <flux:error name="bayarJumlah" />
                        </flux:field>
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <flux:field>
                            <flux:label>Tanggal Pembayaran</flux:label>
                            <flux:input wire:model="bayarTanggal" type="datetime-local" />
                            <flux:error name="bayarTanggal" />
                        </flux:field>

                        <flux:field>
                            <flux:label>Referensi / No. Struk <span class="text-zinc-400 font-normal">(opsional)</span></flux:label>
                            <flux:input wire:model="bayarReferensi" placeholder="Contoh: TRX-12345" />
                            <flux:error name="bayarReferensi" />
                        </flux:field>
                    </div>

                    <flux:field>
                        <flux:label>Catatan Petugas <span class="text-zinc-400 font-normal">(opsional)</span></flux:label>
                        <flux:input wire:model="bayarCatatan" placeholder="Catatan internal..." />
                        <flux:error name="bayarCatatan" />
                    </flux:field>

                    <div class="flex justify-end gap-2 pt-2">
                        <flux:button wire:click="closeBayarModal" type="button" variant="ghost">Batal</flux:button>
                        <flux:button type="submit" variant="primary" icon="check">Simpan & Lunasi</flux:button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
