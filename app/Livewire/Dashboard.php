<?php

namespace App\Livewire;

use App\Enums\StatusInvoice;
use App\Enums\StatusPelanggan;
use App\Models\Invoice;
use App\Models\LayananPelanggan;
use App\Models\Pelanggan;
use App\Models\Pembayaran;
use App\Models\PengaturanSiklusTagihan;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Dashboard Operasional & Billing')]
class Dashboard extends Component
{
    private const BARIS_DAFTAR = 8;

    private const BARIS_TRANSAKSI = 5;

    /**
     * Total Pelanggan: Aktif dan Tidak Aktif (off + expired); pelanggan calon tidak dihitung.
     *
     * @return array{total: int, aktif: int, tidak_aktif: int}|null
     */
    public function getPelangganProperty(): ?array
    {
        if (! auth()->user()->can('pelanggan.lihat')) {
            return null;
        }

        $jumlah = Pelanggan::query()
            ->toBase()
            ->whereIn('status', [StatusPelanggan::Aktif, StatusPelanggan::Off, StatusPelanggan::Expired])
            ->selectRaw('status, COUNT(*) as jumlah')
            ->groupBy('status')
            ->pluck('jumlah', 'status');

        $aktif = (int) ($jumlah[StatusPelanggan::Aktif->value] ?? 0);
        $tidakAktif = (int) ($jumlah[StatusPelanggan::Off->value] ?? 0) + (int) ($jumlah[StatusPelanggan::Expired->value] ?? 0);

        return ['total' => $aktif + $tidakAktif, 'aktif' => $aktif, 'tidak_aktif' => $tidakAktif];
    }

    /**
     * Pendapatan Diterima (kas): hari ini dibanding kemarin (nominal), bulan ini dibanding
     * rentang hari yang sama bulan lalu (persentase).
     *
     * @return array{hari_ini: float, kemarin: float, bulan_ini: float, pertumbuhan: float|null}|null
     */
    public function getPendapatanProperty(): ?array
    {
        if (! auth()->user()->can('pembayaran.lihat')) {
            return null;
        }

        $sekarang = now();
        $kemarin = $sekarang->copy()->subDay();
        $bulanLalu = $sekarang->copy()->subMonthNoOverflow();

        $bulanIni = $this->totalPembayaran($sekarang->copy()->startOfMonth(), $sekarang->copy()->endOfDay());
        $pembanding = $this->totalPembayaran($bulanLalu->copy()->startOfMonth(), $bulanLalu->endOfDay());

        return [
            'hari_ini' => $this->totalPembayaran($sekarang->copy()->startOfDay(), $sekarang->copy()->endOfDay()),
            'kemarin' => $this->totalPembayaran($kemarin->copy()->startOfDay(), $kemarin->endOfDay()),
            'bulan_ini' => $bulanIni,
            'pertumbuhan' => $pembanding > 0 ? round((($bulanIni - $pembanding) / $pembanding) * 100, 1) : null,
        ];
    }

    /**
     * Ringkasan Tagihan Periode bulan berjalan (tarif siklus tanpa tunggakan; invoice digabung
     * tetap dihitung) plus total Belum Dibayar seluruh periode.
     *
     * @return array{ditagih: float, lunas: float, belum_lunas: float, tertagih: float|null, belum_dibayar: float}|null
     */
    public function getTagihanProperty(): ?array
    {
        $user = auth()->user();

        if (! $user->can('invoice.lihat') || ! $user->can('pembayaran.lihat')) {
            return null;
        }

        $siklus = Invoice::query()
            ->where('periode_tagihan', now()->format('Y-m'))
            ->where('status', '!=', StatusInvoice::Dibatalkan)
            ->selectRaw('COALESCE(SUM(jumlah_setelah_promo - jumlah_tunggakan), 0) as ditagih')
            ->selectRaw('COALESCE(SUM(CASE WHEN status = ? THEN jumlah_setelah_promo - jumlah_tunggakan ELSE 0 END), 0) as lunas', [StatusInvoice::Lunas->value])
            ->first();

        $ditagih = (float) $siklus->ditagih;
        $lunas = (float) $siklus->lunas;

        return [
            'ditagih' => $ditagih,
            'lunas' => $lunas,
            'belum_lunas' => $ditagih - $lunas,
            'tertagih' => $ditagih > 0 ? round(($lunas / $ditagih) * 100, 1) : null,
            'belum_dibayar' => (float) Invoice::query()->whereIn('status', StatusInvoice::terbuka())->sum('jumlah_setelah_promo'),
        ];
    }

    /**
     * Tren Pendapatan Harian: pendapatan dan jumlah transaksi per hari, tanggal 1 sampai hari ini.
     *
     * @return array{categories: array<int, string>, revenue: array<int, float>, transactions: array<int, int>}|null
     */
    public function getTrenProperty(): ?array
    {
        if (! auth()->user()->can('pembayaran.lihat')) {
            return null;
        }

        $sekarang = now();

        $harian = Pembayaran::query()
            ->whereBetween('dibayar_pada', [$sekarang->copy()->startOfMonth(), $sekarang->copy()->endOfDay()])
            ->selectRaw('DATE(dibayar_pada) as tanggal, SUM(jumlah_dibayar) as total, COUNT(*) as jumlah')
            ->groupByRaw('DATE(dibayar_pada)')
            ->get()
            ->keyBy('tanggal');

        $tren = ['categories' => [], 'revenue' => [], 'transactions' => []];

        for ($hari = $sekarang->copy()->startOfMonth(); $hari->lte($sekarang); $hari = $hari->copy()->addDay()) {
            $data = $harian->get($hari->toDateString());

            $tren['categories'][] = $hari->translatedFormat('d M');
            $tren['revenue'][] = (float) ($data->total ?? 0);
            $tren['transactions'][] = (int) ($data->jumlah ?? 0);
        }

        return $tren;
    }

    /**
     * Transaksi Terbaru: pembayaran terakhir yang tercatat.
     *
     * @return Collection<int, Pembayaran>|null
     */
    public function getTransaksiTerbaruProperty(): ?Collection
    {
        if (! auth()->user()->can('pembayaran.lihat')) {
            return null;
        }

        return Pembayaran::with(['invoice.pelanggan'])
            ->latest('dibayar_pada')
            ->limit(self::BARIS_TRANSAKSI)
            ->get();
    }

    /**
     * Daftar Pelanggan Expired & Jatuh Tempo: layanan expired (maks. 30 hari) dan yang jatuh
     * tempo dalam lead time invoice.
     *
     * @return array{daftar: Collection<int, LayananPelanggan>, lewat: int, segera: int, lead: int}|null
     */
    public function getPerluPerhatianProperty(): ?array
    {
        if (! auth()->user()->can('layanan_pelanggan.lihat')) {
            return null;
        }

        $lead = PengaturanSiklusTagihan::ambil()->leadDays();
        $tagihanTerbuka = fn ($query) => $query->whereIn('status', StatusInvoice::terbuka());

        $daftar = LayananPelanggan::query()
            ->perluPerhatian($lead)
            ->with(['pelanggan', 'paketLayanan'])
            ->withSum(['invoices as tagihan_terbuka' => $tagihanTerbuka], 'jumlah_setelah_promo')
            ->withMax(['invoices as invoice_terbuka_id' => $tagihanTerbuka], 'id')
            ->orderBy('tanggal_expired')
            ->orderByDesc('tagihan_terbuka')
            ->orderBy(Pelanggan::query()->select('nama_depan')->whereColumn('pelanggan.id', 'layanan_pelanggan.pelanggan_id'))
            ->limit(self::BARIS_DAFTAR)
            ->get();

        return [
            'daftar' => $daftar,
            'lewat' => LayananPelanggan::query()->perluPerhatian($lead, 'overdue')->count(),
            'segera' => LayananPelanggan::query()->perluPerhatian($lead, 'soon')->count(),
            'lead' => $lead,
        ];
    }

    public function render(): View
    {
        $user = auth()->user();

        return view('livewire.dashboard', [
            'pelanggan' => $this->pelanggan,
            'pendapatan' => $this->pendapatan,
            'tagihan' => $this->tagihan,
            'tren' => $this->tren,
            'transaksiTerbaru' => $this->transaksiTerbaru,
            'perluPerhatian' => $this->perluPerhatian,
            'bisaLihatInvoice' => $user->can('invoice.lihat'),
            'bisaLihatPelanggan' => $user->can('pelanggan.lihat'),
        ]);
    }

    private function totalPembayaran(CarbonInterface $dari, CarbonInterface $sampai): float
    {
        return (float) Pembayaran::whereBetween('dibayar_pada', [$dari, $sampai])->sum('jumlah_dibayar');
    }
}
