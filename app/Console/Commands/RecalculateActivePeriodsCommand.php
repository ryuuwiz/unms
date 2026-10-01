<?php

namespace App\Console\Commands;

use App\Enums\StatusInvoice;
use App\Models\Invoice;
use App\Models\LayananPelanggan;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class RecalculateActivePeriodsCommand extends Command
{
    protected $signature = 'billing:recalculate-active-periods {--apply : Persist the recalculated periods}';

    protected $description = 'Recalculate service active periods from paid invoice periods';

    public function handle(): int
    {
        $changes = 0;

        LayananPelanggan::query()
            ->with([
                'invoices' => fn ($query) => $query
                    ->where('status', StatusInvoice::Lunas)
                    ->orderBy('tanggal_lunas')
                    ->orderBy('id'),
                'invoices.invoiceDigabung',
                'invoices.promo',
            ])
            ->chunkById(100, function ($layanans) use (&$changes): void {
                foreach ($layanans as $layanan) {
                    $expired = null;

                    foreach ($layanan->invoices as $invoice) {
                        $mulai = $this->periodeMulai($layanan, $invoice);
                        $selesai = $this->periodeSelesai($invoice);

                        if ($expired && $expired->greaterThan($selesai)) {
                            $selesai = $expired;
                        }

                        $attributes = [
                            'masa_aktif_mulai' => $mulai->toDateString(),
                            'masa_aktif_selesai' => $selesai->toDateString(),
                            'masa_aktif_hingga' => $selesai->toDateString(),
                        ];

                        $changes++;
                        $this->line(sprintf(
                            '%s: %s -> %s',
                            $invoice->no_invoice,
                            $layanan->tanggal_expired?->toDateString() ?? '-',
                            $selesai->toDateString()
                        ));

                        if ($this->option('apply')) {
                            $invoice->update($attributes);
                        }

                        $expired = $selesai;
                    }

                    if ($expired && $this->option('apply')) {
                        $layanan->update(['tanggal_expired' => $expired->toDateString()]);
                    }
                }
            });

        $mode = $this->option('apply') ? 'diterapkan' : 'terdeteksi (dry-run)';
        $this->info("{$changes} invoice {$mode}.");

        return self::SUCCESS;
    }

    private function periodeMulai(LayananPelanggan $layanan, Invoice $invoice): Carbon
    {
        if ($invoice->masa_aktif_mulai) {
            return Carbon::parse($invoice->masa_aktif_mulai);
        }

        if ($invoice->periode_tagihan) {
            return Carbon::createFromFormat('!Y-m', $invoice->periode_tagihan)->startOfMonth();
        }

        return Carbon::parse($layanan->tanggal_mulai);
    }

    private function periodeSelesai(Invoice $invoice): Carbon
    {
        $selesai = $invoice->masa_aktif_selesai
            ? Carbon::parse($invoice->masa_aktif_selesai)
            : ($invoice->periode_tagihan
                ? Carbon::createFromFormat('!Y-m', $invoice->periode_tagihan)->endOfMonth()
                : Carbon::parse($invoice->tanggal_jatuh_tempo));

        foreach ($invoice->invoiceDigabung as $digabung) {
            if ($digabung->masa_aktif_selesai) {
                $selesai = $selesai->max(Carbon::parse($digabung->masa_aktif_selesai));
            } elseif ($digabung->periode_tagihan) {
                $selesai = $selesai->max(
                    Carbon::createFromFormat('!Y-m', $digabung->periode_tagihan)->endOfMonth()
                );
            }
        }

        $bonusBulan = (int) ($invoice->promo?->bonus_bulan ?? 0);

        return $bonusBulan > 0
            ? $selesai->addMonthsNoOverflow($bonusBulan)
            : $selesai;
    }
}
