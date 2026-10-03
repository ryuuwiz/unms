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
use App\Models\ChannelPembayaran;
use App\Models\Invoice;
use App\Models\Pembayaran;
use App\Models\PengaturanGateway;
use App\Models\TransaksiPaymentGateway;
use App\Models\WebhookLog;
use App\Services\PaymentGateway\Drivers\IpaymuDriver;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
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
        $this->registerDriver('ipaymu', new IpaymuDriver);
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
            $provider = $setting ? $setting->provider : 'ipaymu';
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

        throw new InvalidArgumentException('Belum ada Koneksi Gateway yang dikonfigurasi.');
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
     */
    public function buatPaymentLink(
        Invoice $invoice,
        ?string $provider = null,
        bool $forceRegenerate = false
    ): TransaksiPaymentGateway {
        // 1. Cek apakah invoice sudah memiliki link aktif yang belum kedaluwarsa
        if (! $forceRegenerate && $invoice->hasActivePaymentLink()) {
            $existingTrx = TransaksiPaymentGateway::where('invoice_id', $invoice->id)
                ->where('channel', GatewayChannel::Invoice)
                ->where('status', StatusTransaksiGateway::Pending)
                ->where('expired_at', '>', now())
                ->latest('id')
                ->first();

            if ($existingTrx) {
                return $existingTrx;
            }
        }

        $setting = $this->getSetting($provider);
        $driver = $this->driver($setting->provider);

        // 2. Reservasi baris transaksi lokal SEBELUM memanggil API gateway. Hosted Invoice tidak
        // menambah Fee Admin lokal: fee-nya dibebankan gateway lewat feeDirection (ADR-0072).
        $transaksi = $this->reservasiTransaksi($invoice, $setting, $driver, 0.0, GatewayChannel::Invoice, null);

        // 3. Minta driver membuat payment link resmi menggunakan external_id yang sudah direservasi
        $response = $driver->createPaymentLink($invoice, $setting, $transaksi->external_id);

        // 4. Update invoice lokal & baris transaksi hasil reservasi dalam satu transaksi DB
        return DB::transaction(function () use ($invoice, $driver, $response, $transaksi) {
            $invoice->update([
                'payment_gateway_url' => $response->paymentUrl,
                'payment_gateway_id' => $response->paymentId,
                'payment_gateway_provider' => $driver->getProviderName(),
                'payment_gateway_status' => 'PENDING',
                'payment_gateway_expired_at' => $response->expiredAt,
            ]);

            return $this->catatResponsTransaksi($transaksi, $response);
        });
    }

    /**
     * Channel Pembayaran yang boleh ditawarkan ke pelanggan saat ini (ADR-0073).
     *
     * @return Collection<int, ChannelPembayaran>
     */
    public function channelTersedia(): Collection
    {
        $provider = array_keys(array_filter($this->drivers, fn (PaymentGatewayContract $driver) => $driver->kodeChannel() !== []));

        return ChannelPembayaran::query()
            ->tersedia($provider)
            ->orderBy('tipe')
            ->orderBy('kode')
            ->get();
    }

    /**
     * Terbitkan pembayaran langsung pada satu Channel Pembayaran (ADR-0073). Transaksi Pending
     * channel yang sama dengan nominal sama dipakai ulang; ganti channel membuat transaksi baru
     * dan transaksi lama dibiarkan kedaluwarsa (pembayaran ganda ditangani pelunasan susulan).
     */
    public function bayarLewatChannel(Invoice $invoice, ChannelPembayaran $channel): TransaksiPaymentGateway
    {
        $nominal = (float) $invoice->jumlah_setelah_promo;
        $fee = $channel->hitungFee($nominal);

        $aktif = TransaksiPaymentGateway::where('invoice_id', $invoice->id)
            ->where('pengaturan_gateway_id', $channel->pengaturan_gateway_id)
            ->where('channel_detail', $channel->kode)
            ->where('status', StatusTransaksiGateway::Pending)
            ->where('expired_at', '>', now())
            ->where('total_tagihan', $nominal + $fee)
            ->latest('id')
            ->first();

        if ($aktif) {
            return $aktif;
        }

        $setting = $channel->pengaturanGateway;
        $driver = $this->driver($setting->provider);
        $transaksi = $this->reservasiTransaksi($invoice, $setting, $driver, $fee, $channel->tipe, $channel->kode);
        $response = $driver->createChannelPayment($invoice, $setting, $channel, $transaksi->external_id, $nominal + $fee);

        return DB::transaction(function () use ($invoice, $driver, $response, $transaksi) {
            $invoice->update([
                'payment_gateway_id' => $response->paymentId,
                'payment_gateway_provider' => $driver->getProviderName(),
                'payment_gateway_status' => 'PENDING',
                'payment_gateway_expired_at' => $response->expiredAt,
            ]);

            return $this->catatResponsTransaksi($transaksi, $response);
        });
    }

    /**
     * Reservasi baris transaksi lokal (external_id + total final termasuk fee) SEBELUM API gateway
     * dipanggil. Jika panggilan API gagal/timeout, baris Pending ini tertinggal sebagai jejak yang
     * dapat direkonsiliasi (RekonsiliasiPembayaranCommand); webhook menemukannya lewat external_id
     * sehingga tidak jatuh ke pembanding jumlah_setelah_promo tanpa fee yang memicu anomali palsu.
     */
    private function reservasiTransaksi(
        Invoice $invoice,
        PengaturanGateway $setting,
        PaymentGatewayContract $driver,
        float $fee,
        GatewayChannel $channel,
        ?string $channelDetail
    ): TransaksiPaymentGateway {
        $externalId = $driver->generateExternalId($invoice);
        $totalTagihan = (float) $invoice->jumlah_setelah_promo + $fee;

        return TransaksiPaymentGateway::create([
            'invoice_id' => $invoice->id,
            'gateway' => $driver->getProviderName(),
            'pengaturan_gateway_id' => $setting->id,
            'external_id' => $externalId,
            'channel' => $channel,
            'channel_detail' => $channelDetail,
            'total_tagihan' => $totalTagihan,
            'fee_gateway' => $fee,
            'status' => StatusTransaksiGateway::Pending,
            'payload_request' => [
                'provider' => $driver->getProviderName(),
                'invoice_no' => $invoice->no_invoice,
                'external_id' => $externalId,
                'amount' => $totalTagihan,
                'channel' => $channelDetail,
            ],
        ]);
    }

    private function catatResponsTransaksi(TransaksiPaymentGateway $transaksi, PaymentLinkResponse $response): TransaksiPaymentGateway
    {
        $transaksi->update([
            'provider_reference_id' => $response->paymentId,
            'channel' => $response->channel,
            'channel_detail' => $response->channelDetail,
            'nomor_pembayaran' => $response->paymentNumber ?? $response->paymentUrl,
            'qr_string' => $response->qrString,
            'total_tagihan' => $response->amount,
            'expired_at' => $response->expiredAt,
            'payload_response' => $response->rawResponse,
        ]);

        return $transaksi;
    }

    /**
     * Sinkronkan status lalu pastikan invoice punya tautan pembayaran aktif, kembalikan URL
     * hosted payment page resmi -- null jika invoice sudah lunas atau tautan gagal diterbitkan.
     * Titik reuse tunggal untuk semua tombol "Bayar Sekarang" (portal login maupun tautan publik).
     */
    public function resolvePaymentUrl(Invoice $invoice): ?string
    {
        $invoice->refresh();

        if ($invoice->isLunas()) {
            return null;
        }

        if ($invoice->layananPelanggan?->tidakLagiDitagih()) {
            return null;
        }

        if (! empty($invoice->payment_gateway_id)) {
            $this->sinkronkanStatus($invoice);
            $invoice->refresh();

            if ($invoice->isLunas()) {
                return null;
            }
        }

        if (! $invoice->hasActivePaymentLink()) {
            $this->buatPaymentLink($invoice, forceRegenerate: true);
            $invoice->refresh();
        }

        return $invoice->payment_gateway_url ?: null;
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

        $provider = $invoice->payment_gateway_provider ?: 'ipaymu';
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
        return $transaksi->gateway ?: ($transaksi->invoice?->payment_gateway_provider ?: 'ipaymu');
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
            ]);
        });
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
            $provider = (string) ($rawPayload['provider'] ?? 'ipaymu');
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

            // Guard clause idempotensi: Jika invoice sudah lunas sebelumnya
            if ($lockedInvoice->isLunas()) {
                if ($transaksi) {
                    $transaksi->update([
                        'status' => StatusTransaksiGateway::Paid,
                        'payload_response' => $rawPayload,
                    ]);
                }
                $lockedInvoice->update([
                    'payment_gateway_status' => 'PAID',
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
                    'payload_response' => $rawPayload,
                    // Channel yang benar-benar dipakai membayar (link dibuat sebagai `invoice` generik).
                    'channel' => GatewayChannel::tryFrom($channel) ?? $transaksi->channel,
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
