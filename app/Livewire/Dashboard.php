<?php

namespace App\Livewire;

use App\Enums\StatusInvoice;
use App\Enums\StatusPelanggan;
use App\Enums\StatusRouter;
use App\Enums\Ticket\PrioritasTicket;
use App\Enums\Ticket\StatusTicket;
use App\Models\Invoice;
use App\Models\LayananPelanggan;
use App\Models\Pelanggan;
use App\Models\Pembayaran;
use App\Models\Router;
use App\Models\Ticket;
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
        [$startDate, $endDate, $prevStartDate, $prevEndDate] = $this->getDateRange();

        // 1. Revenue
        $revenue = (float) Pembayaran::whereBetween('dibayar_pada', [$startDate, $endDate])->sum('jumlah_dibayar');
        $prevRevenue = (float) Pembayaran::whereBetween('dibayar_pada', [$prevStartDate, $prevEndDate])->sum('jumlah_dibayar');

        $revenueGrowth = 0.0;
        if ($prevRevenue > 0) {
            $revenueGrowth = round((($revenue - $prevRevenue) / $prevRevenue) * 100, 1);
        } elseif ($revenue > 0) {
            $revenueGrowth = 100.0;
        }

        // 2. Invoices & Unpaid
        $unpaidInvoices = Invoice::where('status', StatusInvoice::MenungguPembayaran);
        $unpaidAmount = (float) $unpaidInvoices->sum('jumlah_setelah_promo');
        $unpaidCount = $unpaidInvoices->count();
        $overdueCount = Invoice::where('status', StatusInvoice::MenungguPembayaran)
            ->where('tanggal_jatuh_tempo', '<', now()->startOfDay())
            ->count();

        // 3. Customers
        $totalCustomers = Pelanggan::count();
        $activeCustomers = Pelanggan::where('status', StatusPelanggan::Aktif)->count();
        $prospectCustomers = Pelanggan::where('status', StatusPelanggan::Prospek)->count();
        $newCustomers = Pelanggan::whereBetween('created_at', [$startDate, $endDate])->count();

        // 4. Tickets & Infrastructure
        $openTickets = Ticket::whereIn('status', [
            StatusTicket::Baru,
            StatusTicket::Diproses,
            StatusTicket::MenungguKonfirmasi,
        ])->count();

        $criticalTickets = Ticket::whereIn('status', [StatusTicket::Baru, StatusTicket::Diproses])
            ->whereIn('prioritas', [PrioritasTicket::Tinggi, PrioritasTicket::Darurat])
            ->count();

        $totalRouters = Router::count();
        $onlineRouters = Router::where('status_koneksi', StatusRouter::Online)->count();

        return [
            'revenue' => $revenue,
            'prev_revenue' => $prevRevenue,
            'revenue_growth' => $revenueGrowth,
            'unpaid_amount' => $unpaidAmount,
            'unpaid_count' => $unpaidCount,
            'overdue_count' => $overdueCount,
            'total_customers' => $totalCustomers,
            'active_customers' => $activeCustomers,
            'prospect_customers' => $prospectCustomers,
            'new_customers' => $newCustomers,
            'open_tickets' => $openTickets,
            'critical_tickets' => $criticalTickets,
            'total_routers' => $totalRouters,
            'online_routers' => $onlineRouters,
        ];
    }

    /**
     * 12-Month Revenue & Billed Trend.
     *
     * @return array{categories: array<string>, revenue: array<float>, billed: array<float>}
     */
    public function getRevenueChartDataProperty(): array
    {
        $categories = [];
        $revenueData = [];
        $billedData = [];

        for ($i = 11; $i >= 0; $i--) {
            $month = now()->subMonths($i);
            $start = $month->copy()->startOfMonth();
            $end = $month->copy()->endOfMonth();

            $categories[] = $month->translatedFormat('M Y');

            $rev = (float) Pembayaran::whereBetween('dibayar_pada', [$start, $end])->sum('jumlah_dibayar');
            $billed = (float) Invoice::whereBetween('tanggal_terbit', [$start, $end])->sum('jumlah_setelah_promo');

            $revenueData[] = $rev;
            $billedData[] = $billed;
        }

        return [
            'categories' => $categories,
            'revenue' => $revenueData,
            'billed' => $billedData,
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
     * Ticket volume breakdown by type (Bar chart).
     *
     * @return array{categories: array<string>, series: array<int>}
     */
    public function getTicketChartDataProperty(): array
    {
        $types = [
            'pemasangan' => 'Pemasangan',
            'gangguan' => 'Gangguan',
            'pindah_alamat' => 'Pindah Alamat',
            'pencabutan' => 'Pencabutan',
        ];

        $categories = array_values($types);
        $series = [];

        foreach (array_keys($types) as $type) {
            $series[] = Ticket::where('jenis', $type)->count();
        }

        return [
            'categories' => $categories,
            'series' => $series,
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
     * Urgent tickets requiring attention.
     */
    public function getUrgentTicketsProperty(): Collection
    {
        return Ticket::with(['pelanggan', 'pic'])
            ->whereIn('status', [StatusTicket::Baru, StatusTicket::Diproses])
            ->orderByRaw("CASE 
                WHEN prioritas = 'darurat' THEN 1 
                WHEN prioritas = 'tinggi' THEN 2 
                WHEN prioritas = 'sedang' THEN 3 
                ELSE 4 END")
            ->latest()
            ->take(5)
            ->get();
    }

    public function render(): View
    {
        return view('livewire.dashboard', [
            'kpis' => $this->kpis,
            'revenueChart' => $this->revenueChartData,
            'paketChart' => $this->paketChartData,
            'ticketChart' => $this->ticketChartData,
            'recentPayments' => $this->recentPayments,
            'urgentTickets' => $this->urgentTickets,
        ]);
    }
}
