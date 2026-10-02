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
use App\Support\BrandPelanggan;
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
            // Selalu lewat worker: dispatchAfterResponse menjalankan job secara sinkron, sehingga
            // release() karena Jeda Antar-Pesan membuang job (baru disapu wa:proses-antrian 5 menit kemudian).
            KirimWaBlastJob::dispatch($antrian);
        } else {
            AntrianWaBlast::catatGagal($antrian, (string) $antrian->pesan_error);
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
        $namaPaket = $layanan?->paketLayanan->nama_paket ?? 'Layanan Internet';

        // Tautan Tagihan tanpa login dan tanpa masa berlaku, bukan Link Pembayaran Gateway
        // langsung: pelanggan selalu mendarat di halaman rincian tagihan kami dulu (ADR-0067).
        $linkBayar = $invoice->exists ? $invoice->tautanTagihan() : url('/');

        return [
            'nama_pelanggan' => $pelanggan ? "{$pelanggan->nama_depan} {$pelanggan->nama_belakang}" : 'Pelanggan',
            'no_reg' => $pelanggan->no_reg ?? '-',
            'nama_brand' => BrandPelanggan::untukNoReg($pelanggan?->no_reg)->nama(),
            'no_invoice' => $invoice->no_invoice,
            'periode' => $invoice->periode_tagihan ?? Carbon::parse($invoice->tanggal_terbit)->format('m/Y'),
            'total_tagihan' => 'Rp '.number_format($invoice->jumlah_setelah_promo ?? $invoice->jumlah, 0, ',', '.'),
            'jatuh_tempo' => $this->tanggalPesan($invoice->tanggal_jatuh_tempo, 'd F Y'),
            'link_pembayaran' => $linkBayar,
            'nama_paket' => $namaPaket,
            'site_id' => $layanan->site_id ?? '-',
            // Kode referensi kosmetik saja -- tidak ada integrasi PPOB/biller minimarket di
            // sistem ini untuk memvalidasinya, sekadar nomor rujukan singkat di pesan.
            'kode_bayar' => str_pad((string) ($invoice->id % 100000), 5, '0', STR_PAD_LEFT),
            'status_internet' => $pelanggan?->status?->label() ?? '-',
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

        $alamat = $pelanggan->alamat_lengkap ?? '-';
        if ($pelanggan?->perumahan) {
            $alamat .= " ({$pelanggan->perumahan->nama_perumahan})";
        }

        return [
            'nomor_tiket' => $ticket->nomor_ticket,
            'jenis_tiket' => $ticket->jenis->label(),
            'prioritas' => $ticket->prioritas->label(),
            'status_tiket' => $ticket->status->label(),
            'nama_pelanggan' => $pelanggan ? "{$pelanggan->nama_depan} {$pelanggan->nama_belakang}" : 'Pelanggan',
            'no_reg' => $pelanggan->no_reg ?? '-',
            'nama_brand' => BrandPelanggan::untukNoReg($pelanggan?->no_reg)->nama(),
            'no_hp_pelanggan' => $pelanggan->no_hp ?? '-',
            'alamat' => $alamat,
            'nama_pic' => $pic->name ?? 'Belum Ditugaskan',
            'catatan_histori' => $catatan ?? $ticket->deskripsi,
            'deskripsi' => $ticket->deskripsi,
            'sla_target' => $ticket->sla_target_selesai ? $this->tanggalPesan($ticket->sla_target_selesai) : '-',
            'jadwal_teknisi' => $ticket->dijadwalkan_pada ? $this->tanggalPesan($ticket->dijadwalkan_pada) : '-',
            'link_tiket' => route('ticket.show', $ticket->id),
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
        $params['tanggal_bayar'] = $this->tanggalPesan($pembayaran->dibayar_pada ?? now());
        $metodeText = $pembayaran->metode->value;
        $params['metode_bayar'] = strtoupper(str_replace('_', ' ', $metodeText));
        $params['referensi_transaksi'] = $pembayaran->referensi_transaksi ?? $invoice->no_invoice;

        return $params;
    }

    /**
     * Gabungkan parameter default data Perusahaan. `nama_brand` yang sudah diisi (Brand
     * Pelanggan, ADR-0061) tidak ditimpa.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function mergeCompanyParams(array $params): array
    {
        try {
            $perusahaan = Perusahaan::default();
            $params['nama_perusahaan'] = $perusahaan->nama_perusahaan ?? config('app.name', 'GOBILLING');
            $params['nama_brand'] ??= $perusahaan->nama_brand ?? $params['nama_perusahaan'];
            $params['telepon_perusahaan'] = $perusahaan->telepon ?? '-';
            $params['whatsapp_perusahaan'] = $perusahaan->whatsapp ?? '-';
            $params['alamat_perusahaan'] = $perusahaan->alamat ?? '-';
        } catch (\Throwable) {
            $params['nama_perusahaan'] = config('app.name', 'GOBILLING');
            $params['nama_brand'] ??= config('app.name', 'GOBILLING');
            $params['telepon_perusahaan'] = '-';
            $params['whatsapp_perusahaan'] = '-';
            $params['alamat_perusahaan'] = '-';
        }

        return $params;
    }

    /**
     * Tanggal di pesan WhatsApp untuk pelanggan selalu berbahasa Indonesia, apa pun APP_LOCALE.
     */
    private function tanggalPesan(mixed $tanggal, string $format = 'd F Y H:i'): string
    {
        return Carbon::parse($tanggal)->locale('id')->translatedFormat($format);
    }
}
