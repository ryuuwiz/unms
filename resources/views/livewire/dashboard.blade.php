<div class="space-y-6" wire:poll.120s>
    <div>
        <flux:heading size="xl" class="font-bold">Dashboard Operasional & Billing</flux:heading>
        <flux:subheading>Bulan berjalan · diperbarui {{ now()->format('H:i') }}</flux:subheading>
    </div>

    @if (!$pelanggan && !$pendapatan && !$tagihan && !$perluPerhatian)
        <div
            class="p-8 rounded-2xl bg-white dark:bg-zinc-800 border border-zinc-200/80 dark:border-zinc-700/80 text-center text-sm text-zinc-500 dark:text-zinc-400">
            Tidak ada ringkasan untuk peran Anda.
        </div>
    @endif

    @if ($pelanggan || $pendapatan || $tagihan)
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            @if ($pelanggan)
                @php $persenAktif = $pelanggan['total'] > 0 ? round(($pelanggan['aktif'] / $pelanggan['total']) * 100) : 0; @endphp
                <x-stat-card title="Total Pelanggan" :value="number_format($pelanggan['total'], 0, ',', '.')" :subvalue="$pelanggan['aktif'] . ' aktif · ' . $pelanggan['tidak_aktif'] . ' tidak aktif'" icon="users"
                    color="indigo" :href="route('pelanggan.index')">
                    <div class="h-1.5 rounded-full bg-zinc-200 dark:bg-zinc-700 overflow-hidden">
                        <div class="h-full bg-emerald-500" style="width: {{ $persenAktif }}%"></div>
                    </div>
                </x-stat-card>
            @endif

            @if ($pendapatan)
                <x-stat-card title="Pendapatan Hari Ini" :value="'Rp ' . number_format($pendapatan['hari_ini'], 0, ',', '.')" :subvalue="$pendapatan['kemarin'] > 0 ? 'Kemarin: Rp ' . number_format($pendapatan['kemarin'], 0, ',', '.') : null" icon="currency-dollar"
                    color="emerald" :href="route('pembayaran.index')" />

                <x-stat-card title="Pendapatan Bulan Ini" :value="'Rp ' . number_format($pendapatan['bulan_ini'], 0, ',', '.')" :trend="$pendapatan['pertumbuhan'] !== null
                    ? ($pendapatan['pertumbuhan'] >= 0 ? '+' : '') . $pendapatan['pertumbuhan'] . '%'
                    : null"
                    :trend-type="($pendapatan['pertumbuhan'] ?? 0) >= 0 ? 'up' : 'down'"
                    :trend-label="$pendapatan['pertumbuhan'] !== null ? 'vs periode sama bulan lalu' : null"
                    :subvalue="$pendapatan['pertumbuhan'] === null ? 'Belum ada pembanding bulan lalu' : null" icon="banknotes" color="purple"
                    :href="route('pembayaran.index')" />
            @endif

            @if ($tagihan)
                <x-stat-card :title="'Tagihan Periode ' . now()->translatedFormat('M Y')" :value="'Rp ' . number_format($tagihan['ditagih'], 0, ',', '.')" :trend="$tagihan['tertagih'] !== null ? $tagihan['tertagih'] . '%' : null"
                    :trend-type="($tagihan['tertagih'] ?? 0) >= 75 ? 'up' : (($tagihan['tertagih'] ?? 0) >= 50 ? 'neutral' : 'down')" :trend-label="$tagihan['tertagih'] !== null ? 'tertagih' : null" :subvalue="'Lunas: Rp ' . number_format($tagihan['lunas'], 0, ',', '.')"
                    icon="document-text" color="amber" :href="route('invoice.index', ['status' => 'belum_dibayar'])">
                    <div class="h-1.5 rounded-full bg-zinc-200 dark:bg-zinc-700 overflow-hidden">
                        <div class="h-full bg-amber-500" style="width: {{ min(100, $tagihan['tertagih'] ?? 0) }}%"></div>
                    </div>
                    <div>Belum lunas: Rp {{ number_format($tagihan['belum_lunas'], 0, ',', '.') }}</div>
                    <div>Total belum dibayar, semua periode: Rp {{ number_format($tagihan['belum_dibayar'], 0, ',', '.') }}
                    </div>
                </x-stat-card>
            @endif
        </div>
    @endif

    @if ($perluPerhatian || $transaksiTerbaru)
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 items-start">
            @if ($perluPerhatian)
                @php $total = $perluPerhatian['lewat'] + $perluPerhatian['segera']; @endphp
                <div
                    class="p-5 rounded-2xl bg-white dark:bg-zinc-800 border border-zinc-200/80 dark:border-zinc-700/80 shadow-xs space-y-4">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <div class="flex items-center gap-2">
                                <flux:heading size="lg">Pelanggan Expired & Jatuh Tempo</flux:heading>
                                @if ($total > 0)
                                    <flux:badge size="sm" color="rose">{{ $total }}</flux:badge>
                                @endif
                            </div>
                            <flux:subheading>{{ $perluPerhatian['lewat'] }} sudah lewat · {{ $perluPerhatian['segera'] }} jatuh tempo ≤ H-{{ $perluPerhatian['lead'] }}</flux:subheading>
                        </div>
                        @if ($total > $perluPerhatian['daftar']->count())
                            <flux:button :href="route('layanan-pelanggan.index', ['expiry' => 'all'])" variant="ghost"
                                size="xs" wire:navigate>
                                Lihat semua {{ $total }} &rarr;
                            </flux:button>
                        @endif
                    </div>

                    <div class="divide-y divide-zinc-100 dark:divide-zinc-700/60">
                        @forelse ($perluPerhatian['daftar'] as $layanan)
                            @php
                                $hari = (int) today()->diffInDays($layanan->tanggal_expired, false);
                                $tujuan =
                                    $bisaLihatInvoice && $layanan->invoice_terbuka_id
                                        ? route('invoice.show', $layanan->invoice_terbuka_id)
                                        : ($bisaLihatPelanggan
                                            ? route('pelanggan.show', $layanan->pelanggan_id)
                                            : null);
                            @endphp
                            <a wire:key="expired-{{ $layanan->id }}"
                                @if ($tujuan) href="{{ $tujuan }}" wire:navigate @endif
                                class="py-3 px-2 flex items-center justify-between gap-3 text-xs rounded-lg transition {{ $tujuan ? 'hover:bg-zinc-50/50 dark:hover:bg-zinc-700/20' : '' }}">
                                <div class="min-w-0 space-y-0.5">
                                    <div class="font-semibold text-zinc-900 dark:text-zinc-100 truncate">
                                        {{ $layanan->pelanggan?->identitasLengkap() ?? 'Pelanggan #' . $layanan->pelanggan_id }}
                                    </div>
                                    <div class="text-[11px] text-zinc-500 dark:text-zinc-400 truncate">
                                        {{ $layanan->paketLayanan?->nama_paket ?? '-' }} &bull;
                                        Exp {{ $layanan->tanggal_expired->translatedFormat('d M Y') }}
                                    </div>
                                </div>
                                <div class="flex items-center gap-3 shrink-0">
                                    @if ((float) $layanan->tagihan_terbuka > 0)
                                        <span class="font-semibold text-sm text-zinc-900 dark:text-zinc-100">
                                            Rp {{ number_format((float) $layanan->tagihan_terbuka, 0, ',', '.') }}
                                        </span>
                                    @endif
                                    @if ($hari < 0)
                                        <flux:badge size="sm" color="rose">Lewat {{ abs($hari) }} hari</flux:badge>
                                    @elseif ($hari === 0)
                                        <flux:badge size="sm" color="amber">Hari ini</flux:badge>
                                    @else
                                        <flux:badge size="sm" color="amber">H-{{ $hari }}</flux:badge>
                                    @endif
                                </div>
                            </a>
                        @empty
                            <div
                                class="py-8 flex flex-col items-center gap-2 text-xs text-zinc-400 dark:text-zinc-500">
                                <flux:icon name="check-circle" class="size-6 text-emerald-500" />
                                Tidak ada layanan yang perlu ditindaklanjuti
                            </div>
                        @endforelse
                    </div>
                </div>
            @endif

            @if ($transaksiTerbaru)
                <div
                    class="p-5 rounded-2xl bg-white dark:bg-zinc-800 border border-zinc-200/80 dark:border-zinc-700/80 shadow-xs space-y-4">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <flux:heading size="lg">Transaksi Terbaru</flux:heading>
                            <flux:subheading>Pembayaran terakhir yang tercatat.</flux:subheading>
                        </div>
                        <flux:button :href="route('pembayaran.index')" variant="ghost" size="xs" wire:navigate>
                            Lihat semua &rarr;
                        </flux:button>
                    </div>

                    <div class="divide-y divide-zinc-100 dark:divide-zinc-700/60">
                        @forelse ($transaksiTerbaru as $bayar)
                            @php
                                $invoice = $bayar->invoice;
                                $tujuan =
                                    $bisaLihatInvoice && $invoice
                                        ? route('invoice.show', $invoice)
                                        : ($bisaLihatPelanggan && $invoice?->pelanggan_id
                                            ? route('pelanggan.show', $invoice->pelanggan_id)
                                            : null);
                            @endphp
                            <a wire:key="transaksi-{{ $bayar->id }}"
                                @if ($tujuan) href="{{ $tujuan }}" wire:navigate @endif
                                class="py-3 px-2 flex items-center justify-between gap-3 text-xs rounded-lg transition {{ $tujuan ? 'hover:bg-zinc-50/50 dark:hover:bg-zinc-700/20' : '' }}">
                                <div class="min-w-0 space-y-0.5">
                                    <div class="font-semibold text-zinc-900 dark:text-zinc-100 truncate">
                                        {{ $invoice?->pelanggan?->identitasLengkap() ?? 'Invoice #' . $bayar->invoice_id }}
                                        <span class="font-mono text-[10px] text-zinc-400">{{ $invoice?->no_invoice }}</span>
                                    </div>
                                    <div class="text-[11px] text-zinc-500 dark:text-zinc-400 truncate">
                                        {{ $bayar->metode->label() }} &bull;
                                        {{ $bayar->dibayar_pada?->translatedFormat('d M Y, H:i') ?? '-' }}
                                    </div>
                                </div>
                                <div class="shrink-0 font-bold text-sm text-emerald-600 dark:text-emerald-400">
                                    +Rp {{ number_format((float) $bayar->jumlah_dibayar, 0, ',', '.') }}
                                </div>
                            </a>
                        @empty
                            <div class="py-8 text-center text-xs text-zinc-400 dark:text-zinc-500">
                                Belum ada pembayaran yang tercatat.
                            </div>
                        @endforelse
                    </div>
                </div>
            @endif
        </div>
    @endif

    @if ($tren)
        <div
            class="p-5 rounded-2xl bg-white dark:bg-zinc-800 border border-zinc-200/80 dark:border-zinc-700/80 shadow-xs space-y-4">
            <div>
                <flux:heading size="lg">Tren Pendapatan Harian</flux:heading>
                <flux:subheading>Pendapatan (Rp) dan jumlah transaksi per hari, tanggal 1 sampai hari ini.
                </flux:subheading>
            </div>

            <div wire:key="tren-{{ md5(json_encode($tren)) }}" x-data="trenChart(@js($tren))" x-init="initChart()"
                class="min-h-[320px] w-full">
                <div wire:ignore x-ref="chart"></div>
            </div>
        </div>
    @endif
</div>

@script
    <script>
        Alpine.data('trenChart', (config) => ({
            chart: null,
            initChart() {
                const isDark = document.documentElement.classList.contains('dark');
                const teks = isDark ? '#a1a1aa' : '#71717a';

                this.chart = new ApexCharts(this.$refs.chart, {
                    chart: {
                        height: 310,
                        type: 'line',
                        toolbar: {
                            show: false
                        },
                        fontFamily: 'inherit',
                        background: 'transparent',
                    },
                    theme: {
                        mode: isDark ? 'dark' : 'light'
                    },
                    series: [{
                            name: 'Pendapatan (Rp)',
                            type: 'area',
                            data: config.revenue
                        },
                        {
                            name: 'Jumlah Transaksi',
                            type: 'column',
                            data: config.transactions
                        },
                    ],
                    stroke: {
                        width: [2.5, 0],
                        curve: 'smooth'
                    },
                    colors: ['#6366f1', '#10b981'],
                    fill: {
                        type: ['gradient', 'solid'],
                        gradient: {
                            shadeIntensity: 1,
                            opacityFrom: 0.45,
                            opacityTo: 0.05,
                            stops: [0, 90, 100]
                        },
                        opacity: [0.35, 0.85],
                    },
                    plotOptions: {
                        bar: {
                            columnWidth: '35%',
                            borderRadius: 4
                        }
                    },
                    xaxis: {
                        categories: config.categories,
                        labels: {
                            style: {
                                colors: teks,
                                fontSize: '11px'
                            },
                            rotate: -45,
                            rotateAlways: false,
                        },
                        axisBorder: {
                            show: false
                        },
                        axisTicks: {
                            show: false
                        },
                    },
                    yaxis: [{
                            title: {
                                text: 'Pendapatan',
                                style: {
                                    color: teks,
                                    fontSize: '11px'
                                }
                            },
                            labels: {
                                formatter: (value) => value >= 1000000 ? (value / 1000000).toFixed(1) + 'M' :
                                    (value >= 1000 ? (value / 1000).toFixed(0) + 'k' : value),
                                style: {
                                    colors: teks,
                                    fontSize: '11px'
                                },
                            },
                        },
                        {
                            opposite: true,
                            title: {
                                text: 'Transaksi',
                                style: {
                                    color: teks,
                                    fontSize: '11px'
                                }
                            },
                            labels: {
                                formatter: (value) => Math.round(value) + ' trx',
                                style: {
                                    colors: teks,
                                    fontSize: '11px'
                                },
                            },
                        },
                    ],
                    dataLabels: {
                        enabled: false
                    },
                    grid: {
                        borderColor: isDark ? '#27272a' : '#f4f4f5',
                        strokeDashArray: 4
                    },
                    tooltip: {
                        theme: isDark ? 'dark' : 'light',
                        shared: true,
                        intersect: false,
                        y: {
                            formatter: (val, {
                                seriesIndex
                            }) => seriesIndex === 0 ?
                                'Rp ' + new Intl.NumberFormat('id-ID').format(val) :
                                val + ' transaksi',
                        },
                    },
                    legend: {
                        position: 'top',
                        horizontalAlign: 'right',
                        labels: {
                            colors: isDark ? '#e4e4e7' : '#3f3f46'
                        }
                    },
                });

                this.chart.render();
            },
            destroy() {
                this.chart?.destroy();
            },
        }));
    </script>
@endscript
