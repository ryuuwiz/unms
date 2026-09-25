<div class="space-y-6" wire:init="loadPppStatuses">
    {{--
        wire:init diletakkan di root component (bukan di dalam blok tab "subscriptions")
        karena tab tersebut hanya dirender saat $activeTab === 'subscriptions'. Kalau
        wire:init dipasang di dalam blok @if yang tidak aktif saat render awal, ia
        tidak akan pernah terpicu untuk pengguna yang mendarat di tab lain.
    --}}
    @php
        $card = 'rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900';
        $hasCoord = is_numeric($pelanggan->latitude) && is_numeric($pelanggan->longitude);
        $totalBelumDibayar = $invoicesAktif->sum('jumlah_setelah_promo');
        $layananAktif = $pelanggan->layanans->filter(fn ($l) => $l->status->value === 'aktif')->count();
        $tabs = [
            'overview' => ['icon' => 'user', 'label' => 'Informasi & Lokasi', 'count' => null],
            'subscriptions' => ['icon' => 'rss', 'label' => 'Layanan Internet', 'count' => $pelanggan->layanans->count() ?: null],
            'billing' => ['icon' => 'banknotes', 'label' => 'Tagihan & Pembayaran', 'count' => $invoicesAktif->count() ?: null, 'alert' => true],
            'dokumen' => ['icon' => 'document-duplicate', 'label' => 'Dokumen & Legalitas', 'count' => ($dokumens->count() + ($ktpMedia ? 1 : 0)) ?: null],
            'audit' => ['icon' => 'clock', 'label' => 'Riwayat Aktivitas', 'count' => $activityLogs->count() ?: null],
        ];
        if ($bisaLihatTiket) {
            $tabs = array_merge(array_slice($tabs, 0, 4, true), ['tiket' => ['icon' => 'ticket', 'label' => 'Riwayat Tiket', 'count' => $jumlahTiketTerbuka ?: null, 'alert' => true]], array_slice($tabs, 4, null, true));
        }
    @endphp

    {{-- Header --}}
    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div class="flex min-w-0 items-start gap-3">
            <flux:button :href="route('pelanggan.index')" wire:navigate variant="ghost" icon="arrow-left" aria-label="Kembali ke daftar pelanggan" />
            <div class="min-w-0">
                <flux:heading size="xl" class="break-words">{{ $pelanggan->namaLengkap() }}</flux:heading>
                <div class="mt-2 flex flex-wrap items-center gap-2">
                    <flux:badge size="sm" color="zinc" class="font-mono">{{ $pelanggan->no_reg }}</flux:badge>
                    <flux:badge size="sm" :color="$pelanggan->status->color()">{{ $pelanggan->status->label() }}</flux:badge>
                    <flux:badge size="sm" :color="$pelanggan->tipe_pelanggan->value === 'bisnis' ? 'purple' : 'zinc'">{{ $pelanggan->tipe_pelanggan->label() }}</flux:badge>
                </div>
                <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">
                    Didaftarkan {{ $pelanggan->created_at?->translatedFormat('d F Y, H:i') ?? '—' }} oleh {{ $pelanggan->pembuat?->name ?? 'Sistem' }}
                </p>
            </div>
        </div>

        <div class="flex flex-wrap gap-2">
            @canImpersonate
                @if ($pelanggan->akunPelanggan && $pelanggan->akunPelanggan->canBeImpersonated())
                    <flux:button :href="route('impersonate', ['id' => $pelanggan->akunPelanggan->id, 'guardName' => 'pelanggan'])" variant="subtle" icon="arrow-right-end-on-rectangle" class="flex-1 sm:flex-none">
                        Buka Portal (Login as)
                    </flux:button>
                @endif
            @endCanImpersonate
            @can('update', $pelanggan)
                <flux:button :href="route('pelanggan.edit', $pelanggan)" wire:navigate variant="primary" icon="pencil-square" class="flex-1 sm:flex-none">
                    Edit Pelanggan
                </flux:button>
            @endcan
        </div>
    </div>

    {{-- Tabs: scroll horizontal di layar sempit --}}
    <div class="relative border-b border-zinc-200 dark:border-zinc-700">
        <div class="pointer-events-none absolute inset-y-0 right-0 z-10 w-8 bg-gradient-to-l from-white sm:hidden dark:from-zinc-800"></div>
        <nav wire:key="tabs-{{ $activeTab }}" x-init="$el.querySelector('[aria-current]')?.scrollIntoView({ inline: 'center', block: 'nearest' })"
            class="-mb-px flex gap-2 overflow-x-auto whitespace-nowrap pr-8 [scrollbar-width:none] sm:gap-6 sm:pr-0" aria-label="Tabs">
            @foreach ($tabs as $key => $tab)
                <button type="button" wire:click="setTab('{{ $key }}')" @if ($activeTab === $key) aria-current="page" @endif
                    class="flex min-h-12 items-center gap-2 border-b-2 px-2 text-sm font-medium transition-colors sm:px-0 sm:text-base {{ $activeTab === $key ? 'border-primary-600 text-primary-600 dark:border-primary-400 dark:text-primary-400' : 'border-transparent text-zinc-500 hover:border-zinc-300 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200' }}">
                    <flux:icon :name="$tab['icon']" class="size-5" />
                    {{ $tab['label'] }}
                    @if ($tab['count'])
                        <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ ($tab['alert'] ?? false) ? 'bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-300' : 'bg-zinc-200 text-zinc-700 dark:bg-zinc-700 dark:text-zinc-300' }}">{{ $tab['count'] }}</span>
                    @endif
                </button>
            @endforeach
        </nav>
    </div>

    {{-- ═══════════ Tab 1: Informasi & Lokasi ═══════════ --}}
    @if ($activeTab === 'overview')
        @php
            $navUrl = $hasCoord ? "https://www.google.com/maps/dir/?api=1&destination={$pelanggan->latitude},{$pelanggan->longitude}" : null;
            $wilayah = $pelanggan->perumahan
                ? collect([
                    $pelanggan->perumahan->nama_perumahan,
                    'Kel. ' . ($pelanggan->perumahan->kelurahan?->nama_kelurahan ?? '—'),
                    'Kec. ' . ($pelanggan->perumahan->kelurahan?->kecamatan?->nama_kecamatan ?? '—'),
                    $pelanggan->perumahan->kelurahan?->kecamatan?->kota?->nama_kota,
                ])->filter()->implode(' · ')
                : null;
            $quick = 'flex min-h-16 flex-col items-center justify-center gap-1 rounded-xl border p-3 text-sm font-medium transition';
            $row = 'flex flex-col gap-1 px-4 py-3 sm:flex-row sm:items-center sm:justify-between sm:gap-6 sm:px-6';
            $dt = 'text-sm text-zinc-500 dark:text-zinc-400';
            $dd = 'text-base text-zinc-900 break-words sm:text-right dark:text-zinc-100';
        @endphp

        <div class="space-y-6">
            {{-- Aksi cepat --}}
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <a href="tel:{{ $pelanggan->no_hp }}" class="{{ $quick }} border-zinc-200 bg-white text-zinc-800 hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100 dark:hover:bg-zinc-800">
                    <flux:icon name="phone" class="size-6" /> Telepon
                </a>
                <a href="https://wa.me/{{ $pelanggan->no_hp }}" target="_blank" rel="noopener noreferrer" class="{{ $quick }} border-emerald-200 bg-emerald-50 text-emerald-800 hover:bg-emerald-100 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-300">
                    <flux:icon name="chat-bubble-left-right" class="size-6" /> WhatsApp
                </a>
                @if ($navUrl)
                    <a href="{{ $navUrl }}" target="_blank" rel="noopener noreferrer" class="{{ $quick }} border-sky-200 bg-sky-50 text-sky-800 hover:bg-sky-100 dark:border-sky-900 dark:bg-sky-950/40 dark:text-sky-300">
                        <flux:icon name="map" class="size-6" /> Navigasi
                    </a>
                @endif
                @can('update', $pelanggan)
                    <a href="{{ route('pelanggan.edit', $pelanggan) }}" wire:navigate class="{{ $quick }} border-zinc-200 bg-white text-zinc-800 hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100 dark:hover:bg-zinc-800">
                        <flux:icon name="pencil-square" class="size-6" /> Edit
                    </a>
                @endcan
            </div>

            {{-- Lokasi: peta + kartu alamat berdampingan di desktop (bukan ditumpuk di atas peta, agar
                 tidak menutupi kontrol zoom/marker), kartu di bawah peta pada layar kecil. --}}
            <section aria-labelledby="judul-lokasi" class="grid grid-cols-1 gap-3 {{ $hasCoord ? 'xl:grid-cols-3' : '' }}">
                @if ($hasCoord)
                    <div class="xl:col-span-2">
                        <x-map-view :lat="$pelanggan->latitude" :lng="$pelanggan->longitude" :popup-title="$pelanggan->namaLengkap()" :popup-subtitle="$pelanggan->alamat_lengkap" height="clamp(300px, 55vh, 560px)" />
                    </div>
                @else
                    <div class="flex min-h-64 flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed border-zinc-300 p-6 text-center dark:border-zinc-700">
                        <flux:icon name="map-pin" class="size-8 text-zinc-400" />
                        <span class="text-base font-medium text-zinc-700 dark:text-zinc-300">Koordinat belum ditentukan</span>
                        <span class="max-w-md text-sm text-zinc-500">Tentukan titik koordinat agar teknisi mudah menemukan lokasi pemasangan.</span>
                        @can('update', $pelanggan)
                            <flux:button :href="route('pelanggan.edit', $pelanggan)" wire:navigate variant="primary" icon="pencil-square" class="mt-2">Atur Titik Koordinat</flux:button>
                        @endcan
                    </div>
                @endif

                <div class="{{ $card }} p-4 sm:p-5">
                    <h2 id="judul-lokasi" class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Lokasi pemasangan</h2>
                    <p class="mt-1 text-base font-semibold text-zinc-900 dark:text-zinc-50">{{ $pelanggan->alamat_lengkap }}</p>
                    @if ($wilayah)
                        <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ $wilayah }}</p>
                    @endif
                    @if ($hasCoord)
                        <p class="mt-1 font-mono text-sm text-zinc-500 dark:text-zinc-400">
                            {{ number_format((float) $pelanggan->latitude, 7, '.', '') }}, {{ number_format((float) $pelanggan->longitude, 7, '.', '') }}
                        </p>
                        <div class="mt-4 flex flex-wrap gap-2">
                            <flux:button :href="$navUrl" target="_blank" variant="primary" icon="map" class="flex-1">Navigasi</flux:button>
                            <flux:button :href="route('maps.estimasi-kabel', ['lat' => $pelanggan->latitude, 'lng' => $pelanggan->longitude])" wire:navigate icon="calculator" class="flex-1">Estimasi kabel</flux:button>
                        </div>
                        @can('update', $pelanggan)
                            <a href="{{ route('pelanggan.edit', $pelanggan) }}" wire:navigate class="mt-3 inline-flex min-h-11 items-center text-sm font-medium text-primary-600 hover:underline dark:text-primary-400">Ubah titik lokasi →</a>
                        @endcan
                    @endif
                </div>
            </section>

            {{-- Ringkasan --}}
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                <button type="button" wire:click="setTab('subscriptions')" class="{{ $card }} p-4 text-left hover:bg-zinc-50 dark:hover:bg-zinc-800">
                    <div class="text-sm text-zinc-500 dark:text-zinc-400">Layanan aktif</div>
                    <div class="mt-1 text-2xl font-semibold text-zinc-900 dark:text-zinc-50">{{ $layananAktif }} <span class="text-base font-normal text-zinc-500">/ {{ $pelanggan->layanans->count() }}</span></div>
                </button>
                <button type="button" wire:click="setTab('billing')" class="rounded-xl border p-4 text-left {{ $totalBelumDibayar > 0 ? 'border-rose-200 bg-rose-50 hover:bg-rose-100 dark:border-rose-900 dark:bg-rose-950/40' : 'border-zinc-200 bg-white hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 dark:hover:bg-zinc-800' }}">
                    <div class="text-sm text-zinc-500 dark:text-zinc-400">Belum dibayar</div>
                    <div class="mt-1 text-2xl font-semibold {{ $totalBelumDibayar > 0 ? 'text-rose-700 dark:text-rose-300' : 'text-zinc-900 dark:text-zinc-50' }}">Rp {{ number_format($totalBelumDibayar, 0, ',', '.') }}</div>
                </button>
                <div class="{{ $card }} p-4">
                    <div class="text-sm text-zinc-500 dark:text-zinc-400">Kode pembayaran</div>
                    <div class="mt-1 break-all font-mono text-2xl font-semibold text-zinc-900 dark:text-zinc-50">{{ $pelanggan->kode_pembayaran }}</div>
                </div>
            </div>

            {{-- Detail: progressive disclosure --}}
            <details class="{{ $card }} group" open>
                <summary class="flex min-h-14 cursor-pointer list-none items-center justify-between px-4 text-base font-semibold text-zinc-900 sm:px-6 dark:text-zinc-50">
                    Kontak & identitas
                    <flux:icon name="chevron-down" class="size-5 text-zinc-400 transition group-open:rotate-180" />
                </summary>
                <dl class="divide-y divide-zinc-100 border-t border-zinc-100 dark:divide-zinc-800 dark:border-zinc-800">
                    <div class="{{ $row }}">
                        <dt class="{{ $dt }}">No. HP / WhatsApp</dt>
                        <dd class="{{ $dd }}">{{ $pelanggan->no_hp }}</dd>
                    </div>
                    @foreach (['Email' => $pelanggan->email ?: '—', 'Telepon rumah' => $pelanggan->telepon_rumah ?: '—', 'No. registrasi' => $pelanggan->no_reg] as $label => $value)
                        <div class="{{ $row }}">
                            <dt class="{{ $dt }}">{{ $label }}</dt>
                            <dd class="{{ $dd }}">{{ $value }}</dd>
                        </div>
                    @endforeach
                    <div class="{{ $row }}">
                        <dt class="{{ $dt }}">NIK</dt>
                        <dd class="{{ $dd }} flex items-center gap-2 font-mono sm:justify-end">
                            @if ($pelanggan->nik)
                                <span>{{ $showNik ? $pelanggan->nik : substr($pelanggan->nik, 0, 4) . '••••••••' . substr($pelanggan->nik, -4) }}</span>
                                <flux:button type="button" wire:click="toggleShowNik" variant="ghost" size="sm" :icon="$showNik ? 'eye-slash' : 'eye'" :aria-label="$showNik ? 'Sembunyikan NIK' : 'Tampilkan NIK'" />
                            @else
                                <span class="text-zinc-400">—</span>
                            @endif
                        </dd>
                    </div>
                </dl>
            </details>

            <details class="{{ $card }} group">
                <summary class="flex min-h-14 cursor-pointer list-none items-center justify-between px-4 text-base font-semibold text-zinc-900 sm:px-6 dark:text-zinc-50">
                    Detail alamat
                    <flux:icon name="chevron-down" class="size-5 text-zinc-400 transition group-open:rotate-180" />
                </summary>
                <dl class="divide-y divide-zinc-100 border-t border-zinc-100 dark:divide-zinc-800 dark:border-zinc-800">
                    @foreach ([
                        'RT / RW' => ($pelanggan->rt ?? '-') . ' / ' . ($pelanggan->rw ?? '-'),
                        'No. rumah' => $pelanggan->no_rumah ?? '—',
                        'Kode pos' => $pelanggan->kode_pos ?? '—',
                        'Perumahan / cluster' => $pelanggan->perumahan?->nama_perumahan ?? '—',
                        'Wilayah' => $wilayah ?? '—',
                    ] as $label => $value)
                        <div class="{{ $row }}">
                            <dt class="{{ $dt }}">{{ $label }}</dt>
                            <dd class="{{ $dd }}">{{ $value }}</dd>
                        </div>
                    @endforeach
                </dl>
            </details>

            <details class="{{ $card }} group">
                <summary class="flex min-h-14 cursor-pointer list-none items-center justify-between px-4 text-base font-semibold text-zinc-900 sm:px-6 dark:text-zinc-50">
                    Akun Portal Pelanggan
                    <flux:icon name="chevron-down" class="size-5 text-zinc-400 transition group-open:rotate-180" />
                </summary>
                <dl class="divide-y divide-zinc-100 border-t border-zinc-100 dark:divide-zinc-800 dark:border-zinc-800">
                    <div class="{{ $row }}">
                        <dt class="{{ $dt }}">Username (email)</dt>
                        <dd class="{{ $dd }}">{{ $pelanggan->akunPelanggan?->email ?? $pelanggan->email ?? '— (belum didaftarkan)' }}</dd>
                    </div>
                    <div class="{{ $row }}">
                        <dt class="{{ $dt }}">Status</dt>
                        <dd class="{{ $dd }}">
                            <flux:badge size="sm" :color="$pelanggan->akunPelanggan ? 'emerald' : 'amber'">{{ $pelanggan->akunPelanggan ? 'Aktif & terdaftar' : 'Belum ada akun' }}</flux:badge>
                        </dd>
                    </div>
                    <div class="{{ $row }}">
                        <dt class="{{ $dt }}">Password</dt>
                        <dd class="{{ $dd }} flex flex-wrap items-center gap-3 sm:justify-end">
                            <span class="font-mono">Default: 12345678</span>
                            @can('update', $pelanggan)
                                <flux:button type="button" size="sm" wire:click="resetPasswordPortal"
                                    wire:confirm="Apakah Anda yakin ingin mereset password akun portal pelanggan ini ke default (12345678)?">
                                    Reset Password
                                </flux:button>
                            @endcan
                        </dd>
                    </div>
                </dl>
            </details>

            <details class="{{ $card }} group">
                <summary class="flex min-h-14 cursor-pointer list-none items-center justify-between px-4 text-base font-semibold text-zinc-900 sm:px-6 dark:text-zinc-50">
                    Informasi registrasi
                    <flux:icon name="chevron-down" class="size-5 text-zinc-400 transition group-open:rotate-180" />
                </summary>
                <dl class="divide-y divide-zinc-100 border-t border-zinc-100 dark:divide-zinc-800 dark:border-zinc-800">
                    @foreach ([
                        'Didaftarkan oleh' => $pelanggan->pembuat?->name ?? 'Sistem',
                        'Waktu dibuat' => $pelanggan->created_at?->translatedFormat('d F Y, H:i') ?? '—',
                        'Terakhir diperbarui' => $pelanggan->updated_at?->translatedFormat('d F Y, H:i') ?? '—',
                    ] as $label => $value)
                        <div class="{{ $row }}">
                            <dt class="{{ $dt }}">{{ $label }}</dt>
                            <dd class="{{ $dd }}">{{ $value }}</dd>
                        </div>
                    @endforeach
                </dl>
            </details>
        </div>
    @endif

    {{-- ═══════════ Tab 2: Layanan Internet ═══════════ --}}
    @if ($activeTab === 'subscriptions')
        <div class="space-y-6" wire:poll.10s.visible="loadPppStatuses">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <flux:heading size="lg">Layanan internet</flux:heading>
                    <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Router, paket, dan status sesi PPP realtime dari MikroTik.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <flux:button type="button" wire:click="refreshPppStatus" variant="subtle" icon="arrow-path" wire:loading.attr="disabled" class="flex-1 sm:flex-none">
                        <span wire:loading.remove wire:target="refreshPppStatus">Segarkan Status PPP</span>
                        <span wire:loading wire:target="refreshPppStatus">Menghubungi Router...</span>
                    </flux:button>
                    @can('create', App\Models\LayananPelanggan::class)
                        <flux:button :href="route('layanan-pelanggan.create', $pelanggan)" wire:navigate variant="primary" icon="plus" class="flex-1 sm:flex-none">
                            Tambah Registrasi Billing
                        </flux:button>
                    @endcan
                </div>
            </div>

            @forelse ($pelanggan->layanans as $layanan)
                @php
                    $statusPpp = $pppStatuses[$layanan->id] ?? null;
                    $isConnected = $statusPpp['is_connected'] ?? false;
                    $isDisabled = $statusPpp['is_disabled'] ?? ($layanan->status->value === 'isolir' || $layanan->status->value === 'nonaktif');
                    $routerOnline = $statusPpp['router_online'] ?? true;
                @endphp
                <article id="layanan-{{ $layanan->id }}" class="{{ $card }} scroll-mt-24">
                    <header class="flex flex-col gap-4 p-4 sm:p-6 lg:flex-row lg:items-start lg:justify-between">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-50">{{ $layanan->label_layanan ?: $layanan->paketLayanan->nama_paket }}</h3>
                                <flux:badge size="sm" :color="$layanan->statusBadgeColor()">{{ $layanan->statusBadgeLabel() }}</flux:badge>
                            </div>
                            <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-zinc-500 dark:text-zinc-400">
                                <span class="font-mono">{{ $layanan->site_id }}</span>
                                @if ($layanan->nama_site)
                                    <span class="inline-flex items-center gap-1"><flux:icon name="map-pin" class="size-4" />{{ $layanan->nama_site }}</span>
                                @endif
                                @if ($layanan->alamat_pemasangan)
                                    <span>Titik pasang: {{ $layanan->alamat_pemasangan }}</span>
                                @endif
                            </div>
                            <div class="mt-3 flex flex-wrap gap-2">
                                <span wire:loading wire:target="loadPppStatuses" class="contents">
                                    <flux:badge color="zinc"><span class="mr-1.5 inline-block size-2 animate-pulse rounded-full bg-zinc-400"></span>Memuat Status...</flux:badge>
                                </span>
                                <span wire:loading.remove wire:target="loadPppStatuses" class="contents">
                                    @if ($isConnected)
                                        <flux:badge color="emerald"><span class="mr-1.5 inline-block size-2 rounded-full bg-emerald-500"></span>Connected (Online)</flux:badge>
                                    @else
                                        <flux:badge color="zinc"><span class="mr-1.5 inline-block size-2 rounded-full bg-zinc-400"></span>Disconnected (Offline)</flux:badge>
                                    @endif
                                </span>
                                @if ($isDisabled)
                                    <flux:badge color="rose">Terisolir / Disabled</flux:badge>
                                @endif
                            </div>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            @if ($layanan->status->value === 'proses' && ! $layanan->router_id)
                                @can('create', App\Models\Ticket::class)
                                    <flux:button :href="route('ticket.create', ['jenis' => 'pemasangan', 'pelanggan_id' => $pelanggan->id, 'layanan_id' => $layanan->id])" wire:navigate size="sm" variant="primary" icon="wrench-screwdriver">
                                        Buat Ticket Pemasangan
                                    </flux:button>
                                @endcan
                            @endif
                            @can('update', $layanan)
                                <flux:button wire:click="openUbahPaketModal({{ $layanan->id }})" size="sm" variant="outline" icon="arrows-up-down">Ubah Paket</flux:button>
                                <flux:button :href="route('layanan-pelanggan.edit', $layanan)" wire:navigate size="sm" variant="ghost" icon="pencil-square">Edit</flux:button>
                            @endcan
                        </div>
                    </header>

                    @if (! $routerOnline)
                        <div class="mx-4 mb-4 flex items-center gap-2 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-800 sm:mx-6 dark:bg-amber-950/40 dark:text-amber-300">
                            <flux:icon name="exclamation-triangle" class="size-5 shrink-0" />
                            Router tidak dapat dihubungi
                        </div>
                    @endif

                    <dl class="grid grid-cols-1 gap-x-6 gap-y-5 border-t border-zinc-100 px-4 py-5 sm:grid-cols-2 sm:px-6 lg:grid-cols-4 dark:border-zinc-800">
                        <div>
                            <dt class="text-sm text-zinc-500 dark:text-zinc-400">Router</dt>
                            <dd class="mt-1 text-base font-medium text-zinc-900 dark:text-zinc-100">
                                @if ($layanan->router)
                                    @can('view', $layanan->router)
                                        <a href="{{ route('router.edit', $layanan->router) }}" wire:navigate class="text-primary-600 hover:underline dark:text-primary-400">{{ $layanan->router->nama_router }}</a>
                                    @else
                                        {{ $layanan->router->nama_router }}
                                    @endcan
                                    <span class="block font-mono text-sm font-normal text-zinc-500">{{ $layanan->router->ip_address }}:{{ $layanan->router->port }}</span>
                                @else
                                    <span class="font-normal text-zinc-400">Belum diaktivasi</span>
                                @endif
                            </dd>
                        </div>
                        <div>
                            <dt class="text-sm text-zinc-500 dark:text-zinc-400">Paket</dt>
                            <dd class="mt-1 text-base font-medium text-zinc-900 dark:text-zinc-100">
                                {{ $layanan->paketLayanan->nama_paket }}
                                <span class="block text-sm font-normal text-zinc-500">
                                    {{ $layanan->paketLayanan->profilBandwidth?->labelKecepatan() ?? '-' }} · Rp {{ number_format($layanan->total_tarif, 0, ',', '.') }}/bln
                                </span>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-sm text-zinc-500 dark:text-zinc-400">Username PPP</dt>
                            <dd class="mt-1 break-all font-mono text-base font-semibold text-zinc-900 dark:text-zinc-100">
                                {{ $layanan->ppp_username ?? 'Belum diaktivasi' }}
                                <span class="flex items-center gap-1 text-sm font-normal text-zinc-500">
                                    @if ($revealedPppPasswordLayananId === $layanan->id)
                                        Pass: {{ $revealedPppPasswordValue }}
                                    @else
                                        Pass: ••••••••
                                        @can('viewPppPassword', $layanan)
                                            <flux:button type="button" size="xs" variant="ghost" icon="eye" wire:click="revealPppPassword({{ $layanan->id }})" wire:loading.attr="disabled" wire:target="revealPppPassword({{ $layanan->id }})" aria-label="Tampilkan password PPP" />
                                        @endcan
                                    @endif
                                </span>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-sm text-zinc-500 dark:text-zinc-400">Jatuh tempo</dt>
                            <dd class="mt-1 text-base font-medium text-zinc-900 dark:text-zinc-100">{{ $layanan->tanggal_expired?->translatedFormat('d M Y') ?? '—' }}</dd>
                        </div>
                    </dl>

                    <details class="group border-t border-zinc-100 dark:border-zinc-800">
                        <summary class="flex min-h-12 cursor-pointer list-none items-center justify-between px-4 text-sm font-medium text-zinc-700 sm:px-6 dark:text-zinc-300">
                            Detail sesi PPP & jaringan
                            <flux:icon name="chevron-down" class="size-5 text-zinc-400 transition group-open:rotate-180" />
                        </summary>
                        <dl class="grid grid-cols-2 gap-x-6 gap-y-4 px-4 pb-5 sm:grid-cols-3 sm:px-6 lg:grid-cols-4">
                            @foreach ([
                                'Profile' => $statusPpp['profile'] ?? $layanan->paketLayanan->profilBandwidth?->pppProfileName($layanan->profilePool()) ?? '—',
                                'Service' => strtoupper($statusPpp['service'] ?? $layanan->jenis_koneksi?->value ?? 'pppoe'),
                                'Uptime' => $statusPpp['uptime'] ?? '—',
                                'Terakhir logout' => $statusPpp['last_logged_out'] ?? '—',
                                'Gateway (local IP)' => $statusPpp['local_address'] ?? $layanan->resolveLocalAddress() ?? $layanan->ipPool?->getGatewayAddress() ?? '—',
                                'IP Publik Dedicated' => $layanan->ipPublikAktif()?->alamat_ip ?? '—',
                                'Caller ID (MAC ONT)' => $statusPpp['caller_id'] ?? '—',
                                'Port ODP' => ($layanan->odpPort?->odp?->nama_odp ?? '—') . ' (Port ' . ($layanan->odpPort?->nomor_port ?? '—') . ')',
                                'Tanggal mulai' => $layanan->tanggal_mulai->translatedFormat('d M Y'),
                                'Auto isolir' => $layanan->auto_isolir ? 'Aktif' : 'Nonaktif',
                            ] as $label => $value)
                                <div>
                                    <dt class="text-sm text-zinc-500 dark:text-zinc-400">{{ $label }}</dt>
                                    <dd class="mt-1 break-all font-mono text-sm text-zinc-900 dark:text-zinc-100">{{ $value }}</dd>
                                </div>
                            @endforeach
                            <div>
                                <dt class="text-sm text-zinc-500 dark:text-zinc-400">IP remote (ONT)</dt>
                                <dd class="mt-1 font-mono text-sm">
                                    @if ($isConnected && ! empty($statusPpp['ip_address']))
                                        <a href="http://{{ $statusPpp['ip_address'] }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1 font-semibold text-primary-600 hover:underline dark:text-primary-400">
                                            {{ $statusPpp['ip_address'] }} <flux:icon name="arrow-top-right-on-square" class="size-4" />
                                        </a>
                                    @elseif (! $isConnected)
                                        <span class="text-rose-700 dark:text-rose-300">Belum tersambung (Offline)</span>
                                        @if ($layanan->resolveRemoteAddress())
                                            <span class="block text-zinc-500">Target statis: {{ $layanan->resolveRemoteAddress() }}</span>
                                        @endif
                                    @else
                                        <span class="text-zinc-400">—</span>
                                    @endif
                                </dd>
                            </div>
                            <div>
                                <dt class="text-sm text-zinc-500 dark:text-zinc-400">Secret PPP</dt>
                                <dd class="mt-1">
                                    <flux:badge size="sm" :color="$isDisabled ? 'rose' : 'emerald'">{{ $isDisabled ? 'Disabled (isolir)' : 'Enabled' }}</flux:badge>
                                </dd>
                            </div>
                        </dl>
                    </details>
                </article>
            @empty
                <div class="rounded-xl border-2 border-dashed border-zinc-300 p-8 text-center sm:p-12 dark:border-zinc-700">
                    <flux:icon name="rss" class="mx-auto size-8 text-zinc-400" />
                    <p class="mt-2 text-base font-medium text-zinc-700 dark:text-zinc-300">Belum ada layanan</p>
                    <p class="mt-1 text-sm text-zinc-500">Pelanggan ini belum memiliki paket internet atau akun PPP.</p>
                </div>
            @endforelse
        </div>
    @endif

    {{-- ═══════════ Tab 3: Tagihan & Pembayaran ═══════════ --}}
    @if ($activeTab === 'billing')
        <div class="space-y-6">
            <div class="flex flex-col gap-4 rounded-xl border p-4 sm:flex-row sm:items-center sm:justify-between sm:p-6 {{ $totalBelumDibayar > 0 ? 'border-rose-200 bg-rose-50 dark:border-rose-900 dark:bg-rose-950/40' : 'border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900' }}">
                <div>
                    <div class="text-sm text-zinc-600 dark:text-zinc-400">Belum dibayar · {{ $invoicesAktif->count() }} tagihan</div>
                    <div class="mt-1 text-3xl font-semibold {{ $totalBelumDibayar > 0 ? 'text-rose-700 dark:text-rose-300' : 'text-zinc-900 dark:text-zinc-50' }}">Rp {{ number_format($totalBelumDibayar, 0, ',', '.') }}</div>
                </div>
                @can('create', App\Models\Invoice::class)
                    <flux:button type="button" wire:click="openTambahInvoiceModal" icon="plus">Tambah Invoice</flux:button>
                @endcan
            </div>

            {{-- Tagihan belum dibayar --}}
            <section class="space-y-3">
                <flux:heading size="lg">Tagihan belum dibayar</flux:heading>
                @if ($invoicesAktif->isNotEmpty())
                    <ul class="{{ $card }} divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach ($invoicesAktif as $inv)
                            @php $pgTrx = $inv->transaksiPaymentGateways->first(); @endphp
                            <li wire:key="inv-aktif-{{ $inv->id }}" class="flex flex-col gap-4 p-4 sm:p-6 lg:flex-row lg:items-center lg:justify-between">
                                <div class="min-w-0 space-y-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <a href="{{ route('invoice.show', $inv) }}" wire:navigate class="font-mono text-base font-semibold text-primary-600 hover:underline dark:text-primary-400">{{ $inv->no_invoice }}</a>
                                        <flux:badge size="sm" :color="$inv->status->color()">{{ $inv->status->label() }}</flux:badge>
                                        @if ($inv->promo)
                                            <flux:badge size="sm" color="emerald">{{ $inv->promo->kode_promo }}</flux:badge>
                                        @endif
                                    </div>
                                    <p class="text-base text-zinc-900 dark:text-zinc-100">{{ $inv->formattedPeriodeTagihan() }} · {{ $inv->layananPelanggan?->paketLayanan?->nama_paket ?? 'Layanan' }}</p>
                                    <p class="text-sm text-zinc-500 dark:text-zinc-400">
                                        Jatuh tempo {{ $inv->tanggal_jatuh_tempo->translatedFormat('d M Y') }} · terbit {{ $inv->tanggal_terbit->translatedFormat('d M Y') }}
                                        @if ($pgTrx)
                                            · {{ strtoupper($pgTrx->gateway) }} ({{ strtoupper($pgTrx->channel?->value ?? 'VA') }}){{ $pgTrx->nomor_pembayaran ? ' VA ' . $pgTrx->nomor_pembayaran : '' }}
                                        @elseif ($inv->metode_pembayaran)
                                            · {{ $inv->metode_pembayaran?->label() ?? $inv->metode_pembayaran }}
                                        @endif
                                    </p>
                                </div>
                                <div class="flex items-center justify-between gap-4 lg:justify-end">
                                    <div class="text-left lg:text-right">
                                        <div class="font-mono text-lg font-semibold text-zinc-900 dark:text-zinc-50">{{ $inv->formattedJumlahSetelahPromo() }}</div>
                                        @if ($inv->jumlah != $inv->jumlah_setelah_promo)
                                            <div class="font-mono text-sm text-zinc-400 line-through">{{ $inv->formattedJumlah() }}</div>
                                        @endif
                                    </div>
                                    <div class="flex items-center gap-1">
                                        @can('create', App\Models\Pembayaran::class)
                                            <flux:button type="button" wire:click="openBayarModal({{ $inv->id }})" variant="primary" icon="banknotes">Bayar</flux:button>
                                        @endcan
                                        @if ($inv->hasActiveXenditInvoice())
                                            <flux:button :href="$inv->xendit_invoice_url" target="_blank" variant="subtle" icon="arrow-top-right-on-square" aria-label="Buka tautan pembayaran Xendit" />
                                        @endif
                                    </div>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <div class="rounded-xl border-2 border-dashed border-zinc-200 p-8 text-center dark:border-zinc-800">
                        <flux:icon name="check-circle" class="mx-auto size-8 text-emerald-500" />
                        <p class="mt-2 text-base font-medium text-zinc-700 dark:text-zinc-300">Tidak ada tagihan yang belum dibayar.</p>
                    </div>
                @endif
            </section>

            {{-- Riwayat lunas --}}
            <section class="space-y-3">
                <flux:heading size="lg">Riwayat Pembayaran Lunas <span class="text-base font-normal text-zinc-500">({{ $invoicesLunas->count() }})</span></flux:heading>
                @if ($invoicesLunas->isNotEmpty())
                    <ul class="{{ $card }} divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach ($invoicesLunas as $inv)
                            @php $lastPayment = $inv->pembayarans->first(); @endphp
                            <li wire:key="inv-lunas-{{ $inv->id }}">
                                <a href="{{ route('invoice.show', $inv) }}" wire:navigate class="flex flex-col gap-2 p-4 hover:bg-zinc-50 sm:flex-row sm:items-center sm:justify-between sm:p-6 dark:hover:bg-zinc-800/60">
                                    <div class="min-w-0 space-y-1">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="font-mono text-base font-semibold text-primary-600 dark:text-primary-400">{{ $inv->no_invoice }}</span>
                                            @if ($inv->promo)
                                                <flux:badge size="sm" color="emerald">{{ $inv->promo->kode_promo }}</flux:badge>
                                            @endif
                                        </div>
                                        <p class="text-base text-zinc-900 dark:text-zinc-100">{{ $inv->formattedPeriodeTagihan() }} · {{ $inv->layananPelanggan?->paketLayanan?->nama_paket ?? 'Layanan' }}</p>
                                        <p class="text-sm text-zinc-500 dark:text-zinc-400">
                                            Lunas {{ $inv->tanggal_lunas?->translatedFormat('d M Y') ?? $lastPayment?->dibayar_pada?->translatedFormat('d M Y') ?? '—' }}
                                            · {{ $lastPayment?->metode?->label() ?? $inv->metode_pembayaran?->label() ?? 'Lunas' }}
                                            · {{ $lastPayment?->dicatatOleh?->name ?? 'Sistem Gateway' }}
                                            @if ($lastPayment?->referensi_transaksi)
                                                · Ref {{ $lastPayment->referensi_transaksi }}
                                            @endif
                                        </p>
                                    </div>
                                    <div class="font-mono text-lg font-semibold text-emerald-700 sm:text-right dark:text-emerald-400">{{ $inv->formattedJumlahSetelahPromo() }}</div>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <div class="rounded-xl border-2 border-dashed border-zinc-200 p-8 text-center text-base text-zinc-500 dark:border-zinc-800">Belum ada invoice yang lunas.</div>
                @endif
            </section>

            {{-- Invoice dihapus: jarang dibuka, dilipat --}}
            <details class="{{ $card }} group">
                <summary class="flex min-h-14 cursor-pointer list-none items-center justify-between px-4 text-base font-semibold text-zinc-900 sm:px-6 dark:text-zinc-50">
                    <span>Riwayat Invoice Dihapus / Dibatalkan <span class="font-normal text-zinc-500">({{ $invoicesDihapus->count() }})</span></span>
                    <flux:icon name="chevron-down" class="size-5 text-zinc-400 transition group-open:rotate-180" />
                </summary>
                @if ($invoicesDihapus->isNotEmpty())
                    <ul class="divide-y divide-zinc-100 border-t border-zinc-100 dark:divide-zinc-800 dark:border-zinc-800">
                        @foreach ($invoicesDihapus as $delInv)
                            <li wire:key="inv-hapus-{{ $delInv->id }}" class="flex flex-col gap-1 p-4 sm:px-6">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <span class="font-mono text-base text-zinc-600 dark:text-zinc-400">{{ $delInv->no_invoice }}</span>
                                    <span class="font-mono text-base text-zinc-600 dark:text-zinc-400">{{ $delInv->formattedJumlahSetelahPromo() }}</span>
                                </div>
                                <p class="text-sm text-zinc-500">{{ $delInv->formattedPeriodeTagihan() }} · {{ $delInv->layananPelanggan?->paketLayanan?->nama_paket ?? '—' }}</p>
                                <p class="text-sm text-zinc-500">Dihapus {{ $delInv->deleted_at?->translatedFormat('d M Y, H:i') ?? '—' }} oleh {{ $delInv->dihapusOleh?->name ?? 'Sistem' }}</p>
                                @if ($delInv->keterangan_hapus)
                                    <p class="text-sm italic text-zinc-600 dark:text-zinc-400">“{{ $delInv->keterangan_hapus }}”</p>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="border-t border-zinc-100 p-4 text-sm text-zinc-500 sm:px-6 dark:border-zinc-800">Tidak ada invoice yang dihapus.</p>
                @endif
            </details>
        </div>
    @endif

    {{-- ═══════════ Tab 4: Dokumen & Legalitas ═══════════ --}}
    @if ($activeTab === 'dokumen')
        <div class="space-y-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <flux:heading size="lg">Dokumen Identitas & Legalitas</flux:heading>
                    <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Berkas disimpan terenkripsi dan dilindungi tanda air dinamis (UU PDP).</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    @can('update', $pelanggan)
                        <flux:button wire:click="openUploadKtpModal" variant="subtle" icon="identification" class="flex-1 sm:flex-none">{{ $ktpMedia ? 'Ganti Foto KTP' : 'Unggah Foto KTP' }}</flux:button>
                    @endcan
                    @can('uploadDokumen', $pelanggan)
                        <flux:button wire:click="openUploadDocModal" variant="primary" icon="plus" class="flex-1 sm:flex-none">Unggah Dokumen Baru</flux:button>
                    @endcan
                </div>
            </div>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                {{-- KTP --}}
                <section class="{{ $card }} p-4 sm:p-6">
                    <div class="flex items-center justify-between gap-2">
                        <h3 class="text-base font-semibold text-zinc-900 dark:text-zinc-50">Kartu Identitas (KTP)</h3>
                        <flux:badge size="sm" :color="$ktpMedia ? 'green' : 'zinc'" :icon="$ktpMedia ? 'lock-closed' : null">{{ $ktpMedia ? 'Terenkripsi' : 'Belum Ada' }}</flux:badge>
                    </div>
                    @if ($ktpMedia)
                        <div class="mt-4 flex flex-col items-center gap-2 rounded-lg bg-zinc-50 p-6 text-center dark:bg-zinc-800/60">
                            <flux:icon name="shield-check" class="size-8 text-emerald-600 dark:text-emerald-400" />
                            <span class="text-base font-medium text-zinc-700 dark:text-zinc-300">Berkas KTP tersimpan aman</span>
                            <span class="break-all font-mono text-sm text-zinc-500">{{ $ktpMedia->file_name }} ({{ number_format($ktpMedia->size / 1024, 1) }} KB)</span>
                        </div>
                        @can('viewKtp', $pelanggan)
                            <flux:button wire:click="openKtpModal" variant="primary" icon="eye" class="mt-4 w-full">Buka Foto KTP (Watermarked)</flux:button>
                        @else
                            <p class="mt-4 rounded-lg bg-amber-50 p-3 text-center text-sm text-amber-800 dark:bg-amber-950/30 dark:text-amber-300">Anda tidak memiliki hak akses untuk melihat foto KTP.</p>
                        @endcan
                    @else
                        <div class="mt-4 rounded-lg border-2 border-dashed border-zinc-200 p-6 text-center dark:border-zinc-800">
                            <flux:icon name="identification" class="mx-auto size-8 text-zinc-300 dark:text-zinc-600" />
                            <p class="mt-2 text-sm text-zinc-500">Belum ada foto KTP.</p>
                            @can('update', $pelanggan)
                                <flux:button wire:click="openUploadKtpModal" variant="ghost" icon="arrow-up-tray" class="mt-2">Unggah Sekarang</flux:button>
                            @endcan
                        </div>
                    @endif
                </section>

                {{-- Dokumen pendukung --}}
                <section class="{{ $card }} lg:col-span-2">
                    <div class="flex items-center justify-between gap-2 p-4 sm:p-6">
                        <h3 class="text-base font-semibold text-zinc-900 dark:text-zinc-50">Dokumen MOU & Legalitas Pendukung</h3>
                        <span class="text-sm text-zinc-500">{{ $dokumens->count() }} dokumen</span>
                    </div>
                    @if ($dokumens->isNotEmpty())
                        <ul class="divide-y divide-zinc-100 border-t border-zinc-100 dark:divide-zinc-800 dark:border-zinc-800">
                            @foreach ($dokumens as $doc)
                                @php
                                    $jenis = $doc->getCustomProperty('jenis_dokumen', 'Dokumen');
                                    $nomor = $doc->getCustomProperty('nomor_dokumen');
                                    $ket = $doc->getCustomProperty('keterangan');
                                    $isPdf = str_contains((string) $doc->mime_type, 'pdf');
                                @endphp
                                <li wire:key="doc-{{ $doc->id }}" class="flex items-start gap-3 p-4 sm:px-6">
                                    <flux:icon :name="$isPdf ? 'document-text' : 'photo'" class="mt-0.5 size-6 shrink-0 text-zinc-400" />
                                    <div class="min-w-0 flex-1 space-y-1">
                                        <p class="break-all text-base font-medium text-zinc-900 dark:text-zinc-100">{{ $doc->file_name }}</p>
                                        <div class="flex flex-wrap items-center gap-2">
                                            <flux:badge size="sm" :color="match ($jenis) {
                                                'MOU / Kontrak' => 'indigo',
                                                'Formulir Berlangganan' => 'emerald',
                                                'Surat Kuasa' => 'amber',
                                                'Berita Acara Pemasangan' => 'cyan',
                                                default => 'zinc',
                                            }">{{ $jenis }}</flux:badge>
                                            @if ($nomor)
                                                <span class="font-mono text-sm text-zinc-500">{{ $nomor }}</span>
                                            @endif
                                        </div>
                                        @if ($ket)
                                            <p class="text-sm text-zinc-600 dark:text-zinc-400">{{ $ket }}</p>
                                        @endif
                                        <p class="text-sm text-zinc-500">
                                            {{ number_format($doc->size / 1024, 1) }} KB · {{ $doc->created_at->translatedFormat('d M Y, H:i') }} · {{ $doc->getCustomProperty('uploaded_by', 'Staf') }}
                                        </p>
                                    </div>
                                    <div class="flex shrink-0 items-center gap-1">
                                        @can('viewDokumen', $pelanggan)
                                            <flux:button :href="route('pelanggan.dokumen.stream', [$pelanggan, $doc])" target="_blank" variant="ghost" icon="arrow-down-tray" aria-label="Lihat / unduh {{ $doc->file_name }}" />
                                        @endcan
                                        @can('deleteDokumen', $pelanggan)
                                            <flux:button wire:click="deleteDokumen({{ $doc->id }})" wire:confirm="Apakah Anda yakin ingin menghapus berkas dokumen {{ $doc->file_name }}?" variant="ghost" icon="trash" class="text-red-500 hover:text-red-700" aria-label="Hapus {{ $doc->file_name }}" />
                                        @endcan
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <div class="mx-4 mb-4 rounded-lg border-2 border-dashed border-zinc-200 p-8 text-center sm:mx-6 sm:mb-6 dark:border-zinc-800">
                            <flux:icon name="document-text" class="mx-auto size-8 text-zinc-300 dark:text-zinc-600" />
                            <p class="mt-2 text-sm text-zinc-500">Belum ada dokumen MOU atau legalitas.</p>
                            @can('uploadDokumen', $pelanggan)
                                <flux:button wire:click="openUploadDocModal" variant="ghost" icon="plus" class="mt-2">Unggah Dokumen MOU</flux:button>
                            @endcan
                        </div>
                    @endif
                </section>
            </div>
        </div>
    @endif

    {{-- ═══════════ Tab: Riwayat Tiket ═══════════ --}}
    @if ($activeTab === 'tiket' && $tickets)
        <section class="space-y-3">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div class="grid grid-cols-2 gap-3 sm:flex">
                    <flux:select wire:model.live="filterStatusTiket" label="Status" size="sm">
                        <flux:select.option value="">Semua</flux:select.option>
                        <flux:select.option value="terbuka">Terbuka</flux:select.option>
                        <flux:select.option value="ditutup">Selesai / Batal</flux:select.option>
                    </flux:select>
                    <flux:select wire:model.live="filterJenisTiket" label="Jenis" size="sm">
                        <flux:select.option value="">Semua</flux:select.option>
                        @foreach (App\Enums\Ticket\JenisTicket::cases() as $jenis)
                            <flux:select.option value="{{ $jenis->value }}">{{ $jenis->label() }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>
                @can('create', App\Models\Ticket::class)
                    <flux:button :href="route('ticket.create', ['pelanggan_id' => $pelanggan->id])" wire:navigate variant="primary" icon="plus">Buat Tiket</flux:button>
                @endcan
            </div>

            @if ($tickets->isNotEmpty())
                <ul class="{{ $card }} divide-y divide-zinc-100 dark:divide-zinc-800">
                    @foreach ($tickets as $tiket)
                        <li wire:key="tiket-{{ $tiket->id }}">
                            <a href="{{ route('ticket.show', $tiket) }}" wire:navigate class="flex flex-col gap-2 p-4 hover:bg-zinc-50 sm:flex-row sm:items-center sm:justify-between sm:p-6 dark:hover:bg-zinc-800/60">
                                <div class="min-w-0 space-y-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="font-mono text-base font-semibold text-primary-600 dark:text-primary-400">{{ $tiket->nomor_ticket }}</span>
                                        <flux:badge size="sm" :color="$tiket->jenis->color()">{{ $tiket->jenis->label() }}</flux:badge>
                                        <flux:badge size="sm" :color="$tiket->status->color()">{{ $tiket->status->label() }}</flux:badge>
                                        <flux:badge size="sm" :color="$tiket->prioritas->color()">{{ $tiket->prioritas->label() }}</flux:badge>
                                    </div>
                                    <p class="text-base text-zinc-900 dark:text-zinc-100">
                                        {{ $tiket->layananPelanggan ? ($tiket->layananPelanggan->site_id ?? '—') . ' · ' . ($tiket->layananPelanggan->paketLayanan?->nama_paket ?? 'Layanan') : 'Tanpa layanan terkait' }}
                                    </p>
                                    <p class="text-sm text-zinc-500 dark:text-zinc-400">PIC {{ $tiket->pic?->name ?? 'Belum ditugaskan' }}</p>
                                </div>
                                <div class="shrink-0 text-sm text-zinc-500 sm:text-right dark:text-zinc-400">
                                    <div>Dibuat {{ $tiket->created_at?->translatedFormat('d M Y, H:i') }}</div>
                                    <div>Diperbarui {{ $tiket->updated_at?->translatedFormat('d M Y, H:i') }}</div>
                                </div>
                            </a>
                        </li>
                    @endforeach
                </ul>
                <div>{{ $tickets->links() }}</div>
            @else
                <div class="rounded-xl border-2 border-dashed border-zinc-200 p-8 text-center dark:border-zinc-800">
                    <flux:icon name="ticket" class="mx-auto size-8 text-zinc-400" />
                    <p class="mt-2 text-base font-medium text-zinc-700 dark:text-zinc-300">Belum ada tiket untuk pelanggan ini.</p>
                </div>
            @endif
        </section>
    @endif

    {{-- ═══════════ Tab 5: Riwayat Aktivitas (timeline) ═══════════ --}}
    @if ($activeTab === 'audit')
        @php
            $fmtNilai = fn ($v) => $v === null || $v === '' ? '—' : (is_scalar($v) ? (string) $v : json_encode($v, JSON_UNESCAPED_UNICODE));
        @endphp
        <section class="{{ $card }}">
            <div class="p-4 sm:p-6">
                <flux:heading size="lg">Log Aktivitas Data Pelanggan</flux:heading>
                <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Perubahan data dan riwayat administratif, terbaru di atas.</p>
            </div>
            <ol class="divide-y divide-zinc-100 border-t border-zinc-100 dark:divide-zinc-800 dark:border-zinc-800">
                @forelse ($activityLogs as $log)
                    @php
                        $baru = $log->attribute_changes?->get('attributes') ?? [];
                        $lama = $log->attribute_changes?->get('old') ?? [];
                    @endphp
                    <li wire:key="log-{{ $log->id }}" class="space-y-2 p-4 sm:px-6">
                        <div class="flex flex-col gap-1 sm:flex-row sm:items-baseline sm:justify-between sm:gap-4">
                            <p class="text-base font-medium text-zinc-900 dark:text-zinc-100">{{ $log->description }}</p>
                            <time datetime="{{ $log->created_at->toIso8601String() }}" class="shrink-0 text-sm text-zinc-500">{{ $log->created_at->translatedFormat('d M Y, H:i') }}</time>
                        </div>
                        <p class="text-sm text-zinc-500">oleh {{ $log->causer?->name ?? 'Sistem' }}</p>
                        @if (! empty($baru))
                            <dl class="grid grid-cols-1 gap-2 rounded-lg bg-zinc-50 p-3 text-sm sm:grid-cols-2 dark:bg-zinc-800/60">
                                @foreach ($baru as $field => $nilai)
                                    <div>
                                        <dt class="text-zinc-500">{{ str($field)->replace('_', ' ')->ucfirst() }}</dt>
                                        <dd class="break-words text-zinc-900 dark:text-zinc-100">
                                            @if (array_key_exists($field, $lama))
                                                <span class="text-zinc-400 line-through">{{ $fmtNilai($lama[$field]) }}</span> →
                                            @endif
                                            {{ $fmtNilai($nilai) }}
                                        </dd>
                                    </div>
                                @endforeach
                            </dl>
                        @endif
                        @if ($log->properties->isNotEmpty())
                            <details>
                                <summary class="inline-flex min-h-11 cursor-pointer items-center text-sm font-medium text-zinc-600 dark:text-zinc-400">Data tambahan</summary>
                                <pre class="overflow-x-auto rounded-lg bg-zinc-50 p-3 font-mono text-xs text-zinc-700 dark:bg-zinc-800/60 dark:text-zinc-300">{{ json_encode($log->properties, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                            </details>
                        @endif
                    </li>
                @empty
                    <li class="p-8 text-center text-base text-zinc-500">Belum ada riwayat aktivitas tercatat untuk pelanggan ini.</li>
                @endforelse
            </ol>
        </section>
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
                            <flux:input wire:model="bayarJumlah" type="number" step="1000" readonly description="Sistem belum mendukung pembayaran sebagian; nominal wajib sama persis dengan tagihan." />
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

    {{-- ─── Modal 5: Modal Ubah / Upgrade Paket Layanan & Auto Sync MikroTik ─── --}}
    @if ($showUbahPaketModal && $selectedLayananForModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-black/60 p-4 backdrop-blur-sm">
            <div class="relative w-full max-w-lg rounded-2xl border border-zinc-200 bg-white p-6 shadow-2xl dark:border-zinc-700 dark:bg-zinc-900 space-y-5">
                <div class="flex items-center justify-between border-b border-zinc-100 pb-3 dark:border-zinc-800">
                    <div class="flex items-center gap-2">
                        <div class="flex size-8 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600 dark:bg-indigo-950 dark:text-indigo-400">
                            <flux:icon name="arrows-up-down" class="size-5" />
                        </div>
                        <div>
                            <flux:heading size="lg">Ubah / Upgrade Paket Layanan</flux:heading>
                            <flux:description class="text-xs">Site: <strong class="font-mono text-zinc-900 dark:text-zinc-100">{{ $selectedLayananForModal->site_id }}</strong> ({{ $selectedLayananForModal->ppp_username }})</flux:description>
                        </div>
                    </div>
                    <flux:button wire:click="closeUbahPaketModal" variant="ghost" size="sm" icon="x-mark" />
                </div>

                {{-- Summary Paket Aktif Saat Ini --}}
                <div class="rounded-xl bg-zinc-50 p-4 text-xs dark:bg-zinc-800/60 space-y-2">
                    <div class="flex justify-between">
                        <span class="text-zinc-500">Paket Saat Ini:</span>
                        <span class="font-semibold text-zinc-800 dark:text-zinc-200">{{ $selectedLayananForModal->paketLayanan?->nama_paket ?? '-' }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-zinc-500">Profil Bandwidth:</span>
                        <span class="font-medium text-zinc-700 dark:text-zinc-300">{{ $selectedLayananForModal->paketLayanan?->profilBandwidth?->labelKecepatan() ?? '-' }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-zinc-500">Tarif Saat Ini:</span>
                        <span class="font-mono font-medium text-zinc-700 dark:text-zinc-300">Rp {{ number_format($selectedLayananForModal->total_tarif, 0, ',', '.') }}/bln</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-zinc-500">Router BRAS:</span>
                        <span class="font-medium text-zinc-700 dark:text-zinc-300">{{ $selectedLayananForModal->router?->nama_router ?? '-' }}</span>
                    </div>
                </div>

                <form wire:submit="prosesUbahPaket" class="space-y-4">
                    <x-searchable-select field="newPaketId" label="Pilih Paket Baru" placeholder="Cari nama paket..." />

                    <div class="rounded-lg border border-indigo-100 bg-indigo-50/70 p-3.5 text-xs text-indigo-900 dark:border-indigo-900/40 dark:bg-indigo-950/30 dark:text-indigo-300">
                        <div class="flex items-start gap-2">
                            <flux:icon name="bolt" class="size-4 shrink-0 mt-0.5 text-indigo-600 dark:text-indigo-400" />
                            <div>
                                <span class="font-semibold">Otomatisasi Sinkronisasi MikroTik:</span>
                                <p class="mt-0.5 text-[11px] text-indigo-700 dark:text-indigo-400">
                                    Sistem akan otomatis memperbarui PPP Profile di MikroTik RouterOS dan memutus sesi aktif client (re-dial 1 detik) agar limit kecepatan baru langsung aktif.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end gap-2 pt-2">
                        <flux:button wire:click="closeUbahPaketModal" type="button" variant="ghost">Batal</flux:button>
                        <flux:button type="submit" variant="primary" icon="check">Terapkan Perubahan</flux:button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- Modal 6: Tambah Invoice Manual --}}
    @if ($showTambahInvoiceModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-black/60 p-4 backdrop-blur-sm">
            <div class="relative w-full max-w-lg rounded-2xl border border-zinc-200 bg-white p-6 shadow-2xl dark:border-zinc-700 dark:bg-zinc-900 space-y-5">
                <div class="flex items-center justify-between border-b border-zinc-100 pb-3 dark:border-zinc-800">
                    <div class="flex items-center gap-2">
                        <div class="flex size-8 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600 dark:bg-emerald-950 dark:text-emerald-400">
                            <flux:icon name="document-plus" class="size-5" />
                        </div>
                        <div>
                            <flux:heading size="lg">Tambah Invoice Manual</flux:heading>
                            <flux:description class="text-xs">Biaya instalasi, denda, atau tagihan lain di luar tagihan bulanan otomatis.</flux:description>
                        </div>
                    </div>
                    <flux:button wire:click="closeTambahInvoiceModal" variant="ghost" size="sm" icon="x-mark" />
                </div>

                <form wire:submit="simpanTambahInvoice" class="space-y-4">
                    <flux:field>
                        <flux:label>Layanan Terkait *</flux:label>
                        <flux:select wire:model="tambahInvoiceLayananId">
                            <flux:select.option value="">-- Pilih Layanan --</flux:select.option>
                            @foreach ($pelanggan->layanans as $lay)
                                <flux:select.option value="{{ $lay->id }}">
                                    {{ $lay->site_id }} - {{ $lay->paketLayanan?->nama_paket }} (PPP: {{ $lay->ppp_username }})
                                </flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:error name="tambahInvoiceLayananId" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Detail / Keterangan Invoice *</flux:label>
                        <flux:textarea wire:model="tambahInvoiceKeterangan" rows="2" placeholder="Contoh: Biaya instalasi pemasangan baru, denda keterlambatan, dll." />
                        <flux:error name="tambahInvoiceKeterangan" />
                    </flux:field>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <flux:field>
                            <flux:label>Total Jumlah (Rp) *</flux:label>
                            <flux:input type="number" wire:model="tambahInvoiceJumlah" placeholder="250000" description="Hanya angka, tanpa titik/koma." />
                            <flux:error name="tambahInvoiceJumlah" />
                        </flux:field>

                        <flux:field>
                            <flux:label>Tanggal Jatuh Tempo *</flux:label>
                            <flux:input type="date" wire:model="tambahInvoiceTanggalJatuhTempo" />
                            <flux:error name="tambahInvoiceTanggalJatuhTempo" />
                        </flux:field>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <flux:field>
                            <flux:label>Pilih Promo (Opsional)</flux:label>
                            <flux:select wire:model.live="tambahInvoicePromoId">
                                <flux:select.option value="">-- Tanpa Promo --</flux:select.option>
                                @foreach ($promosAktif as $promo)
                                    <flux:select.option value="{{ $promo->id }}">
                                        {{ $promo->kode_promo }} - {{ $promo->nama_promo }}
                                    </flux:select.option>
                                @endforeach
                            </flux:select>
                        </flux:field>

                        <flux:field>
                            <flux:label>Kode Promo (Opsional)</flux:label>
                            <flux:input wire:model.live="tambahInvoiceKodePromo" placeholder="Kode promo global/musiman" :disabled="(bool) $tambahInvoicePromoId" />
                            <flux:error name="tambahInvoiceKodePromo" />
                        </flux:field>
                    </div>

                    <div class="flex justify-end gap-2 pt-2">
                        <flux:button wire:click="closeTambahInvoiceModal" type="button" variant="ghost">Batal</flux:button>
                        <flux:button type="submit" variant="primary" icon="document-check">Terbitkan Invoice</flux:button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
