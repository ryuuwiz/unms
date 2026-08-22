<div class="space-y-6">
    {{-- Header & Period Filter --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl" class="font-bold">Dashboard Operasional & Billing</flux:heading>
            <flux:subheading>Ringkasan performa finansial, data langganan, beban tiket, dan status infrastruktur ISP.
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
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-2">
        {{-- 1. Pendapatan Terkumpul --}}
        <x-stat-card title="Pendapatan Terkumpul" :value="'Rp ' . number_format($kpis['revenue'], 0, ',', '.')" :trend="($kpis['revenue_growth'] >= 0 ? '+' : '') . $kpis['revenue_growth'] . '%'" :trend-type="$kpis['revenue_growth'] >= 0 ? 'up' : 'down'"
            trend-label="vs periode lalu" :subvalue="'Bulan Lalu: Rp ' . number_format($kpis['prev_revenue'], 0, ',', '.')" icon="currency-dollar" color="emerald" :href="route('pembayaran.index')" />

        {{-- 2. Pelanggan Aktif --}}
        <x-stat-card title="Pelanggan Aktif" :value="$kpis['active_customers'] . ' Pelanggan'" :trend="'+' . $kpis['new_customers'] . ' Baru'" trend-type="up"
            trend-label="di periode ini" :subvalue="'Total: ' . $kpis['total_customers'] . ' | Prospek: ' . $kpis['prospect_customers']" icon="users" color="indigo" :href="route('pelanggan.index')" />

        {{-- 3. Tagihan Tertunggak --}}
        <x-stat-card title="Tagihan Menunggu" :value="'Rp ' . number_format($kpis['unpaid_amount'], 0, ',', '.')" :trend="$kpis['overdue_count'] > 0 ? $kpis['overdue_count'] . ' Jatuh Tempo' : null" :trend-type="$kpis['overdue_count'] > 0 ? 'down' : 'neutral'"
            trend-label="perlu ditagih" :subvalue="$kpis['unpaid_count'] . ' Tagihan Belum Lunas'" icon="document-text" color="amber" :href="route('invoice.index')" />

        {{-- 4. Tiket & Status Jaringan --}}
        <x-stat-card title="Tiket Terbuka & Jaringan" :value="$kpis['open_tickets'] . ' Tiket'" :trend="$kpis['critical_tickets'] > 0 ? $kpis['critical_tickets'] . ' Kritis' : 'Normal'" :trend-type="$kpis['critical_tickets'] > 0 ? 'down' : 'up'"
            trend-label="perlu tindakan" :subvalue="'Router: ' . $kpis['online_routers'] . '/' . $kpis['total_routers'] . ' Online'" icon="bolt" color="rose" :href="route('ticket.index')" />
    </div>

    {{-- Row 2: Charts Area --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Tren Pendapatan 12 Bulan (Area Chart) --}}
        <div
            class="lg:col-span-2 p-5 rounded-2xl bg-white dark:bg-zinc-800 border border-zinc-200/80 dark:border-zinc-700/80 shadow-xs flex flex-col justify-between">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <flux:heading size="lg">Tren Pendapatan & Penagihan</flux:heading>
                    <flux:subheading>Perbandingan pembayaran lunas vs total tagihan diterbitkan (12 Bulan Terakhir).
                    </flux:subheading>
                </div>
                <span
                    class="text-xs px-2.5 py-1 rounded-full bg-indigo-50 dark:bg-indigo-950/70 text-indigo-600 dark:text-indigo-400 font-semibold border border-indigo-200/50 dark:border-indigo-800/40">
                    Finansial
                </span>
            </div>

            <div wire:ignore x-data="revenueChart({
                categories: @js($revenueChart['categories']),
                revenue: @js($revenueChart['revenue']),
                billed: @js($revenueChart['billed']),
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
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Beban Tiket Layanan (Bar Chart) --}}
        <div
            class="p-5 rounded-2xl bg-white dark:bg-zinc-800 border border-zinc-200/80 dark:border-zinc-700/80 shadow-xs flex flex-col justify-between">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <flux:heading size="lg">Beban Tiket & Gangguan</flux:heading>
                    <flux:subheading>Volume tiket berdasarkan kategori layanan.</flux:subheading>
                </div>
                <span
                    class="text-xs px-2.5 py-1 rounded-full bg-rose-50 dark:bg-rose-950/70 text-rose-600 dark:text-rose-400 font-semibold border border-rose-200/50 dark:border-rose-800/40">
                    Support
                </span>
            </div>

            <div wire:ignore x-data="ticketChart({
                categories: @js($ticketChart['categories']),
                series: @js($ticketChart['series']),
            })" x-init="initChart()" class="min-h-[260px] w-full">
                <div x-ref="chart"></div>
            </div>
        </div>

        {{-- 5 Pembayaran Terkini --}}
        <div
            class="p-5 rounded-2xl bg-white dark:bg-zinc-800 border border-zinc-200/80 dark:border-zinc-700/80 shadow-xs space-y-4">
            <div class="flex items-center justify-between">
                <div>
                    <flux:heading size="lg">Pembayaran Terbaru</flux:heading>
                    <flux:subheading>5 transaksi pembayaran terakhir yang diterima sistem.</flux:subheading>
                </div>
                <flux:button :href="route('pembayaran.index')" variant="ghost" size="xs" wire:navigate>
                    Lihat Semua &rarr;
                </flux:button>
            </div>

            <div class="divide-y divide-zinc-100 dark:divide-zinc-700/60">
                @forelse($recentPayments as $bayar)
                    <div class="py-2.5 flex items-center justify-between gap-3 text-xs">
                        <div class="min-w-0 space-y-0.5">
                            <div class="font-semibold text-zinc-900 dark:text-zinc-100 truncate">
                                {{ $bayar->invoice?->pelanggan?->nama_lengkap ?? 'Pelanggan #' . $bayar->invoice_id }}
                            </div>
                            <div class="text-[11px] text-zinc-500 dark:text-zinc-400">
                                {{ $bayar->metode->label() }} &bull; {{ $bayar->dibayar_pada?->format('d M H:i') }}
                            </div>
                        </div>
                        <div class="text-right shrink-0">
                            <div class="font-bold text-emerald-600 dark:text-emerald-400">
                                +Rp {{ number_format((float) $bayar->jumlah_dibayar, 0, ',', '.') }}
                            </div>
                            <div class="text-[10px] text-zinc-400">
                                {{ $bayar->invoice?->no_invoice }}
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="py-6 text-center text-xs text-zinc-400 dark:text-zinc-500">
                        Belum ada riwayat pembayaran yang tercatat.
                    </div>
                @endforelse
            </div>
        </div>

        {{-- 5 Tiket Butuh Penanganan --}}
        <div
            class="p-5 rounded-2xl bg-white dark:bg-zinc-800 border border-zinc-200/80 dark:border-zinc-700/80 shadow-xs space-y-4">
            <div class="flex items-center justify-between">
                <div>
                    <flux:heading size="lg">Tiket Mendesak</flux:heading>
                    <flux:subheading>Tiket aktif berprioritas tinggi butuh penanganan segera.</flux:subheading>
                </div>
                <flux:button :href="route('ticket.index')" variant="ghost" size="xs" wire:navigate>
                    Kelola Tiket &rarr;
                </flux:button>
            </div>

            <div class="divide-y divide-zinc-100 dark:divide-zinc-700/60">
                @forelse($urgentTickets as $tck)
                    <a href="{{ route('ticket.show', $tck) }}"
                        class="py-2.5 block group hover:bg-zinc-50 dark:hover:bg-zinc-700/30 rounded-lg px-1 transition text-xs"
                        wire:navigate>
                        <div class="flex items-center justify-between gap-2">
                            <div
                                class="font-semibold text-zinc-900 dark:text-zinc-100 truncate group-hover:text-indigo-600 dark:group-hover:text-indigo-400">
                                {{ $tck->nomor_ticket }} &bull; {{ $tck->judul }}
                            </div>
                            <span
                                class="shrink-0 px-2 py-0.5 rounded-full text-[10px] font-bold uppercase {{ $tck->prioritas->value === 'darurat' ? 'bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-300' : ($tck->prioritas->value === 'tinggi' ? 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300' : 'bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300') }}">
                                {{ $tck->prioritas->label() }}
                            </span>
                        </div>
                        <div
                            class="flex items-center justify-between text-[11px] text-zinc-500 dark:text-zinc-400 mt-1">
                            <span>{{ $tck->pelanggan?->nama_lengkap ?? '-' }}</span>
                            <span>PIC: {{ $tck->pic?->name ?? 'Belum ada' }}</span>
                        </div>
                    </a>
                @empty
                    <div class="py-6 text-center text-xs text-zinc-400 dark:text-zinc-500">
                        Tidak ada tiket kritis yang menunggu penanganan.
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('alpine:init', () => {
        // 1. Revenue Area Chart
        Alpine.data('revenueChart', (config) => ({
            chart: null,
            initChart() {
                const isDark = document.documentElement.classList.contains('dark');
                const options = {
                    chart: {
                        type: 'area',
                        height: 310,
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
                            name: 'Pendapatan Diterima',
                            data: config.revenue,
                        },
                        {
                            name: 'Total Ditagihkan',
                            data: config.billed,
                        }
                    ],
                    xaxis: {
                        categories: config.categories,
                        labels: {
                            style: {
                                colors: isDark ? '#a1a1aa' : '#71717a',
                                fontSize: '11px',
                            },
                        },
                        axisBorder: {
                            show: false
                        },
                        axisTicks: {
                            show: false
                        },
                    },
                    yaxis: {
                        labels: {
                            formatter: function(value) {
                                if (value >= 1000000) return (value / 1000000).toFixed(1) +
                                    'M';
                                if (value >= 1000) return (value / 1000).toFixed(0) + 'k';
                                return value;
                            },
                            style: {
                                colors: isDark ? '#a1a1aa' : '#71717a',
                                fontSize: '11px',
                            },
                        },
                    },
                    colors: ['#10b981', '#6366f1'],
                    fill: {
                        type: 'gradient',
                        gradient: {
                            shadeIntensity: 1,
                            opacityFrom: 0.45,
                            opacityTo: 0.05,
                            stops: [0, 90, 100],
                        },
                    },
                    stroke: {
                        curve: 'smooth',
                        width: 2.5,
                    },
                    dataLabels: {
                        enabled: false
                    },
                    grid: {
                        borderColor: isDark ? '#27272a' : '#f4f4f5',
                        strokeDashArray: 4,
                    },
                    tooltip: {
                        theme: isDark ? 'dark' : 'light',
                        y: {
                            formatter: function(val) {
                                return 'Rp ' + new Intl.NumberFormat('id-ID').format(val);
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

        // 3. Ticket Bar Chart
        Alpine.data('ticketChart', (config) => ({
            chart: null,
            initChart() {
                const isDark = document.documentElement.classList.contains('dark');
                const options = {
                    chart: {
                        type: 'bar',
                        height: 240,
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
                        name: 'Jumlah Tiket',
                        data: config.series,
                    }],
                    plotOptions: {
                        bar: {
                            borderRadius: 6,
                            columnWidth: '45%',
                            distributed: true,
                        },
                    },
                    colors: ['#3b82f6', '#ef4444', '#f59e0b', '#8b5cf6'],
                    dataLabels: {
                        enabled: false
                    },
                    legend: {
                        show: false
                    },
                    xaxis: {
                        categories: config.categories,
                        labels: {
                            style: {
                                colors: isDark ? '#a1a1aa' : '#71717a',
                                fontSize: '11px',
                            },
                        },
                        axisBorder: {
                            show: false
                        },
                        axisTicks: {
                            show: false
                        },
                    },
                    yaxis: {
                        labels: {
                            style: {
                                colors: isDark ? '#a1a1aa' : '#71717a',
                                fontSize: '11px',
                            },
                        },
                    },
                    grid: {
                        borderColor: isDark ? '#27272a' : '#f4f4f5',
                        strokeDashArray: 4,
                    },
                };

                this.chart = new ApexCharts(this.$refs.chart, options);
                this.chart.render();
            },
        }));
    });
</script>
