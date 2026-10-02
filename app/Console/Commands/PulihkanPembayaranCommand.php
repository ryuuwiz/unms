<?php

namespace App\Console\Commands;

use App\DTO\PaymentGateway\HasilPelunasanSusulan;
use App\Enums\AksiPelunasanSusulan;
use App\Enums\StatusInvoice;
use App\Enums\StatusTransaksiGateway;
use App\Models\Invoice;
use App\Models\TransaksiPaymentGateway;
use App\Services\PaymentGateway\LaporanPelunasanSusulan;
use App\Services\PaymentGateway\PaymentGatewayManager;
use App\Services\PaymentGateway\PelunasanSusulan;
use Carbon\CarbonInterface;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Throwable;

class PulihkanPembayaranCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pembayaran:pulihkan
                            {--dari= : Tanggal awal pembuatan transaksi (Y-m-d), default 30 hari lalu}
                            {--sampai= : Tanggal akhir pembuatan transaksi (Y-m-d), default hari ini}
                            {--limit=500 : Batas jumlah transaksi yang diperiksa}
                            {--dry-run : Hanya tampilkan status gateway dan keputusan Pelunasan Susulan tanpa mengubah data}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Pindai ulang transaksi Expired/Pending ke gateway dan lunasi yang ternyata sudah dibayar (pemulihan sekali jalan, ADR-0067)';

    public function handle(PaymentGatewayManager $manager, PelunasanSusulan $pelunasanSusulan, LaporanPelunasanSusulan $laporan): int
    {
        [$dari, $sampai] = $this->rentangTanggal();
        $dryRun = (bool) $this->option('dry-run');
        $transaksis = $this->transaksiUntukDipindai($dari, $sampai);

        $this->info(sprintf(
            '%s %d transaksi (%s s/d %s)...',
            $dryRun ? '[DRY RUN] Memeriksa' : 'Memulihkan',
            $transaksis->count(),
            $dari->toDateString(),
            $sampai->toDateString(),
        ));

        $hasilSusulan = [];
        $baris = $transaksis->map(function (TransaksiPaymentGateway $transaksi) use ($manager, $pelunasanSusulan, $dryRun, &$hasilSusulan): array {
            [$row, $hasil] = $this->pulihkan($manager, $pelunasanSusulan, $transaksi, $dryRun);
            if ($hasil) {
                $hasilSusulan[] = $hasil;
            }

            return $row;
        });

        if (! $dryRun) {
            $laporan->laporkan($hasilSusulan);
        }

        $dilunasi = $baris->filter(fn (array $row): bool => $row[4] === AksiPelunasanSusulan::Dilunasi->value)->count();
        $this->table(['No Invoice', 'External ID', 'Status Lokal', 'Status Gateway', 'Aksi', 'Keterangan'], $baris->all());
        $this->info($dryRun ? 'Dry run selesai, tidak ada data yang diubah.' : "Selesai. {$dilunasi} invoice dilunasi.");

        return self::SUCCESS;
    }

    /**
     * @return array{0: CarbonInterface, 1: CarbonInterface}
     */
    protected function rentangTanggal(): array
    {
        $dari = $this->option('dari') ? Carbon::parse((string) $this->option('dari')) : now()->subDays(30);
        $sampai = $this->option('sampai') ? Carbon::parse((string) $this->option('sampai')) : now();

        return [$dari->startOfDay(), $sampai->endOfDay()];
    }

    /**
     * @return Collection<int, TransaksiPaymentGateway>
     */
    protected function transaksiUntukDipindai(CarbonInterface $dari, CarbonInterface $sampai): Collection
    {
        return TransaksiPaymentGateway::query()
            ->whereIn('status', [StatusTransaksiGateway::Expired, StatusTransaksiGateway::Pending])
            ->whereBetween('created_at', [$dari, $sampai])
            ->whereHas('invoice', fn ($query) => $query->withTrashed()->where('status', '!=', StatusInvoice::Lunas))
            ->with(['invoice', 'pengaturanGateway'])
            ->orderBy('id')
            ->limit(max(1, (int) $this->option('limit')))
            ->get();
    }

    /**
     * Status gateway PAID diputuskan oleh Pelunasan Susulan (aturan yang sama dengan sapuan harian
     * dan webhook, ADR-0069); status lain disinkronkan seperti biasa (mis. Kedaluwarsa).
     *
     * @return array{0: array{0: string|null, 1: string, 2: string, 3: string, 4: string, 5: string}, 1: HasilPelunasanSusulan|null}
     */
    protected function pulihkan(PaymentGatewayManager $manager, PelunasanSusulan $pelunasanSusulan, TransaksiPaymentGateway $transaksi, bool $dryRun): array
    {
        $row = fn (string $statusGateway, string $aksi, string $keterangan = ''): array => [
            Invoice::withTrashed()->find($transaksi->invoice_id)?->no_invoice,
            $transaksi->external_id,
            $transaksi->status->value,
            $statusGateway,
            $aksi,
            $keterangan,
        ];

        try {
            $statusData = $manager->cekStatusTransaksi($transaksi);
        } catch (Throwable $e) {
            return [$row('ERROR: '.$e->getMessage(), '-'), null];
        }

        $statusGateway = strtoupper((string) ($statusData['status'] ?? ''));
        if ($statusGateway === '') {
            return [$row('ERROR: '.($statusData['error'] ?? '-'), '-'), null];
        }

        $hasil = $pelunasanSusulan->tanganiStatusGateway($transaksi, $statusData, $dryRun);
        if ($hasil) {
            return [$row($statusGateway, $hasil->aksi->value, $hasil->keterangan), $hasil];
        }

        if (! $dryRun) {
            rescue(fn () => $manager->sinkronkanTransaksi($transaksi), report: false);
        }

        return [$row($statusGateway, $dryRun ? '-' : 'tidak berubah'), null];
    }
}
