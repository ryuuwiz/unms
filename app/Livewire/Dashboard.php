<?php

namespace App\Livewire;

use App\Enums\StatusInvoice;
use App\Enums\StatusPelanggan;
use App\Models\Invoice;
use App\Models\LayananPelanggan;
use App\Models\Pelanggan;
use App\Models\Pembayaran;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Dashboard Operasional & Billing')]
class Dashboard extends Component
{
    public string $period = 'this_month'; // 'this_month', 'last_30_days', 'this_year', 'all'

    public function setPeriod(string $period): void
    {
        $this->period = $period;
    }

    /**
     * Get date range based on selected period.
     *
     * @return array{0: Carbon, 1: Carbon, 2: Carbon, 3: Carbon}
     */
    protected function getDateRange(): array
    {
        $now = now();

        return match ($this->period) {
            'last_30_days' => [
                $now->copy()->subDays(30)->startOfDay(),
                $now->copy()->endOfDay(),
                $now->copy()->subDays(60)->startOfDay(),
                $now->copy()->subDays(30)->endOfDay(),
            ],
            'this_year' => [
                $now->copy()->startOfYear(),
                $now->copy()->endOfYear(),
                $now->copy()->subYear()->startOfYear(),
                $now->copy()->subYear()->endOfYear(),
            ],
            'all' => [
                Carbon::createFromTimestamp(0),
                $now->copy()->endOfDay(),
                Carbon::createFromTimestamp(0),
                $now->copy()->endOfDay(),
            ],
            default => [ // 'this_month'
                $now->copy()->startOfMonth(),
                $now->copy()->endOfMonth(),
                $now->copy()->subMonth()->startOfMonth(),
                $now->copy()->subMonth()->endOfMonth(),
            ],
        };
    }

    /**
     * Compute Top KPI Metrics.
     *
     * @return array<string, mixed>
     */
    public function getKpisProperty(): array
    {
        $now = now();

        // 1. Total Pelanggan & Breakdown
        $totalCustomers = Pelanggan::count();
        $activeCustomers = Pelanggan::where('status', StatusPelanggan::Aktif)->count();
        $inactiveCustomers = Pelanggan::where('status', StatusPelanggan::TidakAktif)->count();
        $prospectCustomers = Pelanggan::where('status', StatusPelanggan::Prospek)->count();

        // 2. Pendapatan Hari Ini vs Kemarin
        $todayStart = $now->copy()->startOfDay();
        $todayEnd = $now->copy()->endOfDay();
        $yesterdayStart = $now->copy()->subDay()->startOfDay();
        $yesterdayEnd = $now->copy()->subDay()->endOfDay();

        $todayRevenue = (float) Pembayaran::whereBetween('dibayar_pada', [$todayStart, $todayEnd])->sum('jumlah_dibayar');
        $yesterdayRevenue = (float) Pembayaran::whereBetween('dibayar_pada', [$yesterdayStart, $yesterdayEnd])->sum('jumlah_dibayar');

        $todayGrowth = 0.0;
        if ($yesterdayRevenue > 0) {
            $todayGrowth = round((($todayRevenue - $yesterdayRevenue) / $yesterdayRevenue) * 100, 1);
        } elseif ($todayRevenue > 0) {
            $todayGrowth = 100.0;
        }

        // 3. Pendapatan Bulan Ini vs Bulan Lalu
        $monthStart = $now->copy()->startOfMonth();
        $monthEnd = $now->copy()->endOfMonth();
        $prevMonthStart = $now->copy()->subMonth()->startOfMonth();
        $prevMonthEnd = $now->copy()->subMonth()->endOfMonth();

        $monthRevenue = (float) Pembayaran::whereBetween('dibayar_pada', [$monthStart, $monthEnd])->sum('jumlah_dibayar');
        $prevMonthRevenue = (float) Pembayaran::whereBetween('dibayar_pada', [$prevMonthStart, $prevMonthEnd])->sum('jumlah_dibayar');

        $monthGrowth = 0.0;
        if ($prevMonthRevenue > 0) {
            $monthGrowth = round((($monthRevenue - $prevMonthRevenue) / $prevMonthRevenue) * 100, 1);
        } elseif ($monthRevenue > 0) {
            $monthGrowth = 100.0;
        }

        // 4. Ringkasan Tagihan Bulan Ini
        $thisMonthInvoices = Invoice::whereBetween('tanggal_terbit', [$monthStart, $monthEnd]);
        $thisMonthBilled = (float) (clone $thisMonthInvoices)->sum('jumlah_setelah_promo');
        $thisMonthPaid = (float) (clone $thisMonthInvoices)->where('status', StatusInvoice::Lunas)->sum('jumlah_setelah_promo');
        $thisMonthUnpaid = (float) (clone $thisMonthInvoices)->where('status', StatusInvoice::MenungguPembayaran)->sum('jumlah_setelah_promo');
        $thisMonthPaidCount = (int) (clone $thisMonthInvoices)->where('status', StatusInvoice::Lunas)->count();
        $thisMonthUnpaidCount = (int) (clone $thisMonthInvoices)->where('status', StatusInvoice::MenungguPembayaran)->count();

        $collectionRate = $thisMonthBilled > 0
            ? round(($thisMonthPaid / $thisMonthBilled) * 100, 1)
            : 0.0;

        // 5. Layanan Expired & Mendekati Jatuh Tempo
        $expiredCount = LayananPelanggan::where('tanggal_expired', '<=', $now->copy()->endOfDay())->count();
        $expiringSoonCount = LayananPelanggan::whereBetween('tanggal_expired', [
            $now->copy()->addDay()->startOfDay(),
            $now->copy()->addDays(7)->endOfDay(),
        ])->count();

        return [
            'total_customers' => $totalCustomers,
            'active_customers' => $activeCustomers,
            'inactive_customers' => $inactiveCustomers,
            'prospect_customers' => $prospectCustomers,

            'today_revenue' => $todayRevenue,
            'yesterday_revenue' => $yesterdayRevenue,
            'today_growth' => $todayGrowth,

            'month_revenue' => $monthRevenue,
            'prev_month_revenue' => $prevMonthRevenue,
            'month_growth' => $monthGrowth,

            'this_month_billed' => $thisMonthBilled,
            'this_month_paid' => $thisMonthPaid,
            'this_month_unpaid' => $thisMonthUnpaid,
            'this_month_paid_count' => $thisMonthPaidCount,
            'this_month_unpaid_count' => $thisMonthUnpaidCount,
            'collection_rate' => $collectionRate,

            'expired_count' => $expiredCount,
            'expiring_soon_count' => $expiringSoonCount,
        ];
    }

    /**
     * Daily Revenue & Transaction Count Trend (Dual-Axis Chart for this month or selected period).
     *
     * @return array{categories: array<string>, revenue: array<float>, transactions: array<int>}
     */
    public function getDailyTrendChartDataProperty(): array
    {
        $categories = [];
        $revenueData = [];
        $transactionData = [];

        // Generate daily data points for the current month
        $start = now()->startOfMonth();
        $end = now()->endOfMonth();
        $current = $start;

        $payments = Pembayaran::query()
            ->whereBetween('dibayar_pada', [$start->startOfDay(), now()->endOfDay()])
            ->selectRaw('DATE(dibayar_pada) as payment_date, SUM(jumlah_dibayar) as total_revenue, COUNT(*) as total_trx')
            ->groupByRaw('DATE(dibayar_pada)')
            ->get()
            ->keyBy(fn ($item) => Carbon::parse($item->payment_date)->format('Y-m-d'));

        while ($current->lte($end)) {
            $categories[] = $current->translatedFormat('d M');
            $dateKey = $current->format('Y-m-d');

            // If day is in the future, don't query future data, leave 0 or actual
            if ($current->isFuture() && ! $current->isToday()) {
                $revenueData[] = 0.0;
                $transactionData[] = 0;
            } else {
                $dayPayment = $payments->get($dateKey);
                $revenueData[] = (float) ($dayPayment->total_revenue ?? 0.0);
                $transactionData[] = (int) ($dayPayment->total_trx ?? 0);
            }

            $current = $current->addDay();
        }

        return [
            'categories' => $categories,
            'revenue' => $revenueData,
            'transactions' => $transactionData,
        ];
    }

    /**
     * Subscription package distribution (Donut chart).
     *
     * @return array{labels: array<string>, series: array<int>}
     */
    public function getPaketChartDataProperty(): array
    {
        $distribution = LayananPelanggan::query()
            ->join('paket_layanan', 'layanan_pelanggan.paket_layanan_id', '=', 'paket_layanan.id')
            ->select('paket_layanan.nama_paket', DB::raw('count(*) as total'))
            ->groupBy('paket_layanan.nama_paket')
            ->orderByDesc('total')
            ->take(6)
            ->get();

        if ($distribution->isEmpty()) {
            return [
                'labels' => ['Belum Ada Data'],
                'series' => [1],
            ];
        }

        return [
            'labels' => $distribution->pluck('nama_paket')->toArray(),
            'series' => $distribution->pluck('total')->map(fn ($v) => (int) $v)->toArray(),
        ];
    }

    /**
     * Recent payments (5 latest).
     */
    public function getRecentPaymentsProperty(): Collection
    {
        return Pembayaran::with(['invoice.pelanggan'])
            ->latest('dibayar_pada')
            ->take(5)
            ->get();
    }

    /**
     * Expired & Expiring Soon Services (within 7 days).
     */
    public function getExpiredServicesProperty(): Collection
    {
        return LayananPelanggan::with(['pelanggan', 'paketLayanan', 'router'])
            ->where('tanggal_expired', '<=', now()->addDays(7)->endOfDay())
            ->orderBy('tanggal_expired', 'asc')
            ->take(6)
            ->get();
    }

    public function render(): View
    {
        return view('livewire.dashboard', [
            'kpis' => $this->kpis,
            'dailyTrendChart' => $this->dailyTrendChartData,
            'paketChart' => $this->paketChartData,
            'recentPayments' => $this->recentPayments,
            'expiredServices' => $this->expiredServices,
        ]);
    }
}
