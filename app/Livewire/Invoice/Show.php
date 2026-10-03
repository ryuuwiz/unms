<?php

namespace App\Livewire\Invoice;

use App\Enums\MetodePembayaran;
use App\Enums\Wa\KategoriTemplateWa;
use App\Models\Invoice;
use App\Models\Pembayaran;
use App\Models\WaTemplate;
use App\Services\Billing\BillingService;
use App\Services\PaymentGateway\CekStatusPembayaranInvoice;
use App\Services\Whatsapp\WhatsappService;
use Exception;
use Flux\Flux;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Throwable;

#[Layout('layouts.app')]
#[Title('Detail Invoice')]
class Show extends Component
{
    public Invoice $invoice;

    public bool $showBayarModal = false;

    public string $metode = 'manual_admin';

    public ?float $jumlah_dibayar = 0.0;

    public string $referensi_transaksi = '';

    public string $dibayar_pada = '';

    public string $catatan = '';

    public string $testPhone = '';

    /** @var array{lunas: bool, alasanManual: string|null, galat: string|null}|null */
    public ?array $hasilCekStatus = null;

    public function mount(Invoice $invoice): void
    {
        $this->authorize('view', $invoice);
        $this->invoice = $invoice->load(['pelanggan', 'layananPelanggan.paketLayanan', 'layananPelanggan.router', 'promo', 'pembayarans.dicatatOleh', 'dibuatOleh', 'transaksiPaymentGateways' => fn ($q) => $q->latest('id')]);

        $this->jumlah_dibayar = (float) $invoice->jumlah_setelah_promo;
        $this->dibayar_pada = Carbon::now()->format('Y-m-d\TH:i');
    }

    /**
     * Cek semua link pembayaran invoice ke gateway (termasuk link lama); PAID melunasi invoice lewat sinkron biasa.
     */
    public function cekStatusPembayaran(CekStatusPembayaranInvoice $cekStatus): void
    {
        $this->authorize('viewAny', Pembayaran::class);

        try {
            $this->hasilCekStatus = $cekStatus->periksa($this->invoice)->toArray();
            $this->invoice->refresh()->load(['pembayarans.dicatatOleh', 'transaksiPaymentGateways' => fn ($q) => $q->latest('id')]);
        } catch (Throwable $e) {
            report($e);
            Flux::toast(variant: 'danger', text: 'Gagal mengecek status pembayaran: '.$e->getMessage());
        }
    }

    public function openBayarModal(): void
    {
        $this->showBayarModal = true;
        $this->jumlah_dibayar = (float) $this->invoice->jumlah_setelah_promo;
        $this->dibayar_pada = Carbon::now()->format('Y-m-d\TH:i');
        $this->referensi_transaksi = '';
        $this->catatan = '';
    }

    public function prosesBayar(BillingService $billingService): void
    {
        $this->authorize('create', Pembayaran::class);

        $this->validate([
            'metode' => ['required', 'string', 'in:manual_admin'],
            'jumlah_dibayar' => ['required', 'numeric', Rule::in([(float) $this->invoice->jumlah_setelah_promo])],
            'dibayar_pada' => ['required', 'date'],
            'referensi_transaksi' => ['nullable', 'string', 'max:100'],
            'catatan' => ['nullable', 'string', 'max:500'],
        ], [
            // Pembayaran manual wajib melunasi penuh -- lihat BillingService::prosesPembayaranManual().
            'jumlah_dibayar.in' => 'Nominal pembayaran harus sama persis dengan jumlah tagihan (Rp '.number_format((float) $this->invoice->jumlah_setelah_promo, 0, ',', '.').'). Sistem belum mendukung pembayaran sebagian.',
        ]);

        try {
            $billingService->prosesPembayaranManual(
                invoice: $this->invoice,
                payload: [
                    'metode' => $this->metode,
                    'jumlah_dibayar' => $this->jumlah_dibayar,
                    'referensi_transaksi' => $this->referensi_transaksi ?: null,
                    'dibayar_pada' => $this->dibayar_pada,
                    'catatan' => $this->catatan ?: null,
                ],
                actor: auth('web')->user()
            );

            Flux::toast(variant: 'success', text: "Pembayaran untuk {$this->invoice->no_invoice} berhasil dicatat & layanan telah diperpanjang!");
            $this->showBayarModal = false;
            $this->invoice->refresh()->load(['pembayarans.dicatatOleh', 'layananPelanggan']);
        } catch (Exception $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());
        }
    }

    /**
     * Ganti token Tautan Tagihan: tautan lama yang sudah beredar langsung tidak berlaku.
     */
    public function gantiTautanTagihan(): void
    {
        $this->authorize('gantiTautan', $this->invoice);

        $this->invoice->gantiTokenTautan();

        activity('invoice')
            ->performedOn($this->invoice)
            ->causedBy(auth('web')->user())
            ->log('Tautan Tagihan diganti; tautan lama tidak berlaku.');

        Flux::toast(variant: 'success', text: 'Tautan Tagihan baru dibuat. Tautan lama sudah tidak berlaku.');
    }

    /**
     * Kirim uji coba notifikasi tagihan via WhatsApp memakai jalur pengiriman produksi yang sama
     * (WhatsappService::antrikanPesan) -- super_admin only, lihat InvoicePolicy::kirimUjiCoba().
     */
    public function kirimUjiCobaTagihan(WhatsappService $whatsappService): void
    {
        $this->authorize('kirimUjiCoba', $this->invoice);

        $this->validate([
            'testPhone' => ['required', 'string', 'min:9', 'max:20'],
        ]);

        $template = WaTemplate::active()->kategori(KategoriTemplateWa::Tagihan)->orderBy('id')->first();

        if (! $template) {
            Flux::toast(variant: 'danger', text: 'Tidak ada template WA kategori Tagihan yang aktif.');

            return;
        }

        $whatsappService->antrikanPesan(
            noHp: $this->testPhone,
            kodeTemplate: $template->kode,
            params: $whatsappService->buildInvoiceParams($this->invoice),
            jenis: 'uji_coba_tagihan'
        );

        Flux::toast(variant: 'success', text: 'Pesan uji coba tagihan berhasil dikirim.');
    }

    public function render(): View
    {
        return view('livewire.invoice.show', [
            'metodes' => MetodePembayaran::cases(),
        ]);
    }
}
