<div class="space-y-6">
    {{-- Header --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-3">
            <flux:button :href="route('customers.index')" wire:navigate variant="ghost" size="sm" icon="arrow-left" />
            <div>
                <div class="flex items-center gap-2.5">
                    <flux:heading size="xl">{{ $customer->name }}</flux:heading>
                    <flux:badge size="sm" color="zinc" class="font-mono">{{ $customer->customer_code }}</flux:badge>
                    <flux:badge size="sm" :color="$customer->status->color()">
                        {{ $customer->status->label() }}
                    </flux:badge>
                </div>
                <flux:subheading>Didaftarkan pada {{ $customer->created_at?->translatedFormat('d F Y, H:i') ?? '—' }} oleh {{ $customer->creator?->name ?? 'Sistem' }}</flux:subheading>
            </div>
        </div>

        @can('update', $customer)
            <div class="flex items-center gap-2">
                <flux:button :href="route('customers.edit', $customer)" wire:navigate variant="primary" icon="pencil-square">
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
                Langganan / Paket
                <span class="rounded-full bg-zinc-100 px-2 py-0.5 text-xs text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400">Fase 5</span>
            </button>

            <button
                type="button"
                wire:click="setTab('tickets')"
                class="flex items-center gap-2 border-b-2 py-3 text-sm font-medium transition-colors {{ $activeTab === 'tickets' ? 'border-primary-600 text-primary-600 dark:border-primary-400 dark:text-primary-400' : 'border-transparent text-zinc-500 hover:border-zinc-300 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200' }}"
            >
                <flux:icon name="ticket" class="size-4" />
                Tiket Gangguan
                <span class="rounded-full bg-zinc-100 px-2 py-0.5 text-xs text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400">Mendatang</span>
            </button>

            <button
                type="button"
                wire:click="setTab('audit')"
                class="flex items-center gap-2 border-b-2 py-3 text-sm font-medium transition-colors {{ $activeTab === 'audit' ? 'border-primary-600 text-primary-600 dark:border-primary-400 dark:text-primary-400' : 'border-transparent text-zinc-500 hover:border-zinc-300 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200' }}"
            >
                <flux:icon name="clock" class="size-4" />
                Riwayat Aktivitas
                @if ($auditLogs->isNotEmpty())
                    <span class="rounded-full bg-zinc-200 px-1.5 py-0.2 text-xs font-semibold text-zinc-700 dark:bg-zinc-700 dark:text-zinc-300">{{ $auditLogs->count() }}</span>
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
                            <dd class="mt-1 text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $customer->name }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium text-zinc-400">Nomor WhatsApp / HP</dt>
                            <dd class="mt-1 flex items-center gap-2 text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                {{ $customer->phone }}
                                <a
                                    href="https://wa.me/{{ $customer->phone }}"
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
                            <dd class="mt-1 text-sm text-zinc-700 dark:text-zinc-300">{{ $customer->email ?: '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium text-zinc-400">Status Akun</dt>
                            <dd class="mt-1">
                                <flux:badge size="sm" :color="$customer->status->color()">
                                    {{ $customer->status->label() }}
                                </flux:badge>
                            </dd>
                        </div>
                    </dl>
                </div>

                {{-- Card Alamat --}}
                <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                    <flux:heading size="base" class="mb-4">Alamat Pemasangan</flux:heading>

                    <dl class="space-y-4">
                        <div>
                            <dt class="text-xs font-medium text-zinc-400">Alamat Instalasi (Titik Pasang)</dt>
                            <dd class="mt-1 text-sm text-zinc-800 dark:text-zinc-200">{{ $customer->installation_address }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium text-zinc-400">Alamat KTP / Domisili</dt>
                            <dd class="mt-1 text-sm text-zinc-700 dark:text-zinc-300">{{ $customer->address ?: '—' }}</dd>
                        </div>
                    </dl>
                </div>
            </div>

            {{-- Kolom Kanan: Peta Titik Koordinat & Informasi Sistem --}}
            <div class="space-y-6">
                {{-- Card Peta Koordinat --}}
                <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                    <flux:heading size="base" class="mb-3">Titik Koordinat Lokasi</flux:heading>

                    @if ($customer->lat && $customer->lng)
                        <div class="space-y-3">
                            <x-map-picker :lat="$customer->lat" :lng="$customer->lng" :readonly="true" height="240px" />
                            <div class="flex items-center justify-between text-xs text-zinc-500">
                                <span>Lat: <strong class="font-mono text-zinc-700 dark:text-zinc-300">{{ $customer->lat }}</strong></span>
                                <span>Lng: <strong class="font-mono text-zinc-700 dark:text-zinc-300">{{ $customer->lng }}</strong></span>
                            </div>
                            <div>
                                <a
                                    href="https://www.google.com/maps?q={{ $customer->lat }},{{ $customer->lng }}"
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
                            <dd class="font-medium text-zinc-800 dark:text-zinc-200">{{ $customer->creator?->name ?? '—' }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-zinc-400">Tanggal Dibuat</dt>
                            <dd class="text-zinc-700 dark:text-zinc-300">{{ $customer->created_at?->format('d/m/Y H:i') ?? '—' }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-zinc-400">Terakhir Diperbarui</dt>
                            <dd class="text-zinc-700 dark:text-zinc-300">{{ $customer->updated_at?->format('d/m/Y H:i') ?? '—' }}</dd>
                        </div>
                    </dl>
                </div>
            </div>
        </div>
    @endif

    {{-- Tab 2: Langganan (Subscriptions) Placeholder --}}
    @if ($activeTab === 'subscriptions')
        <div class="rounded-xl border border-dashed border-zinc-300 bg-zinc-50 p-12 text-center dark:border-zinc-700 dark:bg-zinc-900/50">
            <div class="mx-auto flex size-12 items-center justify-center rounded-full bg-blue-50 text-blue-600 dark:bg-blue-950 dark:text-blue-400">
                <flux:icon name="rss" class="size-6" />
            </div>
            <flux:heading size="lg" class="mt-4">Modul Langganan & Layanan</flux:heading>
            <flux:subheading class="mx-auto max-w-md mt-1">
                Data paket internet, status koneksi perangkat (PPPoE/Static), dan riwayat langganan pelanggan ini akan dikelola pada modul <strong>PRD #5 (Subscriptions)</strong>.
            </flux:subheading>
        </div>
    @endif

    {{-- Tab 3: Tiket Gangguan Placeholder --}}
    @if ($activeTab === 'tickets')
        <div class="rounded-xl border border-dashed border-zinc-300 bg-zinc-50 p-12 text-center dark:border-zinc-700 dark:bg-zinc-900/50">
            <div class="mx-auto flex size-12 items-center justify-center rounded-full bg-amber-50 text-amber-600 dark:bg-amber-950 dark:text-amber-400">
                <flux:icon name="ticket" class="size-6" />
            </div>
            <flux:heading size="lg" class="mt-4">Modul Tiket Gangguan & Laporan</flux:heading>
            <flux:subheading class="mx-auto max-w-md mt-1">
                Riwayat tiket gangguan jaringan, jadwal survei teknisi, dan penanganan keluhan pelanggan ini akan tersedia pada fase ticketing mendatang.
            </flux:subheading>
        </div>
    @endif

    {{-- Tab 4: Riwayat Aktivitas (Audit Log) --}}
    @if ($activeTab === 'audit')
        <div class="rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <div class="p-6 pb-2">
                <flux:heading size="base">Log Aktivitas Data Pelanggan</flux:heading>
                <flux:subheading>Catatan perubahan data, status, dan riwayat administratif pelanggan.</flux:subheading>
            </div>

            <flux:table>
                <flux:table.columns>
                    <flux:table.column>Waktu</flux:table.column>
                    <flux:table.column>Pengguna (Aktor)</flux:table.column>
                    <flux:table.column>Aksi</flux:table.column>
                    <flux:table.column>Detail Perubahan</flux:table.column>
                    <flux:table.column>Alamat IP</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($auditLogs as $log)
                        <flux:table.row :key="$log->id">
                            <flux:table.cell class="text-xs text-zinc-500">
                                {{ $log->created_at->translatedFormat('d M Y, H:i') }}
                            </flux:table.cell>
                            <flux:table.cell class="font-medium text-xs">
                                {{ $log->user?->name ?? 'Sistem' }}
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:badge size="sm" color="zinc">
                                    {{ Str::headline(str_replace('_', ' ', $log->action)) }}
                                </flux:badge>
                            </flux:table.cell>
                            <flux:table.cell class="text-xs text-zinc-600 dark:text-zinc-300">
                                @if ($log->new_values)
                                    <span class="font-mono text-[11px]">{{ json_encode($log->new_values, JSON_UNESCAPED_UNICODE) }}</span>
                                @else
                                    —
                                @endif
                            </flux:table.cell>
                            <flux:table.cell class="font-mono text-xs text-zinc-400">
                                {{ $log->ip_address ?? '—' }}
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="5" class="py-8 text-center text-zinc-400">
                                Belum ada riwayat aktivitas tercatat untuk pelanggan ini.
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </div>
    @endif
</div>
