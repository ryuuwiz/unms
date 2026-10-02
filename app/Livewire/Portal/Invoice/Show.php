<?php

namespace App\Livewire\Portal\Invoice;

use App\Enums\StatusLayanan;
use App\Livewire\Portal\Invoice\Concerns\AuthorizesInvoiceAccess;
use App\Models\Invoice;
use App\Services\PaymentGateway\CekStatusPembayaranInvoice;
use App\Services\PaymentGateway\PaymentGatewayManager;
use App\Support\BrandPelanggan;
use Exception;
use Flux\Flux;
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

    /**
     * Cek semua link pembayaran tagihan ke gateway (termasuk link lama). Kasus yang dilaporkan
     * hanya menampilkan pesan umum; alasan internalnya untuk staf.
     */
    public function sinkronkanStatus(CekStatusPembayaranInvoice $cekStatus): void
    {
        try {
            $hasil = $cekStatus->periksa($this->invoice);
            $this->invoice->refresh();

            Flux::toast(variant: $hasil->lunas ? 'success' : ($hasil->dilaporkan() ? 'warning' : 'info'), text: $hasil->pesanPelanggan());
        } catch (Exception $e) {
            report($e);
            Flux::toast(variant: 'danger', text: 'Gagal memperbarui status. Silakan coba lagi beberapa saat lagi.');
        }
    }

    /**
     * Arahkan pelanggan ke tautan hosted payment page resmi payment gateway.
     */
    public function bayar(PaymentGatewayManager $paymentManager): mixed
    {
        if ($this->invoice->isDibatalkan()) {
            Flux::toast(variant: 'warning', text: 'Tagihan ini telah dibatalkan dan tidak dapat dibayar.');

            return null;
        }

        if ($this->invoice->layananPelanggan?->status === StatusLayanan::Berhenti) {
            Flux::toast(variant: 'warning', text: 'Layanan untuk tagihan ini sudah berhenti dan tidak lagi bisa dibayar online. Hubungi kami untuk penyelesaian tunggakan.');

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
        return view('livewire.portal.invoice.show');
    }
}
