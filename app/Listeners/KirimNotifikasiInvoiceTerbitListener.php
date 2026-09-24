<?php

namespace App\Listeners;

use App\Events\InvoiceTerbitEvent;
use App\Notifications\InvoiceTerbitNotification;
use App\Services\Whatsapp\WhatsappService;
use DateTimeInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

/**
 * Kirim Notifikasi Invoice Terbit (WhatsApp + email) -- lihat CONTEXT.md dan ADR-0056.
 */
class KirimNotifikasiInvoiceTerbitListener implements ShouldQueue
{
    public string $queue = 'wa-blast';

    /** Event dipancarkan di dalam transaksi pembuatan invoice; tunggu commit. */
    public bool $afterCommit = true;

    private const ZONA_WAKTU = 'Asia/Jakarta';

    private const JAM_MULAI_SIANG = 7;

    private const JAM_AKHIR_SIANG = 20;

    public function __construct(private WhatsappService $whatsappService) {}

    /**
     * Di luar jendela siang (mis. batch invoice:generate 01:00), tunda ke 08:30 berikutnya.
     */
    public function withDelay(InvoiceTerbitEvent $event): DateTimeInterface|int
    {
        $sekarang = now(self::ZONA_WAKTU);

        if ($sekarang->hour >= self::JAM_MULAI_SIANG && $sekarang->hour < self::JAM_AKHIR_SIANG) {
            return 0;
        }

        $pagi = $sekarang->copy()->setTime(8, 30);

        return $sekarang->hour >= self::JAM_AKHIR_SIANG ? $pagi->addDay() : $pagi;
    }

    public function handle(InvoiceTerbitEvent $event): void
    {
        $invoice = $event->invoice->loadMissing(['pelanggan', 'layananPelanggan.paketLayanan']);
        $pelanggan = $invoice->pelanggan;

        if (! $pelanggan) {
            return;
        }

        $params = $this->whatsappService->mergeCompanyParams($this->whatsappService->buildInvoiceParams($invoice));

        if (! empty($pelanggan->no_hp)) {
            try {
                $this->whatsappService->antrikanPesan(
                    noHp: $pelanggan->no_hp,
                    kodeTemplate: 'invoice_terbit',
                    params: $params,
                    referensi: $invoice,
                    jenis: 'invoice_terbit',
                );
            } catch (\Throwable $e) {
                Log::error("Gagal mengantrikan WA invoice terbit {$invoice->no_invoice}: ".$e->getMessage());
            }
        }

        if (! empty($pelanggan->email)) {
            try {
                $pelanggan->notify(new InvoiceTerbitNotification($invoice, $params));
            } catch (\Throwable $e) {
                Log::error("Gagal mengirim email invoice terbit {$invoice->no_invoice}: ".$e->getMessage());
            }
        }
    }
}
