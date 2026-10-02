<?php

namespace App\Console\Commands;

use App\Enums\StatusInvoice;
use App\Enums\StatusTransaksiGateway;
use App\Models\TransaksiPaymentGateway;
use App\Services\PaymentGateway\PaymentGatewayManager;
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
                            {--dry-run : Hanya tampilkan status gateway tanpa mengubah data}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Pindai ulang transaksi Expired/Pending ke gateway dan lunasi yang ternyata sudah dibayar (pemulihan sekali jalan, ADR-0067)';

    public function handle(PaymentGatewayManager $manager): int
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

        $baris = $transaksis->map(fn (TransaksiPaymentGateway $transaksi): array => $this->pulihkan($manager, $transaksi, $dryRun));
        $dilunasi = $baris->filter(fn (array $row): bool => $row[4] === 'DILUNASI')->count();

        $this->table(['No Invoice', 'External ID', 'Status Lokal', 'Status Gateway', 'Aksi'], $baris->all());
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
            ->whereHas('invoice', fn ($query) => $query->where('status', '!=', StatusInvoice::Lunas))
            ->with(['invoice', 'pengaturanGateway'])
            ->orderBy('id')
            ->limit(max(1, (int) $this->option('limit')))
            ->get();
    }

    /**
     * @return array{0: string|null, 1: string, 2: string, 3: string, 4: string}
     */
    protected function pulihkan(PaymentGatewayManager $manager, TransaksiPaymentGateway $transaksi, bool $dryRun): array
    {
        $statusLokal = $transaksi->status->value;

        try {
            $statusData = $dryRun ? $manager->cekStatusTransaksi($transaksi) : $manager->sinkronkanTransaksi($transaksi);
        } catch (Throwable $e) {
            $statusData = ['error' => $e->getMessage()];
        }

        $statusGateway = strtoupper((string) ($statusData['status'] ?? ''));
        $aksi = match (true) {
            $dryRun => '-',
            $transaksi->invoice?->fresh()?->isLunas() === true => 'DILUNASI',
            default => 'tidak berubah',
        };

        return [
            $transaksi->invoice?->no_invoice,
            $transaksi->external_id,
            $statusLokal,
            $statusGateway ?: 'ERROR: '.($statusData['error'] ?? '-'),
            $aksi,
        ];
    }
}
