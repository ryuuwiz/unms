<?php

namespace App\Console\Commands;

use App\Enums\StatusInvoice;
use App\Enums\Wa\TipePengingatTagihan;
use App\Models\AturanPengingatTagihan;
use App\Models\Invoice;
use App\Services\Whatsapp\WhatsappService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class KirimPengingatTagihanCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'invoice:kirim-pengingat {--force : Jalankan pengingat mengabaikan jam eksekusi harian}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Kirim pengingat tagihan otomatis via WhatsApp sesuai aturan aktif dan tanggal jatuh tempo';

    /**
     * Execute the console command.
     */
    public function handle(WhatsappService $whatsappService): int
    {
        $this->info('Memulai pemrosesan pengingat tagihan otomatis via WhatsApp...');

        $now = Carbon::now();
        $today = Carbon::today();
        $isForce = $this->option('force');

        $rules = AturanPengingatTagihan::query()
            ->active()
            ->with('template')
            ->get();

        if ($rules->isEmpty()) {
            $this->warn('Tidak ada aturan pengingat tagihan yang aktif.');

            return Command::SUCCESS;
        }

        $totalAntreanBaru = 0;

        foreach ($rules as $rule) {
            // Cek apakah jam saat ini sudah memenuhi jam eksekusi aturan
            if (! $isForce) {
                $jamEksekusi = Carbon::createFromTimeString($rule->jam_eksekusi);
                if ($now->format('H:i') < $jamEksekusi->format('H:i')) {
                    $this->line("Aturan [{$rule->nama_aturan}] dilewati (Jadwal: {$rule->jam_eksekusi}, Waktu sekarang: {$now->format('H:i')}).");

                    continue;
                }
            }

            $targetDate = $rule->hitungTanggalJatuhTempoTarget($today);
            $this->info("Menjalankan Aturan [{$rule->nama_aturan}] (Target Jatuh Tempo: {$targetDate->toDateString()})...");

            $invoiceQuery = Invoice::query()
                ->where('status', StatusInvoice::MenungguPembayaran)
                ->with(['pelanggan', 'layananPelanggan.paketLayanan']);

            if ($rule->tipe_pengingat === TipePengingatTagihan::SetelahJatuhTempo && $rule->kirim_ulang_berkala) {
                // Tunggakan berulang: ambil semua invoice yang jatuh tempo <= targetDate
                $invoiceQuery->whereDate('tanggal_jatuh_tempo', '<=', $targetDate);
            } else {
                $invoiceQuery->whereDate('tanggal_jatuh_tempo', $targetDate);
            }

            $invoices = $invoiceQuery->get();
            $countRule = 0;

            foreach ($invoices as $invoice) {
                $pelanggan = $invoice->pelanggan;
                if (! $pelanggan || empty($pelanggan->no_hp)) {
                    continue;
                }

                $params = $whatsappService->buildInvoiceParams($invoice);
                $antrian = $whatsappService->antrikanPesan(
                    noHp: $pelanggan->no_hp,
                    kodeTemplate: $rule->template->kode,
                    params: $params,
                    referensi: $invoice,
                    jenis: $rule->template->kode,
                    tanggalTarget: $today
                );

                if ($antrian && $antrian->wasRecentlyCreated) {
                    $countRule++;
                    $totalAntreanBaru++;
                }
            }

            $this->info("-> Aturan [{$rule->nama_aturan}]: {$countRule} pengingat berhasil diantrikan.");
        }

        $this->info("Selesai. Total {$totalAntreanBaru} pesan WhatsApp baru dimasukkan ke antrean pengiriman.");

        return Command::SUCCESS;
    }
}
