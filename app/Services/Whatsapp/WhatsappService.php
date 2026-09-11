<?php

namespace App\Services\Whatsapp;

use App\Enums\Wa\StatusAntrianWa;
use App\Jobs\Wa\KirimWaBlastJob;
use App\Models\AntrianWaBlast;
use App\Models\Invoice;
use App\Models\Pembayaran;
use App\Models\Perusahaan;
use App\Models\Sysblas;
use App\Models\Ticket;
use App\Models\WaTemplate;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class WhatsappService
{
    public function __construct(
        protected WhatsappClient $client
    ) {}

    /**
     * Antrikan pesan WhatsApp menggunakan template dinamis dari database.
     *
     * @param  array<string, mixed>  $params
     */
    public function antrikanPesan(
        string $noHp,
        string $kodeTemplate,
        array $params = [],
        ?Model $referensi = null,
        ?string $jenis = null,
        ?Carbon $tanggalTarget = null,
        ?Sysblas $sysblas = null
    ): ?AntrianWaBlast {
        $template = WaTemplate::where('kode', $kodeTemplate)->first();

        if (! $template || ! $template->is_aktif) {
            Log::warning("WhatsApp Template '{$kodeTemplate}' tidak ditemukan atau non-aktif.");

            return null;
        }

        // Lengkapi parameter perusahaan secara default
        $params = $this->mergeCompanyParams($params);

        $pesan = $template->render($params);
        $jenisPesan = $jenis ?? $kodeTemplate;

        return $this->antrikanPesanKustom(
            noHp: $noHp,
            pesan: $pesan,
            referensi: $referensi,
            jenis: $jenisPesan,
            tanggalTarget: $tanggalTarget,
            sysblas: $sysblas
        );
    }

    /**
     * Antrikan pesan WhatsApp teks kustom / langsung.
     */
    public function antrikanPesanKustom(
        string $noHp,
        string $pesan,
        ?Model $referensi = null,
        string $jenis = 'kustom',
        ?Carbon $tanggalTarget = null,
        ?Sysblas $sysblas = null
    ): ?AntrianWaBlast {
        $tanggalKirim = $tanggalTarget ? $tanggalTarget->toDateString() : Carbon::today()->toDateString();
        $refTipe = $referensi ? $referensi->getMorphClass() : null;
        $refId = $referensi?->getKey();

        // Idempotency check: jika referensi sudah ada antrean dengan jenis & tanggal sama, abaikan duplikasi
        if ($referensi) {
            $existing = AntrianWaBlast::query()
                ->where('referensi_tipe', $refTipe)
                ->where('referensi_id', $refId)
                ->where('jenis', $jenis)
                ->whereDate('tanggal_kirim', $tanggalKirim)
                ->first();

            if ($existing) {
                return $existing;
            }
        }

        $normalizedPhone = WhatsappClient::normalizePhoneNumber($noHp);
        $isValidPhone = ! empty($normalizedPhone);

        $targetSysblas = $sysblas ?? Sysblas::getDefault();

        /** @var AntrianWaBlast $antrian */
        $antrian = AntrianWaBlast::create([
            'sysblas_id' => $targetSysblas?->id,
            'no_hp_tujuan' => $normalizedPhone ?? $noHp,
            'pesan' => $pesan,
            'jenis' => $jenis,
            'referensi_tipe' => $refTipe,
            'referensi_id' => $refId,
            'tanggal_kirim' => $tanggalKirim,
            'status' => $isValidPhone ? StatusAntrianWa::Menunggu : StatusAntrianWa::Gagal,
            'dijadwalkan_pada' => $tanggalTarget ?? Carbon::now(),
            'pesan_error' => $isValidPhone ? null : "Nomor HP '{$noHp}' tidak valid untuk WhatsApp Indonesia.",
            'percobaan_ke' => $isValidPhone ? 0 : 1,
        ]);

        if ($isValidPhone) {
            // Pengiriman instan: gunakan dispatchAfterResponse jika di web context, atau dispatch langsung
            if (! app()->runningInConsole() && (! $tanggalTarget || $tanggalTarget->isPast() || $tanggalTarget->isToday())) {
                KirimWaBlastJob::dispatchAfterResponse($antrian);
            } else {
                KirimWaBlastJob::dispatch($antrian);
            }
        }

        return $antrian;
    }

    /**
     * Susun parameter template dari model Invoice.
     *
     * @return array<string, mixed>
     */
    public function buildInvoiceParams(Invoice $invoice): array
    {
        $pelanggan = $invoice->pelanggan;
        $layanan = $invoice->layananPelanggan;
        $namaPaket = $layanan?->paketLayanan?->nama_paket ?? 'Layanan Internet';

        $linkBayar = $invoice->xendit_invoice_url
            ?? ($invoice->id ? route('portal.invoice.show', $invoice->id) : url('/'));

        return [
            'nama_pelanggan' => $pelanggan ? "{$pelanggan->nama_depan} {$pelanggan->nama_belakang}" : 'Pelanggan',
            'no_reg' => $pelanggan?->no_reg ?? '-',
            'no_invoice' => $invoice->no_invoice,
            'periode' => $invoice->periode_tagihan ?? Carbon::parse($invoice->tanggal_terbit)->format('m/Y'),
            'total_tagihan' => 'Rp '.number_format($invoice->jumlah_setelah_promo ?? $invoice->jumlah, 0, ',', '.'),
            'jatuh_tempo' => Carbon::parse($invoice->tanggal_jatuh_tempo)->translatedFormat('d F Y'),
            'link_pembayaran' => $linkBayar,
            'nama_paket' => $namaPaket,
            'site_id' => $layanan?->site_id ?? '-',
        ];
    }

    /**
     * Susun parameter template dari model Ticket.
     *
     * @return array<string, mixed>
     */
    public function buildTicketParams(Ticket $ticket, ?string $catatan = null): array
    {
        $pelanggan = $ticket->pelanggan;
        $pic = $ticket->pic;

        $alamat = $pelanggan?->alamat_lengkap ?? '-';
        if ($pelanggan?->perumahan) {
            $alamat .= " ({$pelanggan->perumahan->nama_perumahan})";
        }

        return [
            'nomor_tiket' => $ticket->nomor_ticket,
            'jenis_tiket' => $ticket->jenis->label(),
            'prioritas' => $ticket->prioritas->label(),
            'status_tiket' => $ticket->status->label(),
            'nama_pelanggan' => $pelanggan ? "{$pelanggan->nama_depan} {$pelanggan->nama_belakang}" : 'Pelanggan',
            'no_reg' => $pelanggan?->no_reg ?? '-',
            'no_hp_pelanggan' => $pelanggan?->no_hp ?? '-',
            'alamat' => $alamat,
            'nama_pic' => $pic?->name ?? 'Belum Ditugaskan',
            'catatan_histori' => $catatan ?? $ticket->deskripsi,
            'deskripsi' => $ticket->deskripsi,
            'sla_target' => $ticket->sla_target_selesai ? Carbon::parse($ticket->sla_target_selesai)->translatedFormat('d F Y H:i') : '-',
            'link_tiket' => route('ticket.show', $ticket->id),
            'link_portal_tiket' => route('portal.tiket.show', $ticket->id),
        ];
    }

    /**
     * Susun parameter template dari model Pembayaran.
     *
     * @return array<string, mixed>
     */
    public function buildPaymentParams(Invoice $invoice, Pembayaran $pembayaran): array
    {
        $params = $this->buildInvoiceParams($invoice);

        $params['jumlah_dibayar'] = 'Rp '.number_format((float) $pembayaran->jumlah_dibayar, 0, ',', '.');
        $params['tanggal_bayar'] = Carbon::parse($pembayaran->dibayar_pada ?? now())->translatedFormat('d F Y H:i');
        $metodeText = $pembayaran->metode instanceof \BackedEnum
            ? $pembayaran->metode->value
            : (string) ($pembayaran->metode ?? 'Online');
        $params['metode_bayar'] = strtoupper(str_replace('_', ' ', $metodeText));
        $params['referensi_transaksi'] = $pembayaran->referensi_transaksi ?? $invoice->no_invoice;

        return $params;
    }

    /**
     * Gabungkan parameter default data Perusahaan.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    protected function mergeCompanyParams(array $params): array
    {
        try {
            $perusahaan = Perusahaan::default();
            $params['nama_perusahaan'] = $perusahaan->nama_perusahaan ?? config('app.name', 'GOBILLING');
            $params['nama_brand'] = $perusahaan->nama_brand ?? $params['nama_perusahaan'];
            $params['telepon_perusahaan'] = $perusahaan->telepon ?? '-';
            $params['whatsapp_perusahaan'] = $perusahaan->whatsapp ?? '-';
            $params['alamat_perusahaan'] = $perusahaan->alamat ?? '-';
        } catch (\Throwable) {
            $params['nama_perusahaan'] = config('app.name', 'GOBILLING');
            $params['nama_brand'] = config('app.name', 'GOBILLING');
            $params['telepon_perusahaan'] = '-';
            $params['whatsapp_perusahaan'] = '-';
            $params['alamat_perusahaan'] = '-';
        }

        return $params;
    }
}
