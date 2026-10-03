<?php

namespace App\Livewire\Portal\Invoice;

use App\Enums\GatewayChannel;
use App\Enums\StatusTransaksiGateway;
use App\Livewire\Portal\Invoice\Concerns\AuthorizesInvoiceAccess;
use App\Models\ChannelPembayaran;
use App\Models\Invoice;
use App\Services\PaymentGateway\CekStatusPembayaranInvoice;
use App\Services\PaymentGateway\PaymentGatewayManager;
use App\Support\BrandPelanggan;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Exception;
use Flux\Flux;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Throwable;

#[Layout('layouts.portal')]
#[Title('Rincian Invoice')]
class Show extends Component
{
    use AuthorizesInvoiceAccess;

    public Invoice $invoice;

    public function mount(Invoice $invoice, PaymentGatewayManager $paymentManager): void
    {
        $this->authorizeAksesTagihan($invoice);

        // Sinkronisasi otomatis dengan payment gateway jika masih menunggu pembayaran
        // (Sangat berguna saat pelanggan kembali di-redirect dari halaman checkout gateway)
        // Gangguan gateway tidak boleh membuat halaman tagihan gagal tampil: pelanggan tetap
        // melihat rinciannya dan bisa menekan "Cek Status" / "Bayar Sekarang".
        if ($invoice->isMenungguPembayaran() && (! empty($invoice->payment_gateway_id) || ! empty($invoice->xendit_invoice_id))) {
            try {
                $paymentManager->sinkronkanStatus($invoice);
            } catch (Throwable $e) {
                report($e);
            }
            $invoice->refresh();
        }

        $this->invoice = $invoice->load([
            'pelanggan',
            'layananPelanggan.paketLayanan.profilBandwidth',
            'promo',
            'pembayarans',
            'transaksiPaymentGateways' => fn ($q) => $q->latest(),
        ]);

        // Halaman Tagihan Mandiri selalu ber-brand pemilik invoice, bukan sesi/Petunjuk Brand.
        BrandPelanggan::tetapkanUntukRequest(BrandPelanggan::untukNoReg($this->invoice->pelanggan?->no_reg));
    }

    /** Batas tombol Cek Status Pembayaran per tagihan per menit -- setiap klik menanyakan semua link ke gateway. */
    private const BATAS_CEK_PER_MENIT = 5;

    /**
     * Cek semua link pembayaran tagihan ke gateway (termasuk link lama). Pembayaran yang perlu diproses manual
     * hanya menampilkan pesan umum; alasan internalnya untuk staf.
     */
    public function cekStatusPembayaran(CekStatusPembayaranInvoice $cekStatus): void
    {
        $diizinkan = RateLimiter::attempt(
            'portal-cek-status-pembayaran:'.$this->invoice->id,
            self::BATAS_CEK_PER_MENIT,
            fn () => $this->jalankanCekStatus($cekStatus),
        );

        if (! $diizinkan) {
            Flux::toast(variant: 'warning', text: 'Terlalu sering mengecek. Silakan coba lagi dalam satu menit.');
        }
    }

    private function jalankanCekStatus(CekStatusPembayaranInvoice $cekStatus): void
    {
        try {
            $hasil = $cekStatus->periksa($this->invoice);
            $this->invoice->refresh();
        } catch (Exception $e) {
            report($e);
            Flux::toast(variant: 'danger', text: 'Gagal memperbarui status. Silakan coba lagi beberapa saat lagi.');

            return;
        }

        match (true) {
            $hasil->lunas => Flux::toast(variant: 'success', text: 'Pembayaran berhasil terkonfirmasi! Tagihan telah lunas.'),
            $hasil->perluDiprosesManual() => Flux::toast(variant: 'warning', text: 'Pembayaran Anda sedang kami periksa. Tim kami akan menghubungi Anda.'),
            default => Flux::toast(variant: 'info', text: 'Status tagihan: Menunggu pembayaran.'),
        };
    }

    /** Batas pembuatan pembayaran channel per tagihan per menit -- setiap ganti channel memanggil API gateway. */
    private const BATAS_PILIH_CHANNEL_PER_MENIT = 5;

    /**
     * Terbitkan nomor VA / kode bayar / QR untuk Channel Pembayaran pilihan pelanggan (ADR-0073).
     * Memilih ulang channel yang sama setelah kedaluwarsa (QRIS: 5 menit) membuat yang baru.
     */
    public function pilihChannel(int $channelId, PaymentGatewayManager $paymentManager): void
    {
        if (! $this->bolehDibayarOnline()) {
            return;
        }

        $channel = $paymentManager->channelTersedia()->firstWhere('id', $channelId);
        if (! $channel) {
            Flux::toast(variant: 'warning', text: 'Metode pembayaran ini sedang tidak tersedia.');

            return;
        }

        $diizinkan = RateLimiter::attempt(
            'portal-pilih-channel:'.$this->invoice->id,
            self::BATAS_PILIH_CHANNEL_PER_MENIT,
            fn () => $this->terbitkanPembayaranChannel($paymentManager, $channel),
        );

        if (! $diizinkan) {
            Flux::toast(variant: 'warning', text: 'Terlalu sering mengganti metode. Silakan coba lagi dalam satu menit.');
        }
    }

    private function terbitkanPembayaranChannel(PaymentGatewayManager $paymentManager, ChannelPembayaran $channel): void
    {
        try {
            $paymentManager->bayarLewatChannel($this->invoice, $channel);
        } catch (Exception $e) {
            report($e);
            Flux::toast(variant: 'danger', text: 'Gagal membuat pembayaran. Silakan coba lagi atau pilih metode lain.');
        }
    }

    private function bolehDibayarOnline(): bool
    {
        if ($this->invoice->refresh()->isLunas()) {
            Flux::toast(variant: 'success', text: 'Tagihan ini telah lunas.');

            return false;
        }

        if ($this->invoice->isDibatalkan()) {
            Flux::toast(variant: 'warning', text: 'Tagihan ini telah dibatalkan dan tidak dapat dibayar.');

            return false;
        }

        if ($this->invoice->layananPelanggan?->tidakLagiDitagih()) {
            Flux::toast(variant: 'warning', text: 'Layanan untuk tagihan ini sudah tidak aktif dan tidak lagi bisa dibayar online. Hubungi kami untuk penyelesaian tunggakan.');

            return false;
        }

        return true;
    }

    /**
     * Arahkan pelanggan ke tautan hosted payment page resmi payment gateway.
     */
    public function bayar(PaymentGatewayManager $paymentManager): mixed
    {
        if (! $this->bolehDibayarOnline()) {
            return null;
        }

        try {
            $paymentUrl = $paymentManager->resolvePaymentUrl($this->invoice);

            if ($paymentUrl) {
                return redirect()->away($paymentUrl);
            }

            if ($this->invoice->refresh()->isLunas()) {
                Flux::toast(variant: 'success', text: 'Tagihan ini telah lunas.');
            } else {
                Flux::toast(variant: 'danger', text: 'Gagal memuat tautan pembayaran gateway.');
            }
        } catch (Exception $e) {
            Flux::toast(variant: 'danger', text: 'Gagal memproses pembayaran: '.$e->getMessage());
        }

        return null;
    }

    public function render(): View
    {
        $bisaDibayarOnline = $this->invoice->isMenungguPembayaran() && ! $this->invoice->layananPelanggan?->tidakLagiDitagih();
        $channels = $bisaDibayarOnline ? app(PaymentGatewayManager::class)->channelTersedia() : collect();
        $instruksi = $channels->isEmpty() ? null : $this->invoice->transaksiPaymentGateways()
            ->where('channel', '!=', GatewayChannel::Invoice)
            ->where('status', StatusTransaksiGateway::Pending)
            ->latest('id')
            ->first();

        return view('livewire.portal.invoice.show', [
            'channels' => $channels,
            'instruksi' => $instruksi,
            'qrSvg' => $instruksi?->qr_string ? $this->qrSvg($instruksi->qr_string) : null,
        ]);
    }

    private function qrSvg(string $qrString): string
    {
        $renderer = new ImageRenderer(new RendererStyle(240, 1), new SvgImageBackEnd);

        return (new Writer($renderer))->writeString($qrString);
    }
}
