<?php

namespace App\Livewire\Dashboard;

use App\Enums\StatusInvoice;
use App\Enums\StatusPelanggan;
use App\Enums\Ticket\StatusTicket;
use App\Models\Invoice;
use App\Models\LayananPelanggan;
use App\Models\Pelanggan;
use App\Models\Pembayaran;
use App\Models\PengaturanSiklusTagihan;
use App\Models\Ticket;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Lazy;
use Livewire\Component;

/**
 * Area Dashboard Admin: pelanggan, keuangan, dan tiket hari ini. Tiap widget di-gate izinnya sendiri.
 */
#[Lazy]
class AreaAdmin extends Component
{
    private const BARIS_DAFTAR = 8;

    private const BARIS_TRANSAKSI = 5;

    private const BARIS_TAGIHAN_TERBUKA = 20;

    private const HARI_TREN = 30;

    /**
     * Area tampil bila user berhak melihat minimal satu widget di dalamnya.
     */
    public static function bolehLihat(User $user): bool
    {
        return $user->canAny(['pelanggan.lihat', 'pembayaran.lihat', 'invoice.lihat', 'layanan_pelanggan.lihat', 'ticket.lihat']);
    }

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
     * Tiket hari ini: yang masuk (dibuat) hari ini dan yang masih terbuka, sebatas tiket yang boleh dilihat user.
     *
     * @return array{masuk: int, terbuka: int}|null
     */
    public function getTiketHariIniProperty(): ?array
    {
        $user = auth()->user();

        if (! $user->can('ticket.lihat')) {
            return null;
        }

        $hitung = Ticket::query()
            ->terlihatOleh($user)
            ->selectRaw('COALESCE(SUM(CASE WHEN created_at >= ? THEN 1 ELSE 0 END), 0) as masuk', [today()])
            ->selectRaw('COALESCE(SUM(CASE WHEN status NOT IN (?, ?) THEN 1 ELSE 0 END), 0) as terbuka', [StatusTicket::Selesai->value, StatusTicket::Batal->value])
            ->toBase()
            ->first();

        return ['masuk' => (int) $hitung->masuk, 'terbuka' => (int) $hitung->terbuka];
    }

    /**
     * Ringkasan Tagihan Periode bulan berjalan (tarif siklus tanpa tunggakan; invoice digabung
     * tetap dihitung), distribusi jumlah invoice per status, plus total Belum Dibayar seluruh periode.
     *
     * @return array{ditagih: float, lunas: float, belum_lunas: float, tertagih: float|null, belum_dibayar: float, distribusi: array<string, int>}|null
     */
    public function getTagihanProperty(): ?array
    {
        $user = auth()->user();

        if (! $user->can('invoice.lihat') || ! $user->can('pembayaran.lihat')) {
            return null;
        }

        $periodeIni = Invoice::query()
            ->where('periode_tagihan', now()->format('Y-m'))
            ->where('status', '!=', StatusInvoice::Dibatalkan);

        $siklus = (clone $periodeIni)
            ->selectRaw('COALESCE(SUM(jumlah_setelah_promo - jumlah_tunggakan), 0) as ditagih')
            ->selectRaw('COALESCE(SUM(CASE WHEN status = ? THEN jumlah_setelah_promo - jumlah_tunggakan ELSE 0 END), 0) as lunas', [StatusInvoice::Lunas->value])
            ->toBase()
            ->first();

        $ditagih = (float) $siklus->ditagih;
        $lunas = (float) $siklus->lunas;

        return [
            'ditagih' => $ditagih,
            'lunas' => $lunas,
            'belum_lunas' => $ditagih - $lunas,
            'tertagih' => $ditagih > 0 ? round(($lunas / $ditagih) * 100, 1) : null,
            'belum_dibayar' => (float) Invoice::query()->whereIn('status', StatusInvoice::terbuka())->sum('jumlah_setelah_promo'),
            'distribusi' => $periodeIni->toBase()->selectRaw('status, COUNT(*) as jumlah')->groupBy('status')->pluck('jumlah', 'status')->map(fn ($n) => (int) $n)->all(),
        ];
    }

    /**
     * Tren Pendapatan Harian: pendapatan dan jumlah transaksi per hari, 30 hari terakhir sampai hari ini.
     *
     * @return array{categories: array<int, string>, revenue: array<int, float>, transactions: array<int, int>}|null
     */
    public function getTrenProperty(): ?array
    {
        if (! auth()->user()->can('pembayaran.lihat')) {
            return null;
        }

        $dari = today()->subDays(self::HARI_TREN - 1);

        $harian = Pembayaran::query()
            ->whereBetween('dibayar_pada', [$dari, now()->endOfDay()])
            ->selectRaw('DATE(dibayar_pada) as tanggal, SUM(jumlah_dibayar) as total, COUNT(*) as jumlah')
            ->groupByRaw('DATE(dibayar_pada)')
            ->get()
            ->keyBy('tanggal');

        $tren = ['categories' => [], 'revenue' => [], 'transactions' => []];

        for ($hari = $dari->copy(); $hari->lte(today()); $hari = $hari->copy()->addDay()) {
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

    /**
     * Tagihan Terbuka per Pelanggan: 20 pelanggan dengan total invoice terbuka terbesar (lintas layanan & periode).
     *
     * @return Collection<int, Pelanggan>|null
     */
    public function getTagihanTerbukaProperty(): ?Collection
    {
        if (! auth()->user()->can('invoice.lihat')) {
            return null;
        }

        return Pelanggan::query()
            ->withSum(['invoices as total_terbuka' => fn ($query) => $query->whereIn('status', StatusInvoice::terbuka())], 'jumlah_setelah_promo')
            ->withCount(['invoices as jumlah_terbuka' => fn ($query) => $query->whereIn('status', StatusInvoice::terbuka())])
            ->having('total_terbuka', '>', 0)
            ->orderByDesc('total_terbuka')
            ->limit(self::BARIS_TAGIHAN_TERBUKA)
            ->get();
    }

    public function render(): View
    {
        $user = auth()->user();

        return view('livewire.dashboard.area-admin', [
            'pelanggan' => $this->getPelangganProperty(),
            'pendapatan' => $this->getPendapatanProperty(),
            'tiketHariIni' => $this->getTiketHariIniProperty(),
            'tagihan' => $this->getTagihanProperty(),
            'tren' => $this->getTrenProperty(),
            'transaksiTerbaru' => $this->getTransaksiTerbaruProperty(),
            'perluPerhatian' => $this->getPerluPerhatianProperty(),
            'tagihanTerbuka' => $this->getTagihanTerbukaProperty(),
            'bisaLihatInvoice' => $user->can('invoice.lihat'),
            'bisaLihatPelanggan' => $user->can('pelanggan.lihat'),
        ]);
    }

    private function totalPembayaran(CarbonInterface $dari, CarbonInterface $sampai): float
    {
        return (float) Pembayaran::whereBetween('dibayar_pada', [$dari, $sampai])->sum('jumlah_dibayar');
    }
}
