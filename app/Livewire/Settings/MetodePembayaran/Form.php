<?php

namespace App\Livewire\Settings\MetodePembayaran;

use App\Enums\GatewayChannel;
use App\Models\ChannelPembayaran;
use App\Models\PengaturanGateway;
use App\Services\PaymentGateway\PaymentGatewayManager;
use Closure;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Throwable;

/**
 * Halaman Tambah/Edit Channel Pembayaran (label UI: Metode Pembayaran), ADR-0073.
 */
#[Layout('layouts.app')]
#[Title('Metode Pembayaran')]
class Form extends Component
{
    #[Locked]
    public ?int $channelId = null;

    public ?int $pengaturan_gateway_id = null;

    public string $tipe = '';

    public string $kode = '';

    /** Angka = flat rupiah, diakhiri % = persen dari nominal tagihan. */
    public string $fee_admin = '0';

    public string $icon_url = '';

    public string $status = 'on';

    public string $keterangan = '';

    /**
     * Channel aktif di akun gateway (hasil "Ambil dari iPaymu").
     *
     * @var list<array{tipe: string, kode: string, nama: string, logo: string|null, fee: float, fee_persen: bool}>
     */
    #[Locked]
    public array $saranGateway = [];

    public function mount(?ChannelPembayaran $channel = null): void
    {
        if (! $channel?->exists) {
            $this->authorize('payment_gateway.buat');
            $this->pengaturan_gateway_id = $this->gateways()->first()?->id;

            return;
        }

        $this->authorize('payment_gateway.ubah');

        $this->channelId = $channel->id;
        $this->pengaturan_gateway_id = $channel->pengaturan_gateway_id;
        $this->tipe = $channel->tipe->value;
        $this->kode = $channel->kode;
        $this->fee_admin = self::teksFee((float) $channel->fee_admin, $channel->fee_persen);
        $this->icon_url = $channel->icon_url ?? '';
        $this->status = $channel->is_active ? 'on' : 'off';
        $this->keterangan = $channel->keterangan ?? '';
    }

    public function updatedPengaturanGatewayId(): void
    {
        $this->tipe = '';
        $this->saranGateway = [];
    }

    public function ambilDariGateway(PaymentGatewayManager $manager): void
    {
        $gateway = $this->gateways()->firstWhere('id', $this->pengaturan_gateway_id);
        if (! $gateway) {
            $this->addError('pengaturan_gateway_id', 'Pilih Payment Gateway terlebih dahulu.');

            return;
        }

        try {
            $this->saranGateway = $manager->driver($gateway->provider)->daftarChannelGateway($gateway);
        } catch (ConnectionException $e) {
            // Server gateway tidak menjawab (mis. sandbox iPaymu sedang down): bukan salah kredensial,
            // dan form tetap bisa diisi manual.
            report($e);
            Flux::toast(variant: 'warning', text: "Server {$gateway->provider} tidak merespons. Isi form secara manual atau coba lagi nanti.");
        } catch (Throwable $e) {
            report($e);
            Flux::toast(variant: 'danger', text: 'Gagal mengambil daftar channel dari gateway. Periksa kredensial koneksi.');
        }
    }

    public function pakaiSaran(int $index): void
    {
        $saran = $this->saranGateway[$index] ?? null;
        if ($saran === null) {
            return;
        }

        $this->tipe = $saran['tipe'];
        $this->kode = $saran['kode'];
        $this->fee_admin = self::teksFee($saran['fee'], $saran['fee_persen']);
        $this->icon_url = (string) $saran['logo'];
        $this->keterangan = $this->keterangan ?: $saran['nama'];
        $this->saranGateway = [];
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'pengaturan_gateway_id' => ['required', Rule::in($this->gateways()->modelKeys())],
            'tipe' => ['required', Rule::in(array_keys($this->kodeChannel()))],
            'kode' => [
                'required', 'string', 'max:50',
                Rule::in($this->kodeChannel()[$this->tipe] ?? []),
                Rule::unique('channel_pembayaran', 'kode')
                    ->where('pengaturan_gateway_id', $this->pengaturan_gateway_id)
                    ->ignore($this->channelId),
            ],
            'fee_admin' => [
                'required', 'regex:/^\d+(\.\d{1,2})?%?$/',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (str_ends_with((string) $value, '%') && (float) $value > 100) {
                        $fail('Fee Admin persen maksimal 100%.');
                    }
                },
            ],
            'icon_url' => ['nullable', 'url:http,https', 'max:500'],
            'status' => ['required', Rule::in(['on', 'off'])],
            'keterangan' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'kode.in' => 'Nama metode tidak dikenal gateway untuk tipe ini. Pilih salah satu: :values.',
            'kode.unique' => 'Metode ini sudah terdaftar untuk gateway yang sama.',
            'fee_admin.regex' => 'Isi angka rupiah (mis. 4100) atau persen (mis. 1%).',
        ];
    }

    public function save(): void
    {
        $this->authorize($this->channelId ? 'payment_gateway.ubah' : 'payment_gateway.buat');

        $this->kode = strtolower(trim($this->kode));
        $this->fee_admin = str_replace(' ', '', $this->fee_admin);
        $this->validate();

        ChannelPembayaran::updateOrCreate(['id' => $this->channelId], [
            'pengaturan_gateway_id' => $this->pengaturan_gateway_id,
            'tipe' => $this->tipe,
            'kode' => $this->kode,
            'fee_admin' => (float) rtrim($this->fee_admin, '%'),
            'fee_persen' => str_ends_with($this->fee_admin, '%'),
            'icon_url' => trim($this->icon_url) ?: null,
            'is_active' => $this->status === 'on',
            'keterangan' => trim($this->keterangan) ?: null,
        ]);

        Flux::toast(variant: 'success', text: "Metode pembayaran '{$this->kode}' berhasil disimpan.");

        $this->redirectRoute('settings.metode-pembayaran.index', navigate: true);
    }

    private static function teksFee(float $nilai, bool $persen): string
    {
        return rtrim(rtrim(number_format($nilai, 2, '.', ''), '0'), '.').($persen ? '%' : '');
    }

    /**
     * Koneksi Gateway yang driver-nya mendukung pembayaran per channel (saat ini iPaymu).
     *
     * @return Collection<int, PengaturanGateway>
     */
    private function gateways(): Collection
    {
        $manager = app(PaymentGatewayManager::class);
        $provider = array_filter(
            array_keys($manager->getSupportedProviders()),
            fn (string $provider) => $manager->driver($provider)->kodeChannel() !== [],
        );

        return PengaturanGateway::whereIn('provider', $provider)->orderBy('nama')->get();
    }

    /**
     * @return array<string, list<string>>
     */
    private function kodeChannel(): array
    {
        $gateway = $this->gateways()->firstWhere('id', $this->pengaturan_gateway_id);

        return $gateway ? app(PaymentGatewayManager::class)->driver($gateway->provider)->kodeChannel() : [];
    }

    public function render(): View
    {
        $kodeChannel = $this->kodeChannel();

        return view('livewire.settings.metode-pembayaran.form', [
            'gateways' => $this->gateways(),
            'tipeOptions' => array_map(fn (string $tipe) => GatewayChannel::from($tipe), array_keys($kodeChannel)),
            'kodeSaran' => $kodeChannel[$this->tipe] ?? [],
        ]);
    }
}
