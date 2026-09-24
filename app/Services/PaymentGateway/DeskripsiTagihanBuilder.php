<?php

namespace App\Services\PaymentGateway;

use App\Actions\LayananPelanggan\PerpanjangMasaAktifAction;
use App\Models\Invoice;
use App\Models\PengaturanPrefixRegistrasi;
use App\Models\Perusahaan;
use App\Models\TemplateDeskripsiTagihan;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Susun `description` yang dikirim ke payment gateway -- lihat CONTEXT.md
 * "Template Deskripsi Tagihan Gateway". Tidak pernah melempar exception: link bayar tidak
 * boleh gagal terbit hanya karena template bermasalah.
 */
class DeskripsiTagihanBuilder
{
    private const BATAS_KARAKTER = 255;

    public function __construct(private PerpanjangMasaAktifAction $perpanjangMasaAktif) {}

    public function buat(Invoice $invoice): string
    {
        $hasil = '';

        try {
            $konten = $invoice->periode_tagihan
                ? TemplateDeskripsiTagihan::default()?->konten
                : TemplateDeskripsiTagihan::KONTEN_TANPA_PERIODE;

            if ($konten) {
                $hasil = trim($this->render($konten, $this->params($invoice)));
            }
        } catch (\Throwable $e) {
            Log::warning("Gagal menyusun deskripsi tagihan gateway invoice {$invoice->no_invoice}: ".$e->getMessage());
        }

        // Placeholder tak dikenal yang lolos (mis. template diedit langsung di DB) tidak boleh
        // sampai ke halaman checkout pelanggan.
        if ($hasil === '' || preg_match('/\{[^{}]*\}/', $hasil)) {
            $hasil = "Tagihan Internet UNMS Invoice {$invoice->no_invoice}";
        }

        return mb_substr($hasil, 0, self::BATAS_KARAKTER);
    }

    /**
     * @param  array<string, string>  $params
     */
    private function render(string $konten, array $params): string
    {
        foreach ($params as $nama => $nilai) {
            $konten = str_replace('{'.$nama.'}', $nilai, $konten);
        }

        return $konten;
    }

    /**
     * @return array<string, string>
     */
    private function params(Invoice $invoice): array
    {
        $invoice->loadMissing(['pelanggan', 'layananPelanggan.paketLayanan', 'promo']);
        $pelanggan = $invoice->pelanggan;
        $layanan = $invoice->layananPelanggan;

        // Periode tak terbaca (format bukan Y-m) jatuh ke bulan tanggal terbit, bukan gagal.
        $bulan = ($invoice->periode_tagihan ? Carbon::createFromFormat('!Y-m', $invoice->periode_tagihan) : null)
            ?? Carbon::parse($invoice->tanggal_terbit);

        return [
            'brand' => $this->brand($pelanggan?->no_reg),
            'site_id' => $layanan->site_id ?? '-',
            'bulan' => $bulan->translatedFormat('F Y'),
            'nama_paket_pelanggan' => $layanan?->paketLayanan->nama_paket ?? 'Layanan Internet',
            'hingga' => $layanan
                ? $this->perpanjangMasaAktif->hitungExpiredBaru($layanan, $invoice, Carbon::now())->toDateString()
                : '-',
            'no_invoice' => (string) $invoice->no_invoice,
            'nama_pelanggan' => $pelanggan ? trim("{$pelanggan->nama_depan} {$pelanggan->nama_belakang}") : 'Pelanggan',
            'no_reg' => $pelanggan->no_reg ?? '-',
            'total_tagihan' => 'Rp '.number_format((float) ($invoice->jumlah_setelah_promo ?? $invoice->jumlah), 0, ',', '.'),
            'jatuh_tempo' => Carbon::parse($invoice->tanggal_jatuh_tempo)->toDateString(),
            'keterangan' => $invoice->keterangan ?: 'Tagihan Internet',
        ];
    }

    /**
     * Nama Prefix Registrasi aktif milik pelanggan (huruf awal `no_reg`), fallback brand perusahaan.
     */
    private function brand(?string $noReg): string
    {
        $kode = preg_match('/^[A-Za-z]+/', (string) $noReg, $cocok) ? strtoupper($cocok[0]) : null;

        $nama = $kode
            ? PengaturanPrefixRegistrasi::where('kode', $kode)->where('is_active', true)->value('nama')
            : null;

        return $nama ?: (Perusahaan::default()->nama_brand ?? config('app.name', 'GOBILLING'));
    }
}
