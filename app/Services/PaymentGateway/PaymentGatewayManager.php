<?php

namespace App\Services\PaymentGateway;

use App\Actions\LayananPelanggan\PerpanjangMasaAktifAction;
use App\Contracts\PaymentGateway\PaymentGatewayContract;
use App\DTO\PaymentGateway\PaymentCallbackData;
use App\DTO\PaymentGateway\PaymentLinkResponse;
use App\DTO\PaymentGateway\PingConnectionResult;
use App\Enums\GatewayChannel;
use App\Enums\MetodePembayaran;
use App\Enums\StatusInvoice;
use App\Enums\StatusTransaksiGateway;
use App\Enums\StatusWebhookLog;
use App\Events\InvoicePaidEvent;
use App\Models\Invoice;
use App\Models\Pembayaran;
use App\Models\PengaturanGateway;
use App\Models\TransaksiPaymentGateway;
use App\Models\WebhookLog;
use App\Services\PaymentGateway\Drivers\XenditDriver;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class PaymentGatewayManager
{
    /**
     * @var array<string, PaymentGatewayContract>
     */
    protected array $drivers = [];

    public function __construct()
    {
        // Daftarkan driver bawaan.
        // IpaymuDriver sengaja TIDAK didaftarkan: verifikasi signature-nya belum
        // diimplementasikan, sehingga mendaftarkannya akan membuka endpoint webhook publik
        // yang dapat dipalsukan. Daftarkan kembali hanya setelah verifikasi HMAC iPaymu selesai.
        $this->registerDriver('xendit', new XenditDriver);
    }

    /**
     * Daftarkan driver baru ke manager.
     */
    public function registerDriver(string $name, PaymentGatewayContract $driver): void
    {
        $this->drivers[strtolower($name)] = $driver;
    }

    /**
     * Dapatkan daftar provider yang didukung sistem.
     *
     * @return array<string, string>
     */
    public function getSupportedProviders(): array
    {
        $list = [];
        foreach ($this->drivers as $name => $driver) {
            $list[$name] = $driver->getProviderLabel();
        }

        return $list;
    }

    /**
     * Resolve instance driver.
     */
    public function driver(?string $provider = null): PaymentGatewayContract
    {
        if (empty($provider)) {
            $setting = PengaturanGateway::getDefault();
            $provider = $setting ? $setting->provider : 'xendit';
        }

        $normalized = strtolower(trim($provider));

        if (! isset($this->drivers[$normalized])) {
            throw new InvalidArgumentException("Payment gateway driver [{$provider}] tidak ditemukan atau belum didukung.");
        }

        return $this->drivers[$normalized];
    }

    /**
     * Dapatkan konfigurasi setting untuk provider atau default setting.
     */
    public function getSetting(?string $provider = null): PengaturanGateway
    {
        if (! empty($provider)) {
            $setting = PengaturanGateway::getSettingForProvider($provider);
            if ($setting) {
                return $setting;
            }
        }

        $default = PengaturanGateway::getDefault();
        if ($default) {
            return $default;
        }

        return PengaturanGateway::getXenditSetting();
    }

    /**
     * Koneksi yang kredensialnya dipakai untuk callback dan cek status sebuah transaksi:
     * koneksi penerbitnya, atau koneksi aktif & default bila transaksi tidak dikenal atau
     * dibuat sebelum koneksi penerbit dicatat (ADR-0067).
     */
    public function settingUntukTransaksi(?TransaksiPaymentGateway $transaksi, ?string $provider = null): PengaturanGateway
    {
        return $transaksi->pengaturanGateway ?? $this->getSetting($provider ?: $transaksi?->gateway);
    }

    /**
     * Terbitkan link pembayaran gateway resmi untuk invoice.
     *
     * Urutan sengaja: reservasi baris TransaksiPaymentGateway lokal DULU (dengan
     * external_id dan total_tagihan final, termasuk fee) sebelum memanggil API gateway.
     * Jika panggilan API gagal/timeout, baris Pending ini tertinggal sebagai jejak yang
     * dapat direkonsiliasi (lihat RekonsiliasiPembayaranCommand). Jika panggilan API
     * berhasil tapi update baris ini setelahnya gagal, invoice yang sudah punya link
     * pembayaran tetap tertaut ke baris transaksi dengan nominal yang benar (termasuk
     * fee) -- webhook nantinya tetap menemukan baris ini via external_id, sehingga tidak
     * lagi jatuh ke pembanding jumlah_setelah_promo tanpa fee yang memicu anomali palsu.
     *
     * Checkout yang tidak menghitung biaya sendiri (Xendit) selalu per metode; pemanggil tanpa metode
     * mendapat Virtual Account. Pemakaian ulang dan penerbitan dijaga lock per invoice+metode agar dua
     * klik bersamaan berbagi satu link (ADR-0073).
     */
    public function buatPaymentLink(
        Invoice $invoice,
        ?string $provider = null,
        bool $forceRegenerate = false,
        ?GatewayChannel $metode = null,
    ): TransaksiPaymentGateway {
        $setting = $this->getSetting($provider);
        $driver = $this->driver($setting->provider);

        if (! $driver->menghitungBiayaSendiri()) {
            $metode ??= GatewayChannel::VirtualAccount;
        }

        return Cache::lock("link-bayar:{$invoice->id}:".($metode->value ?? 'semua'), 60)->block(
            40,
            fn () => (! $forceRegenerate ? $this->transaksiAktif($invoice, $setting, $metode) : null)
                ?? $this->terbitkanLink($invoice, $setting, $driver, $metode),
        );
    }

    /**
     * Reservasi baris transaksi lokal SEBELUM memanggil API gateway, lalu minta driver membuat link resmi.
     * Biaya dihitung sekali di sini dan diteruskan ke driver, sehingga total_tagihan sama persis dengan
     * nominal yang dikirim ke gateway dan dilaporkan webhook. Gateway yang menghitung biaya sendiri
     * (iPaymu) menerima nominal tagihan tanpa biaya.
     */
    private function terbitkanLink(Invoice $invoice, PengaturanGateway $setting, PaymentGatewayContract $driver, ?GatewayChannel $metode): TransaksiPaymentGateway
    {
        $externalId = $driver->generateExternalId($invoice);
        $fee = $metode ? $setting->hitungFee($metode, (float) $invoice->jumlah_setelah_promo) : 0;
        $totalTagihanEstimasi = (int) round((float) $invoice->jumlah_setelah_promo) + $fee;

        $transaksi = TransaksiPaymentGateway::create([
            'invoice_id' => $invoice->id,
            'gateway' => $driver->getProviderName(),
            'pengaturan_gateway_id' => $setting->id,
            'external_id' => $externalId,
            'channel' => $metode ?? GatewayChannel::Invoice,
            'total_tagihan' => $totalTagihanEstimasi,
            'fee_gateway' => $fee,
            'status' => StatusTransaksiGateway::Pending,
            'payload_request' => [
                'provider' => $driver->getProviderName(),
                'invoice_no' => $invoice->no_invoice,
                'external_id' => $externalId,
                'amount' => $totalTagihanEstimasi,
            ],
        ]);

        /** @var PaymentLinkResponse $response */
        $response = $driver->createPaymentLink($invoice, $setting, $externalId, $metode, $fee);

        return DB::transaction(function () use ($invoice, $driver, $response, $transaksi) {
            $invoice->update([
                'payment_gateway_url' => $response->paymentUrl,
                'payment_gateway_id' => $response->paymentId,
                'payment_gateway_provider' => $driver->getProviderName(),
                'payment_gateway_status' => 'PENDING',
                'payment_gateway_expired_at' => $response->expiredAt,
                // Kompatibilitas mundur
                'xendit_invoice_id' => $response->paymentId,
                'xendit_invoice_url' => $response->paymentUrl,
                'xendit_status' => 'PENDING',
                'xendit_expired_at' => $response->expiredAt,
            ]);

            $transaksi->update([
                'xendit_reference_id' => $response->paymentId,
                'channel' => $response->channel,
                'channel_detail' => $response->channelDetail,
                'nomor_pembayaran' => $response->paymentUrl,
                'qr_string' => $response->qrString,
                'total_tagihan' => $response->amount,
                'expired_at' => $response->expiredAt,
                'payload_response' => $response->rawResponse,
            ]);

            return $transaksi;
        });
    }

    /**
     * Sinkronkan status lalu pastikan invoice punya tautan pembayaran aktif, kembalikan URL
     * hosted payment page resmi -- null jika invoice sudah lunas atau tautan gagal diterbitkan.
     * Titik reuse tunggal untuk semua tombol "Bayar Sekarang" (portal login maupun tautan publik).
     */
    public function resolvePaymentUrl(Invoice $invoice, ?GatewayChannel $metode = null): ?string
    {
        $invoice->refresh();

        if ($invoice->isLunas()) {
            return null;
        }

        if ($invoice->layananPelanggan?->tidakLagiDitagih()) {
            return null;
        }

        if (! empty($invoice->payment_gateway_id) || ! empty($invoice->xendit_invoice_id)) {
            $this->sinkronkanStatus($invoice);
            $invoice->refresh();

            if ($invoice->isLunas()) {
                return null;
            }
        }

        return $this->buatPaymentLink($invoice, metode: $metode)->nomor_pembayaran ?: null;
    }

    /**
     * Link Pending yang masih berlaku untuk metode ini dari koneksi yang sama.
     */
    private function transaksiAktif(Invoice $invoice, PengaturanGateway $setting, ?GatewayChannel $metode): ?TransaksiPaymentGateway
    {
        return TransaksiPaymentGateway::where('invoice_id', $invoice->id)
            ->where('pengaturan_gateway_id', $setting->id)
            ->where('channel', $metode ?? GatewayChannel::Invoice)
            ->where('status', StatusTransaksiGateway::Pending)
            ->where('expired_at', '>', now())
            ->whereNotNull('nomor_pembayaran')
            ->latest('id')
            ->first();
    }

    /**
     * Sinkronisasikan status invoice langsung ke API gateway melalui transaksi terakhirnya.
     *
     * @return array<string, mixed>
     */
    public function sinkronkanStatus(Invoice $invoice): array
    {
        $transaksi = $invoice->transaksiPaymentGatewayAktif();

        if ($transaksi) {
            return $this->sinkronkanTransaksi($transaksi);
        }

        $provider = $invoice->payment_gateway_provider ?: 'xendit';
        $statusData = $this->driver($provider)->checkStatus($invoice, $this->getSetting($provider));

        return $this->terapkanStatusGateway($invoice, null, $provider, $statusData);
    }

    /**
     * Sinkronisasikan satu transaksi ke gateway memakai kredensial koneksi penerbitnya.
     * Dipakai juga untuk transaksi lama (bukan transaksi terakhir invoice), misalnya oleh
     * pengecekan kedaluwarsa dan perintah pemulihan pembayaran.
     *
     * @return array<string, mixed>
     */
    public function sinkronkanTransaksi(TransaksiPaymentGateway $transaksi): array
    {
        return $this->terapkanStatusTransaksi($transaksi, $this->cekStatusTransaksi($transaksi));
    }

    /**
     * Terapkan status gateway yang sudah ditanyakan lewat `cekStatusTransaksi` tanpa memanggil
     * gateway lagi.
     *
     * @param  array<string, mixed>  $statusData
     * @return array<string, mixed>
     */
    public function terapkanStatusTransaksi(TransaksiPaymentGateway $transaksi, array $statusData): array
    {
        $invoice = $transaksi->invoice;

        if (! $invoice) {
            return $statusData;
        }

        return $this->terapkanStatusGateway($invoice, $transaksi, $this->providerTransaksi($transaksi), $statusData);
    }

    /**
     * Tanyakan status transaksi ke gateway tanpa mengubah data lokal.
     *
     * @return array<string, mixed>
     */
    public function cekStatusTransaksi(TransaksiPaymentGateway $transaksi): array
    {
        $provider = $this->providerTransaksi($transaksi);

        return $this->driver($provider)->checkStatus($transaksi, $this->settingUntukTransaksi($transaksi, $provider));
    }

    protected function providerTransaksi(TransaksiPaymentGateway $transaksi): string
    {
        return $transaksi->gateway ?: ($transaksi->invoice?->payment_gateway_provider ?: 'xendit');
    }

    /**
     * @param  array<string, mixed>  $statusData
     * @return array<string, mixed>
     */
    protected function terapkanStatusGateway(
        Invoice $invoice,
        ?TransaksiPaymentGateway $transaksi,
        string $provider,
        array $statusData
    ): array {
        $statusStr = strtoupper((string) ($statusData['status'] ?? ''));

        if ($this->isMissingRemoteInvoice($statusData)) {
            if (($statusData['error_code'] ?? null) === 'ambiguous_external_id') {
                return $statusData;
            }

            $this->invalidateMissingRemoteInvoice($invoice, $transaksi);

            return $statusData;
        }

        if ($this->transaksiTerakhir($invoice, $transaksi)) {
            $this->adoptRecoveredRemoteReference($invoice, $statusData);
        }

        $this->recordStatusSync($provider, $statusStr, $statusData, $transaksi);
        $this->applySyncedStatus($invoice, $statusStr, $statusData, $transaksi, $provider);

        return $statusData;
    }

    /**
     * Hanya transaksi terakhir yang boleh mengubah tautan pembayaran milik invoice; transaksi
     * lama yang kedaluwarsa tidak boleh menghapus tautan baru yang masih aktif.
     */
    protected function transaksiTerakhir(Invoice $invoice, ?TransaksiPaymentGateway $transaksi): bool
    {
        return $transaksi === null || $invoice->transaksiPaymentGatewayAktif()?->is($transaksi) === true;
    }

    /**
     * @param  array<string, mixed>  $statusData
     */
    protected function recordStatusSync(
        string $provider,
        string $statusStr,
        array $statusData,
        ?TransaksiPaymentGateway $transaksi
    ): void {
        if (! $transaksi || empty($statusData) || isset($statusData['error'])) {
            return;
        }

        $transaksi->update([
            'payload_response' => $statusData,
            'provider_reference_id' => $statusData['id'] ?? $transaksi->provider_reference_id,
            'xendit_reference_id' => $statusData['id'] ?? $transaksi->xendit_reference_id,
        ]);

        $eventId = (string) ($statusData['id'] ?? ($transaksi->provider_reference_id ?: $transaksi->external_id));
        if (empty($eventId)) {
            return;
        }

        WebhookLog::firstOrCreate(
            [
                'provider' => $provider,
                'provider_event_id' => $eventId,
            ],
            [
                'transaksi_payment_gateway_id' => $transaksi->id,
                'event_type' => "sync.{$provider}",
                'xendit_event_id' => $eventId,
                'payload' => $statusData,
                'status_proses' => in_array($statusStr, ['PAID', 'SETTLED', 'SUCCEEDED', 'BERHASIL', 'EXPIRED'], true) ? StatusWebhookLog::Diproses : StatusWebhookLog::Diterima,
                'diterima_pada' => Carbon::now(),
            ]
        );
    }

    /**
     * Status PAID memakai waktu bayar asli dari gateway (`paid_at`) agar tanggal lunas dan
     * perpanjangan masa aktif tidak bergeser ke waktu sinkron berjalan.
     *
     * @param  array<string, mixed>  $statusData
     */
    protected function applySyncedStatus(
        Invoice $invoice,
        string $statusStr,
        array $statusData,
        ?TransaksiPaymentGateway $transaksi,
        string $provider
    ): void {
        if (in_array($statusStr, ['PAID', 'SETTLED', 'SUCCEEDED', 'BERHASIL'], true)) {
            $callbackData = new PaymentCallbackData(
                provider: $provider,
                externalId: $transaksi?->external_id ?: $invoice->no_invoice,
                status: 'PAID',
                paidAmount: (float) ($statusData['paid_amount'] ?? ($statusData['amount'] ?? $invoice->jumlah_setelah_promo)),
                eventId: (string) ($statusData['id'] ?? null),
                paidAt: (string) ($statusData['paid_at'] ?? now()->toIso8601String()),
                rawPayload: $statusData
            );

            $this->prosesPelunasan($invoice, $callbackData, $transaksi);
            $invoice->refresh();

            return;
        }

        if ($statusStr === 'EXPIRED' || $statusStr === 'BATAL') {
            $this->invalidateExpiredInvoice($invoice, $transaksi);
        }
    }

    /**
     * Gateway menyatakan transaksi kedaluwarsa/gagal: tandai transaksinya Expired, dan hapus
     * tautan pembayaran invoice hanya bila ini transaksi terakhirnya -- callback atau sync
     * untuk transaksi lama tidak boleh mematikan tautan baru yang masih aktif (ADR-0067).
     *
     * @param  array<string, mixed>|null  $payloadGateway
     */
    public function invalidateExpiredInvoice(Invoice $invoice, ?TransaksiPaymentGateway $transaksi, ?array $payloadGateway = null): void
    {
        $transaksiTerakhir = $this->transaksiTerakhir($invoice, $transaksi);

        DB::transaction(function () use ($invoice, $transaksi, $transaksiTerakhir, $payloadGateway): void {
            /** @var Invoice $lockedInvoice */
            $lockedInvoice = Invoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            if ($transaksi && $transaksi->status === StatusTransaksiGateway::Pending) {
                $transaksi->update(array_filter([
                    'status' => StatusTransaksiGateway::Expired,
                    'payload_response' => $payloadGateway,
                ]));
            }

            if ($lockedInvoice->isLunas() || ! $transaksiTerakhir) {
                return;
            }

            $lockedInvoice->update([
                'payment_gateway_url' => null,
                'payment_gateway_status' => 'EXPIRED',
                'xendit_invoice_url' => null,
                'xendit_status' => 'EXPIRED',
            ]);
        });
    }

    /**
     * Xendit mengembalikan pesan ini saat ID invoice tidak ada pada akun/key aktif.
     * Link lokal harus dianggap tidak valid agar pembayaran berikutnya membuat invoice baru.
     *
     * @param  array<string, mixed>  $statusData
     */
    protected function isMissingRemoteInvoice(array $statusData): bool
    {
        return str_contains(
            strtolower((string) ($statusData['error'] ?? '')),
            'could not find invoice by id'
        ) || ($statusData['error_code'] ?? null) === 'external_id_not_found';
    }

    /**
     * @param  array<string, mixed>  $statusData
     */
    protected function adoptRecoveredRemoteReference(Invoice $invoice, array $statusData): void
    {
        if (($statusData['recovered_by'] ?? null) !== 'external_id' || empty($statusData['id'])) {
            return;
        }

        $invoice->update([
            'payment_gateway_id' => $statusData['id'],
            'payment_gateway_url' => $statusData['invoice_url'] ?? $invoice->payment_gateway_url,
            'payment_gateway_status' => $statusData['status'] ?? $invoice->payment_gateway_status,
            'xendit_invoice_id' => $statusData['id'],
            'xendit_invoice_url' => $statusData['invoice_url'] ?? $invoice->xendit_invoice_url,
            'xendit_status' => $statusData['status'] ?? $invoice->xendit_status,
        ]);
    }

    /**
     * Invoice tidak ditemukan di gateway: lepaskan referensinya dari invoice agar pembayaran
     * berikutnya membuat invoice baru (ADR-0065). Status transaksi sengaja tidak diubah --
     * "tidak ditemukan" bukan pernyataan kedaluwarsa dari gateway (ADR-0067).
     */
    protected function invalidateMissingRemoteInvoice(Invoice $invoice, ?TransaksiPaymentGateway $transaksi): void
    {
        $transaksiTerakhir = $this->transaksiTerakhir($invoice, $transaksi);

        DB::transaction(function () use ($invoice, $transaksiTerakhir): void {
            /** @var Invoice $lockedInvoice */
            $lockedInvoice = Invoice::query()
                ->whereKey($invoice->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedInvoice->isLunas() || ! $transaksiTerakhir) {
                return;
            }

            $lockedInvoice->update([
                'payment_gateway_url' => null,
                'payment_gateway_id' => null,
                'payment_gateway_status' => 'EXPIRED',
                'xendit_invoice_url' => null,
                'xendit_invoice_id' => null,
                'xendit_status' => 'EXPIRED',
            ]);
        });
    }

    /**
     * Channel yang benar-benar dipakai membayar bila callback menyebutnya; callback tanpa channel
     * (Payment Session melapor `invoice` generik) tidak menimpa metode link.
     */
    private function channelTerbayar(string $channel, TransaksiPaymentGateway $transaksi): GatewayChannel
    {
        $dariCallback = GatewayChannel::tryFrom($channel);

        return $dariCallback === null || $dariCallback === GatewayChannel::Invoice ? $transaksi->channel : $dariCallback;
    }

    /**
     * Invoice sudah lunas lewat jalur lain (link metode lain atau pelunasan manual) dan transaksi ini belum
     * tercatat lunas: uangnya masuk dua kali, jadi staf menanganinya manual (refund), bukan diabaikan.
     */
    private function pembayaranGanda(Invoice $invoice, TransaksiPaymentGateway $transaksi): bool
    {
        return $invoice->isLunas()
            && $transaksi->status !== StatusTransaksiGateway::Paid
            && $invoice->pembayarans()
                ->whereNotIn('referensi_transaksi', array_filter([$transaksi->external_id, $transaksi->provider_reference_id, $transaksi->xendit_reference_id]))
                ->exists();
    }

    /**
     * Eksekusi transaksi pelunasan invoice di database dengan row locking dan dispatch event post-commit.
     *
     * @param  PaymentCallbackData|array<string, mixed>  $payloadOrData
     */
    public function prosesPelunasan(
        Invoice $invoice,
        PaymentCallbackData|array $payloadOrData,
        ?TransaksiPaymentGateway $transaksi = null,
        ?WebhookLog $webhookLog = null
    ): bool {
        $eventToDispatch = null;

        if ($payloadOrData instanceof PaymentCallbackData) {
            $callbackData = $payloadOrData;
            $rawPayload = $callbackData->rawPayload;
            $amount = $callbackData->paidAmount;
            $paidAtStr = $callbackData->paidAt;
            $channel = $callbackData->channel->value;
            $channelDetail = $callbackData->channelDetail;
            $paymentRef = $callbackData->paymentReference;
            $provider = $callbackData->provider;
        } else {
            $rawPayload = $payloadOrData;
            $provider = (string) ($rawPayload['provider'] ?? 'xendit');
            $amount = (float) ($rawPayload['paid_amount'] ?? ($rawPayload['amount'] ?? $invoice->jumlah_setelah_promo));
            $paidAtStr = (string) ($rawPayload['paid_at'] ?? now()->toIso8601String());
            $channel = (string) ($rawPayload['channel'] ?? 'invoice');
            $channelDetail = (string) ($rawPayload['channel_detail'] ?? null);
            $paymentRef = (string) ($rawPayload['payment_id'] ?? ($rawPayload['id'] ?? null));
        }

        // Diperiksa SEBELUM transaksi diganti transaksi aktif invoice: callback yang tidak
        // menyebut transaksi dikenal diverifikasi dengan koneksi default, jadi koneksi itulah
        // yang menentukan apakah uangnya uang sungguhan.
        $dariSandbox = $this->dariKoneksiSandbox($transaksi, $provider);

        if (! $transaksi) {
            $transaksi = $invoice->transaksiPaymentGatewayAktif();
        }

        if ($dariSandbox) {
            $catatan = "Pembayaran sandbox untuk Invoice {$invoice->no_invoice} diabaikan: transaksi koneksi sandbox tidak melunasi invoice.";
            $webhookLog?->update([
                'status_proses' => StatusWebhookLog::Diabaikan,
                'catatan_error' => $catatan,
            ]);
            Log::warning($catatan, ['transaksi_id' => $transaksi?->id]);

            return false;
        }

        DB::transaction(function () use ($invoice, $transaksi, $webhookLog, $rawPayload, $amount, $paidAtStr, $channel, $channelDetail, $paymentRef, $provider, &$eventToDispatch) {
            /** @var Invoice $lockedInvoice */
            $lockedInvoice = Invoice::where('id', $invoice->id)->lockForUpdate()->firstOrFail();

            if ($transaksi && $this->pembayaranGanda($lockedInvoice, $transaksi)) {
                $catatan = "Pembayaran ganda {$provider}: Invoice {$lockedInvoice->no_invoice} sudah lunas lewat pembayaran lain; transaksi {$transaksi->external_id} perlu penanganan manual (refund).";
                $transaksi->update(['status' => StatusTransaksiGateway::Paid, 'payload_response' => $rawPayload]);
                $webhookLog?->update(['status_proses' => StatusWebhookLog::Gagal, 'catatan_error' => $catatan]);
                Log::error($catatan, ['invoice_id' => $lockedInvoice->id, 'transaksi_id' => $transaksi->id]);

                return;
            }

            // Guard clause idempotensi: callback ulang untuk pembayaran yang sudah tercatat
            if ($lockedInvoice->isLunas()) {
                if ($transaksi) {
                    $transaksi->update([
                        'status' => StatusTransaksiGateway::Paid,
                        'payload_response' => $rawPayload,
                    ]);
                }
                $lockedInvoice->update([
                    'payment_gateway_status' => 'PAID',
                    'xendit_status' => 'PAID',
                ]);
                if ($webhookLog) {
                    $webhookLog->update(['status_proses' => StatusWebhookLog::Diproses]);
                }

                return;
            }

            $dibayarPada = $paidAtStr ? Carbon::parse($paidAtStr) : Carbon::now();

            // 1. Update Transaksi Payment Gateway
            if ($transaksi) {
                $transaksi->update([
                    'status' => StatusTransaksiGateway::Paid,
                    'provider_reference_id' => $paymentRef ?: $transaksi->provider_reference_id,
                    'xendit_reference_id' => $paymentRef ?: $transaksi->xendit_reference_id,
                    'payload_response' => $rawPayload,
                    'channel' => $this->channelTerbayar($channel, $transaksi),
                    'channel_detail' => $channelDetail ?: $transaksi->channel_detail,
                ]);
            }

            // 2. Buat / Dapatkan Record Pembayaran secara idempoten
            $channelDetailText = $channelDetail ? strtoupper($channelDetail) : '';
            $nominalBayar = $amount > 0 ? $amount : (float) ($transaksi->total_tagihan ?? $lockedInvoice->jumlah_setelah_promo);
            $providerLabel = strtoupper($provider);
            $refTrx = $paymentRef ?: ($transaksi->external_id ?? $lockedInvoice->no_invoice);

            $pembayaran = Pembayaran::firstOrCreate(
                [
                    'metode' => MetodePembayaran::PaymentGateway,
                    'referensi_transaksi' => $refTrx,
                ],
                [
                    'invoice_id' => $lockedInvoice->id,
                    'jumlah_dibayar' => $nominalBayar,
                    'dibayar_pada' => $dibayarPada,
                    'catatan' => trim("Pembayaran otomatis {$providerLabel} {$channel} {$channelDetailText}"),
                ]
            );

            // 3. Update Status Invoice
            $lockedInvoice->update([
                'status' => StatusInvoice::Lunas,
                'tanggal_lunas' => $dibayarPada->copy()->setTimezone(config('app.zona_waktu_bisnis'))->toDateString(),
                'metode_pembayaran' => MetodePembayaran::PaymentGateway,
                'payment_gateway_status' => 'PAID',
                'xendit_status' => 'PAID',
            ]);

            // 4. Perpanjang Masa Aktif Layanan Pelanggan -- aturan tunggal isFuture()
            // yang sama dengan jalur pembayaran manual, lihat PerpanjangMasaAktifAction.
            $layanan = $lockedInvoice->layananPelanggan()->lockForUpdate()->first();
            if ($layanan) {
                app(PerpanjangMasaAktifAction::class)->execute($layanan, $lockedInvoice, $dibayarPada);
            }

            // 5. Update Status Log Webhook
            if ($webhookLog) {
                $webhookLog->update(['status_proses' => StatusWebhookLog::Diproses]);
            }

            // Siapkan event untuk dipancarkan setelah commit DB
            $eventToDispatch = new InvoicePaidEvent($lockedInvoice, $pembayaran);
        });

        if ($eventToDispatch) {
            event($eventToDispatch);
        }

        return true;
    }

    /**
     * Uang mode test bukan uang sungguhan: di production, transaksi yang diterbitkan koneksi
     * sandbox tidak pernah melunasi invoice (ADR-0067). Di luar production (lokal, staging),
     * sandbox tetap boleh melunasi agar alur pembayaran dapat diuji ujung ke ujung.
     */
    protected function dariKoneksiSandbox(?TransaksiPaymentGateway $transaksi, string $provider): bool
    {
        return app()->isProduction() && $this->settingUntukTransaksi($transaksi, $provider)->sandbox_mode === true;
    }

    /**
     * Cek koneksi API gateway.
     */
    public function pingConnection(PengaturanGateway $setting): PingConnectionResult
    {
        $driver = $this->driver($setting->provider);

        return $driver->pingConnection($setting);
    }
}
