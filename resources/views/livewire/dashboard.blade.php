<div class="space-y-6">
    {{-- Header & Period Filter --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl" class="font-bold">Dashboard Operasional & Billing</flux:heading>
            <flux:subheading>Ringkasan performa finansial, data pelanggan, tagihan bulan berjalan, dan pemantauan masa
                aktif layanan.
            </flux:subheading>
        </div>

        <div class="flex items-center gap-2 flex-wrap">
            {{-- Period Filter Pills --}}
            <div
                class="inline-flex items-center p-1 rounded-xl bg-zinc-100 dark:bg-zinc-800/80 border border-zinc-200 dark:border-zinc-700/80 text-xs">
                <button type="button" wire:click="setPeriod('this_month')"
                    class="px-3 py-1.5 rounded-lg font-medium transition-all {{ $period === 'this_month' ? 'bg-white dark:bg-zinc-700 text-indigo-600 dark:text-indigo-400 shadow-xs' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-200' }}">
                    Bulan Ini
                </button>
                <button type="button" wire:click="setPeriod('last_30_days')"
                    class="px-3 py-1.5 rounded-lg font-medium transition-all {{ $period === 'last_30_days' ? 'bg-white dark:bg-zinc-700 text-indigo-600 dark:text-indigo-400 shadow-xs' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-200' }}">
                    30 Hari Terakhir
                </button>
                <button type="button" wire:click="setPeriod('this_year')"
                    class="px-3 py-1.5 rounded-lg font-medium transition-all {{ $period === 'this_year' ? 'bg-white dark:bg-zinc-700 text-indigo-600 dark:text-indigo-400 shadow-xs' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-200' }}">
                    Tahun Ini
                </button>
                <button type="button" wire:click="setPeriod('all')"
                    class="px-3 py-1.5 rounded-lg font-medium transition-all {{ $period === 'all' ? 'bg-white dark:bg-zinc-700 text-indigo-600 dark:text-indigo-400 shadow-xs' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-200' }}">
                    Semua
                </button>
            </div>
        </div>
    </div>

    {{-- Top Row: 4 KPI Summary Cards --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        {{-- 1. Total Pelanggan --}}
        <x-stat-card title="Total Pelanggan" :value="$kpis['total_customers'] . ' Pelanggan'" :trend="$kpis['active_customers'] . ' Aktif'" trend-type="up"
            trend-label="terkoneksi" :subvalue="'Nonaktif: ' . $kpis['inactive_customers'] . ' | Prospek: ' . $kpis['prospect_customers']" icon="users" color="indigo" :href="route('pelanggan.index')" />

        {{-- 2. Pendapatan Hari Ini --}}
        <x-stat-card title="Pendapatan Hari Ini" :value="'Rp ' . number_format($kpis['today_revenue'], 0, ',', '.')" :trend="($kpis['today_growth'] >= 0 ? '+' : '') . $kpis['today_growth'] . '%'" :trend-type="$kpis['today_growth'] >= 0 ? 'up' : 'down'"
            trend-label="vs kemarin" :subvalue="'Kemarin: Rp ' . number_format($kpis['yesterday_revenue'], 0, ',', '.')" icon="currency-dollar" color="emerald" :href="route('pembayaran.index')" />

        {{-- 3. Pendapatan Bulan Ini --}}
        <x-stat-card title="Pendapatan Bulan Ini" :value="'Rp ' . number_format($kpis['month_revenue'], 0, ',', '.')" :trend="($kpis['month_growth'] >= 0 ? '+' : '') . $kpis['month_growth'] . '%'" :trend-type="$kpis['month_growth'] >= 0 ? 'up' : 'down'"
            trend-label="vs bulan lalu" :subvalue="'Bulan Lalu: Rp ' . number_format($kpis['prev_month_revenue'], 0, ',', '.')" icon="banknotes" color="purple" :href="route('pembayaran.index')" />

        {{-- 4. Ringkasan Tagihan Bulan Ini --}}
        <x-stat-card title="Tagihan Bulan Ini" :value="'Rp ' . number_format($kpis['this_month_billed'], 0, ',', '.')" :trend="$kpis['collection_rate'] . '% CR'" :trend-type="$kpis['collection_rate'] >= 75 ? 'up' : ($kpis['collection_rate'] >= 50 ? 'neutral' : 'down')"
            trend-label="tertagih" :subvalue="'Lunas: Rp ' .
                number_format($kpis['this_month_paid'], 0, ',', '.') .
                ' (' .
                $kpis['this_month_paid_count'] .
                ') | Belum: Rp ' .
                number_format($kpis['this_month_unpaid'], 0, ',', '.')" icon="document-text" color="amber" :href="route('invoice.index')" />
    </div>

    {{-- Row 2: Charts Area --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Tren Pendapatan & Transaksi Harian (Dual-Axis Chart) --}}
        <div
            class="lg:col-span-2 p-5 rounded-2xl bg-white dark:bg-zinc-800 border border-zinc-200/80 dark:border-zinc-700/80 shadow-xs flex flex-col justify-between">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <flux:heading size="lg">Tren Pendapatan & Transaksi Harian</flux:heading>
                    <flux:subheading>Visualisasi nominal pendapatan (Rp) dan frekuensi transaksi harian selama bulan
                        berjalan.
                    </flux:subheading>
                </div>
                <span
                    class="text-xs px-2.5 py-1 rounded-full bg-indigo-50 dark:bg-indigo-950/70 text-indigo-600 dark:text-indigo-400 font-semibold border border-indigo-200/50 dark:border-indigo-800/40">
                    Dual-Axis
                </span>
            </div>

            <div wire:ignore x-data="dailyTrendChart({
                categories: @js($dailyTrendChart['categories']),
                revenue: @js($dailyTrendChart['revenue']),
                transactions: @js($dailyTrendChart['transactions']),
            })" x-init="initChart()" class="min-h-[320px] w-full">
                <div x-ref="chart"></div>
            </div>
        </div>

        {{-- Komposisi Paket Layanan (Donut Chart) --}}
        <div
            class="p-5 rounded-2xl bg-white dark:bg-zinc-800 border border-zinc-200/80 dark:border-zinc-700/80 shadow-xs flex flex-col justify-between">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <flux:heading size="lg">Distribusi Paket Layanan</flux:heading>
                    <flux:subheading>Komposisi langganan aktif berdasarkan paket internet.</flux:subheading>
                </div>
                <span
                    class="text-xs px-2.5 py-1 rounded-full bg-emerald-50 dark:bg-emerald-950/70 text-emerald-600 dark:text-emerald-400 font-semibold border border-emerald-200/50 dark:border-emerald-800/40">
                    Produk
                </span>
            </div>

            <div wire:ignore x-data="paketChart({
                labels: @js($paketChart['labels']),
                series: @js($paketChart['series']),
            })" x-init="initChart()"
                class="min-h-[320px] w-full flex items-center justify-center">
                <div x-ref="chart" class="w-full"></div>
            </div>
        </div>
    </div>

    {{-- Row 3: Operational Breakdown & Recent Activities --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Transaksi Pembayaran Terbaru --}}
        <div
            class="p-5 rounded-2xl bg-white dark:bg-zinc-800 border border-zinc-200/80 dark:border-zinc-700/80 shadow-xs space-y-4">
            <div class="flex items-center justify-between">
                <div>
                    <flux:heading size="lg">Transaksi Pembayaran Terbaru</flux:heading>
                    <flux:subheading>Riwayat penerimaan pembayaran lunas terbaru di sistem.</flux:subheading>
                </div>
                <flux:button :href="route('pembayaran.index')" variant="ghost" size="xs" wire:navigate>
                    Lihat Semua &rarr;
                </flux:button>
            </div>

            <div class="divide-y divide-zinc-100 dark:divide-zinc-700/60">
                @forelse($recentPayments as $bayar)
                    <div
                        class="py-3 flex items-center justify-between gap-3 text-xs hover:bg-zinc-50/50 dark:hover:bg-zinc-700/20 px-2 rounded-lg transition">
                        <div class="min-w-0 space-y-0.5">
                            <div
                                class="font-semibold text-zinc-900 dark:text-zinc-100 truncate flex items-center gap-2">
                                <span>{{ $bayar->invoice?->pelanggan?->identitasLengkap() ?? 'Pelanggan #' . $bayar->invoice_id }}</span>
                                <span
                                    class="text-[10px] font-mono text-zinc-400">({{ $bayar->invoice?->no_invoice }})</span>
                            </div>
                            <div class="text-[11px] text-zinc-500 dark:text-zinc-400 flex items-center gap-2">
                                <span
                                    class="px-1.5 py-0.5 rounded bg-zinc-100 dark:bg-zinc-700 font-medium text-[10px]">
                                    {{ $bayar->metode->label() }}
                                </span>
                                <span>&bull;</span>
                                <span>{{ $bayar->dibayar_pada?->translatedFormat('d M Y, H:i') ?? '-' }}</span>
                            </div>
                        </div>
                        <div class="text-right shrink-0">
                            <div class="font-bold text-emerald-600 dark:text-emerald-400 text-sm">
                                +Rp {{ number_format((float) $bayar->jumlah_dibayar, 0, ',', '.') }}
                            </div>
                            <div class="text-[10px] text-zinc-400">
                                @if ($bayar->invoice?->pelanggan_id)
                                    <a href="{{ route('pelanggan.show', $bayar->invoice->pelanggan_id) }}"
                                        class="text-indigo-600 dark:text-indigo-400 hover:underline" wire:navigate>
                                        Detail Pelanggan
                                    </a>
                                @endif
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="py-8 text-center text-xs text-zinc-400 dark:text-zinc-500">
                        Belum ada riwayat pembayaran yang tercatat.
                    </div>
                @endforelse
            </div>
        </div>

        {{-- Layanan Expired & Jatuh Tempo (H-7) --}}
        <div
            class="p-5 rounded-2xl bg-white dark:bg-zinc-800 border border-zinc-200/80 dark:border-zinc-700/80 shadow-xs space-y-4">
            <div class="flex items-center justify-between">
                <div>
                    <div class="flex items-center gap-2">
                        <flux:heading size="lg">Pelanggan Expired & Jatuh Tempo</flux:heading>
                        @if ($kpis['expired_count'] > 0)
                            <span
                                class="px-2 py-0.5 text-[10px] font-bold rounded-full bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-300">
                                {{ $kpis['expired_count'] }} Expired
                            </span>
                        @endif
                    </div>
                    <flux:subheading>Layanan yang telah kadaluarsa atau mendekati jatuh tempo (H-7).</flux:subheading>
                </div>
                <flux:button :href="route('layanan-pelanggan.index')" variant="ghost" size="xs" wire:navigate>
                    Kelola Layanan &rarr;
                </flux:button>
            </div>

            <div class="divide-y divide-zinc-100 dark:divide-zinc-700/60">
                @forelse($expiredServices as $layanan)
                    @php
                        $isOverdue = $layanan->tanggal_expired && $layanan->tanggal_expired->isPast();
                        $diffDays = $layanan->tanggal_expired
                            ? abs(
                                (int) now()
                                    ->startOfDay()
                                    ->diffInDays($layanan->tanggal_expired->startOfDay(), false),
                            )
                            : 0;
                    @endphp
                    <a href="{{ route('pelanggan.show', $layanan->pelanggan_id) }}"
                        class="py-3 block group hover:bg-zinc-50 dark:hover:bg-zinc-700/30 rounded-lg px-2 transition text-xs"
                        wire:navigate>
                        <div class="flex items-center justify-between gap-2">
                            <div
                                class="font-semibold text-zinc-900 dark:text-zinc-100 truncate group-hover:text-indigo-600 dark:group-hover:text-indigo-400 flex items-center gap-2">
                                <span>{{ $layanan->pelanggan?->identitasLengkap() ?? 'Pelanggan #' . $layanan->pelanggan_id }}</span>
                                <span
                                    class="text-[10px] font-mono text-zinc-400">({{ $layanan->site_id }})</span>
                            </div>
                            @if ($isOverdue)
                                <span
                                    class="shrink-0 px-2 py-0.5 rounded-full text-[10px] font-bold uppercase bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-300">
                                    Expired {{ $diffDays > 0 ? $diffDays . 'h lalu' : 'hari ini' }}
                                </span>
                            @else
                                <span
                                    class="shrink-0 px-2 py-0.5 rounded-full text-[10px] font-bold uppercase bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300">
                                    H-{{ $diffDays }} Jatuh Tempo
                                </span>
                            @endif
                        </div>
                        <div
                            class="flex items-center justify-between text-[11px] text-zinc-500 dark:text-zinc-400 mt-1.5">
                            <span class="flex items-center gap-1.5">
                                <span
                                    class="font-medium text-zinc-700 dark:text-zinc-300">{{ $layanan->paketLayanan?->nama_paket ?? '-' }}</span>
                                <span>&bull;</span>
                                <span>Router: {{ $layanan->router?->nama_router ?? '-' }}</span>
                            </span>
                            <span class="font-mono text-zinc-400">
                                Exp: {{ $layanan->tanggal_expired?->translatedFormat('d M Y') ?? '-' }}
                            </span>
                        </div>
                    </a>
                @empty
                    <div class="py-8 text-center text-xs text-zinc-400 dark:text-zinc-500">
                        Tidak ada layanan yang expired atau mendekati jatuh tempo dlm 7 hari ke depan.
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</div>

@script
<script>
    // 1. Dual-Axis Daily Trend Chart (Area Pendapatan + Bar Transaksi)
    Alpine.data('dailyTrendChart', (config) => ({
        chart: null,
        initChart() {
            const isDark = document.documentElement.classList.contains('dark');
            const options = {
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
                    mode: isDark ? 'dark' : 'light',
                },
                series: [{
                        name: 'Pendapatan (Rp)',
                        type: 'area',
                        data: config.revenue,
                    },
                    {
                        name: 'Jumlah Transaksi',
                        type: 'column',
                        data: config.transactions,
                    }
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
                        stops: [0, 90, 100],
                    },
                    opacity: [0.35, 0.85]
                },
                plotOptions: {
                    bar: {
                        columnWidth: '35%',
                        borderRadius: 4,
                    }
                },
                xaxis: {
                    categories: config.categories,
                    labels: {
                        style: {
                            colors: isDark ? '#a1a1aa' : '#71717a',
                            fontSize: '11px',
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
                                color: isDark ? '#a1a1aa' : '#71717a',
                                fontSize: '11px',
                            }
                        },
                        labels: {
                            formatter: function(value) {
                                if (value >= 1000000) return (value / 1000000)
                                    .toFixed(1) + 'M';
                                if (value >= 1000) return (value / 1000).toFixed(
                                    0) + 'k';
                                return value;
                            },
                            style: {
                                colors: isDark ? '#a1a1aa' : '#71717a',
                                fontSize: '11px',
                            },
                        },
                    },
                    {
                        opposite: true,
                        title: {
                            text: 'Transaksi',
                            style: {
                                color: isDark ? '#a1a1aa' : '#71717a',
                                fontSize: '11px',
                            }
                        },
                        labels: {
                            formatter: function(value) {
                                return Math.round(value) + ' trx';
                            },
                            style: {
                                colors: isDark ? '#a1a1aa' : '#71717a',
                                fontSize: '11px',
                            },
                        },
                    }
                ],
                dataLabels: {
                    enabled: false
                },
                grid: {
                    borderColor: isDark ? '#27272a' : '#f4f4f5',
                    strokeDashArray: 4,
                },
                tooltip: {
                    theme: isDark ? 'dark' : 'light',
                    shared: true,
                    intersect: false,
                    y: {
                        formatter: function(val, {
                            seriesIndex
                        }) {
                            if (seriesIndex === 0) {
                                return 'Rp ' + new Intl.NumberFormat('id-ID').format(
                                    val);
                            }
                            return val + ' transaksi';
                        },
                    },
                },
                legend: {
                    position: 'top',
                    horizontalAlign: 'right',
                    labels: {
                        colors: isDark ? '#e4e4e7' : '#3f3f46',
                    },
                },
            };

            this.chart = new ApexCharts(this.$refs.chart, options);
            this.chart.render();
        },
    }));

    // 2. Paket Donut Chart
    Alpine.data('paketChart', (config) => ({
        chart: null,
        initChart() {
            const isDark = document.documentElement.classList.contains('dark');
            const options = {
                chart: {
                    type: 'donut',
                    height: 290,
                    fontFamily: 'inherit',
                    background: 'transparent',
                },
                theme: {
                    mode: isDark ? 'dark' : 'light',
                },
                labels: config.labels,
                series: config.series,
                colors: ['#6366f1', '#10b981', '#f59e0b', '#ec4899', '#8b5cf6', '#06b6d4'],
                legend: {
                    position: 'bottom',
                    labels: {
                        colors: isDark ? '#e4e4e7' : '#3f3f46',
                        fontSize: '11px',
                    },
                },
                dataLabels: {
                    enabled: false,
                },
                plotOptions: {
                    pie: {
                        donut: {
                            size: '72%',
                            labels: {
                                show: true,
                                name: {
                                    show: true,
                                    fontSize: '12px',
                                    color: isDark ? '#a1a1aa' : '#71717a',
                                },
                                value: {
                                    show: true,
                                    fontSize: '18px',
                                    fontWeight: 'bold',
                                    color: isDark ? '#ffffff' : '#09090b',
                                },
                                total: {
                                    show: true,
                                    label: 'Langganan',
                                    color: isDark ? '#a1a1aa' : '#71717a',
                                },
                            },
                        },
                    },
                },
                stroke: {
                    colors: [isDark ? '#27272a' : '#ffffff'],
                    width: 2,
                },
            };

            this.chart = new ApexCharts(this.$refs.chart, options);
            this.chart.render();
        },
    }));
</script>
@endscript
