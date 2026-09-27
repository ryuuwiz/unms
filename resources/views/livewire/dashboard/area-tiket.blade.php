<div class="space-y-6" wire:poll.120s>
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3 items-start">
        <x-dashboard-panel judul="Ringkasan Tiket Saya" subjudul="Tiket terbuka di antrian kamu per status.">
            <div class="flex items-baseline gap-2">
                <span class="text-3xl font-bold text-zinc-900 dark:text-white">{{ $totalAntrian }}</span>
                <span class="text-sm text-zinc-500">tiket menunggu</span>
            </div>
            <x-bar-distribusi :segmen="$segmen" />
        </x-dashboard-panel>

        <x-dashboard-panel judul="Tren Tiket Harian" subjudul="Tiket masuk per hari, 30 hari terakhir." class="lg:col-span-2">
            <div wire:key="tren-tiket-{{ md5(json_encode($tren)) }}" x-data="trenTiketChart(@js($tren))" x-init="initChart()" class="min-h-[220px] w-full">
                <div wire:ignore x-ref="chart"></div>
            </div>
        </x-dashboard-panel>
    </div>

    <x-dashboard-panel judul="Antrian Tiket Saya" subjudul="Hingga 20 tiket terbuka terbaru yang menunggu tindakan kamu atau divisimu.">
        <x-slot:aksi>
            <flux:button :href="route('ticket.index')" variant="ghost" size="xs" wire:navigate>Lihat semua &rarr;</flux:button>
        </x-slot:aksi>

        @if ($antrian->isEmpty())
            <div class="py-8 flex flex-col items-center gap-2 text-xs text-zinc-400 dark:text-zinc-500">
                <flux:icon name="check-circle" class="size-6 text-emerald-500" />
                Tidak ada tiket yang menunggu kamu
            </div>
        @else
            <div class="-mx-5 overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <caption class="sr-only">Antrian tiket saya</caption>
                    <thead class="border-y border-zinc-200 bg-zinc-50 text-xs font-medium text-zinc-600 dark:border-zinc-700 dark:bg-zinc-900/50 dark:text-zinc-400">
                        <tr>
                            <th scope="col" class="px-5 py-2">Tiket</th>
                            <th scope="col" class="px-5 py-2">Pelanggan</th>
                            <th scope="col" class="px-5 py-2">Status</th>
                            <th scope="col" class="px-5 py-2">SLA</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-700/60">
                        @foreach ($antrian as $tck)
                            <tr wire:key="antrian-{{ $tck->id }}" x-on:click="if (! $event.target.closest('a')) Livewire.navigate('{{ route('ticket.show', $tck) }}')" class="cursor-pointer transition-colors hover:bg-zinc-50 dark:hover:bg-zinc-700/30">
                                <td class="px-5 py-2.5 align-top">
                                    <a href="{{ route('ticket.show', $tck) }}" wire:navigate class="font-mono font-semibold text-zinc-900 hover:text-blue-600 dark:text-white dark:hover:text-blue-400">{{ $tck->nomor_ticket }}</a>
                                    <div class="mt-1"><flux:badge size="sm" :color="$tck->jenis->color()" :icon="$tck->jenis->icon()">{{ $tck->jenis->label() }}</flux:badge></div>
                                </td>
                                <td class="px-5 py-2.5 align-top text-zinc-900 dark:text-white">{{ $tck->pelanggan?->identitasLengkap() ?? '-' }}</td>
                                <td class="px-5 py-2.5 align-top"><flux:badge size="sm" :color="$tck->status->color()">{{ $tck->status->label() }}</flux:badge></td>
                                <td class="px-5 py-2.5 align-top">@include('livewire.ticket.partials.sla', ['tck' => $tck])</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-dashboard-panel>
</div>

@script
    <script>
        Alpine.data('trenTiketChart', (config) => ({
            chart: null,
            initChart() {
                const isDark = document.documentElement.classList.contains('dark');
                const teks = isDark ? '#a1a1aa' : '#71717a';

                this.chart = new ApexCharts(this.$refs.chart, {
                    chart: { height: 220, type: 'bar', toolbar: { show: false }, fontFamily: 'inherit', background: 'transparent' },
                    theme: { mode: isDark ? 'dark' : 'light' },
                    series: [{ name: 'Tiket masuk', data: config.tiket }],
                    colors: ['#0ea5e9'],
                    plotOptions: { bar: { columnWidth: '55%', borderRadius: 3 } },
                    xaxis: {
                        categories: config.categories,
                        labels: { style: { colors: teks, fontSize: '11px' }, rotate: -45, rotateAlways: false },
                        axisBorder: { show: false },
                        axisTicks: { show: false },
                    },
                    yaxis: { labels: { formatter: (value) => Math.round(value), style: { colors: teks, fontSize: '11px' } } },
                    dataLabels: { enabled: false },
                    grid: { borderColor: isDark ? '#27272a' : '#f4f4f5', strokeDashArray: 4 },
                    tooltip: { theme: isDark ? 'dark' : 'light', y: { formatter: (val) => val + ' tiket' } },
                });

                this.chart.render();
            },
            destroy() {
                this.chart?.destroy();
            },
        }));
    </script>
@endscript
