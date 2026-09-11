<?php

namespace App\Livewire\Sysblas\Koneksi;

use App\Enums\Sysblas\SysblasProvider;
use App\Models\Sysblas;
use App\Models\User;
use App\Services\Whatsapp\Drivers\GowaDriver;
use App\Services\Whatsapp\WhatsappClient;
use Flux\Flux;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('SysBlast - Koneksi API Gateway')]
class Index extends Component
{
    public string $search = '';

    // State Modal Form Create/Edit
    public bool $showModal = false;

    public ?int $editingId = null;

    public string $nama = '';

    public string $provider = 'gowa';

    public string $nomor = '';

    public string $url_api = 'http://localhost:3000';

    public string $username = '';

    public string $password = '';

    public string $session_name = 'default';

    public ?string $api_token = '';

    public ?string $api_secret = '';

    public ?int $limit_per_menit = 4;

    public ?int $delay_detik = 15;

    public ?int $jitter_detik = 2;

    public bool $is_typing_simulation = true;

    public bool $is_default = false;

    public bool $is_aktif = true;

    public string $keterangan = '';

    // State Modal Ping / Test Koneksi
    public bool $showPingModal = false;

    public ?int $pingSysblasId = null;

    public ?Sysblas $pingingSysblas = null;

    /** @var array<string, mixed>|null */
    public ?array $pingResult = null;

    public bool $isPinging = false;

    // State Modal QR Code Pairing
    public bool $showQrModal = false;

    public ?int $qrSysblasId = null;

    public ?Sysblas $qrSysblas = null;

    public ?string $qrCodeImage = null;

    public string $qrSessionStatus = 'UNKNOWN';

    public string $qrMessage = '';

    public bool $isLoadingQr = false;

    // State Modal Test Kirim Pesan
    public bool $showTestSendModal = false;

    public ?int $testSysblasId = null;

    public ?Sysblas $testingSysblas = null;

    public string $testPhone = '';

    public string $testMessage = 'Halo! Ini adalah pesan uji coba integrasi WhatsApp SysBlast.';

    public bool $isSendingTest = false;

    /** @var array<int, array<string, mixed>> */
    public array $wahaAvailableSessions = [];

    /** @var array<int, array<string, mixed>> */
    public array $gowaAvailableDevices = [];

    public bool $isLoadingSessions = false;

    public function mount(): void
    {
        $this->authorize('viewAny', User::class);
    }

    public function openCreateModal(): void
    {
        $this->resetForm();
        $this->showModal = true;
    }

    public function updatedProvider(string $value): void
    {
        if (! $this->editingId) {
            if ($value === SysblasProvider::Waha->value) {
                $this->url_api = 'https://waha.gobilling.id';
            } elseif ($value === SysblasProvider::Gowa->value) {
                $this->url_api = 'http://localhost:3000';
            }
        }
    }

    public function openEditModal(int $id): void
    {
        $sysblas = Sysblas::findOrFail($id);

        $this->editingId = $sysblas->id;
        $this->nama = $sysblas->nama;
        $this->provider = $sysblas->provider->value;
        $this->session_name = $sysblas->session_name ?: 'default';
        $this->nomor = $sysblas->nomor ?? '';
        $this->url_api = $sysblas->url_api ?? '';
        $this->username = $sysblas->username ?? '';
        $this->password = $sysblas->password ?? '';
        $this->api_token = $sysblas->api_token ?? '';
        $this->api_secret = $sysblas->api_secret ?? '';
        $this->limit_per_menit = $sysblas->limit_per_menit;
        $this->delay_detik = $sysblas->delay_detik ?? 15;
        $this->jitter_detik = $sysblas->jitter_detik ?? 2;
        $this->is_typing_simulation = (bool) ($sysblas->is_typing_simulation ?? true);
        $this->is_default = (bool) $sysblas->is_default;
        $this->is_aktif = (bool) $sysblas->is_aktif;
        $this->keterangan = $sysblas->keterangan ?? '';

        $this->showModal = true;
    }

    public function simpan(): void
    {
        $this->validate([
            'nama' => ['required', 'string', 'max:255'],
            'provider' => ['required', Rule::enum(SysblasProvider::class)],
            'session_name' => ['nullable', 'string', 'max:100'],
            'nomor' => ['nullable', 'string', 'max:50'],
            'url_api' => ['required', 'url', 'max:255'],
            'username' => [
                Rule::requiredIf(fn () => $this->provider === SysblasProvider::Gowa->value),
                'nullable',
                'string',
                'max:255',
            ],
            'password' => [
                Rule::requiredIf(fn () => $this->provider === SysblasProvider::Gowa->value),
                'nullable',
                'string',
                'max:255',
            ],
            'api_token' => [
                Rule::requiredIf(fn () => ! in_array($this->provider, [SysblasProvider::Waha->value, SysblasProvider::Gowa->value], true)),
                'nullable',
                'string',
                'max:255',
            ],
            'api_secret' => ['nullable', 'string', 'max:255'],
            'limit_per_menit' => ['required', 'integer', 'min:1', 'max:20'],
            'delay_detik' => ['required', 'integer', 'min:0', 'max:3600'],
            'jitter_detik' => ['required', 'integer', 'min:0', 'max:30'],
            'is_typing_simulation' => ['required', 'boolean'],
            'is_default' => ['required', 'boolean'],
            'is_aktif' => ['required', 'boolean'],
            'keterangan' => ['nullable', 'string', 'max:500'],
        ], [
            'nama.required' => 'Nama / Label koneksi wajib diisi.',
            'url_api.required' => 'URL Base API wajib diisi.',
            'url_api.url' => 'Format URL API tidak valid.',
            'api_token.required' => 'Token / API Key wajib diisi.',
            'username.required' => 'Username Basic Auth GOWA wajib diisi.',
            'password.required' => 'Password Basic Auth GOWA wajib diisi.',
            'limit_per_menit.min' => 'Limit minimal 1 pesan per menit.',
            'limit_per_menit.max' => 'Limit maksimal 20 pesan per menit untuk mencegah nomor WhatsApp diblokir (anti-ban).',
            'delay_detik.min' => 'Jeda minimal tidak boleh negatif.',
            'jitter_detik.min' => 'Jeda acak tidak boleh negatif.',
        ]);

        $token = $this->api_token ? trim($this->api_token) : null;
        $secret = $this->api_secret ? trim($this->api_secret) : null;
        if ($secret && $secret === $token) {
            $secret = null;
        }

        $data = [
            'nama' => trim($this->nama),
            'provider' => $this->provider,
            'session_name' => $this->session_name ? trim($this->session_name) : 'default',
            'username' => $this->username ? trim($this->username) : null,
            'password' => $this->password ? trim($this->password) : null,
            'nomor' => $this->nomor ? trim($this->nomor) : null,
            'url_api' => rtrim(trim($this->url_api), '/'),
            'api_token' => $token,
            'api_secret' => $secret,
            'limit_per_menit' => $this->limit_per_menit,
            'delay_detik' => $this->delay_detik ?? 15,
            'jitter_detik' => $this->jitter_detik ?? 2,
            'is_typing_simulation' => $this->is_typing_simulation,
            'is_aktif' => $this->is_aktif,
            'keterangan' => $this->keterangan ? trim($this->keterangan) : null,
        ];

        if ($this->editingId) {
            $sysblas = Sysblas::findOrFail($this->editingId);
            $sysblas->update($data);

            if ($this->is_default) {
                $sysblas->setAsDefault();
            }

            Flux::toast(variant: 'success', text: "Koneksi '{$sysblas->nama}' berhasil diperbarui.");
        } else {
            $data['is_default'] = $this->is_default || Sysblas::count() === 0;
            $sysblas = Sysblas::create($data);

            if ($data['is_default']) {
                $sysblas->setAsDefault();
            }

            Flux::toast(variant: 'success', text: "Koneksi '{$sysblas->nama}' berhasil ditambahkan.");
        }

        if ($sysblas->provider === SysblasProvider::Gowa) {
            $this->daftarkanWebhookGowa($sysblas);
        }

        $this->showModal = false;
        $this->resetForm();
    }

    /**
     * Daftarkan URL webhook terpadu aplikasi ini ke device GOWA milik koneksi. Best-effort:
     * kegagalan hanya ditampilkan sebagai peringatan, tidak membatalkan penyimpanan koneksi.
     */
    protected function daftarkanWebhookGowa(Sysblas $sysblas): void
    {
        try {
            /** @var GowaDriver $driver */
            $driver = $sysblas->makeClient()->getDriver();
            $result = $driver->registerWebhook(route('webhook.whatsapp'), $sysblas->api_secret);

            if (! $result['success']) {
                Flux::toast(variant: 'warning', text: "Koneksi tersimpan, namun registrasi webhook ke GOWA gagal: {$result['message']}");
            }
        } catch (\Throwable $e) {
            Flux::toast(variant: 'warning', text: 'Koneksi tersimpan, namun registrasi webhook ke GOWA gagal: '.$e->getMessage());
        }
    }

    public function setAsDefault(int $id): void
    {
        $sysblas = Sysblas::findOrFail($id);
        $sysblas->setAsDefault();

        Flux::toast(variant: 'success', text: "Koneksi '{$sysblas->nama}' disetel sebagai default gateway.");
    }

    public function toggleStatus(int $id): void
    {
        $sysblas = Sysblas::findOrFail($id);
        $sysblas->update(['is_aktif' => ! $sysblas->is_aktif]);

        $statusText = $sysblas->is_aktif ? 'diaktifkan' : 'dinonaktifkan';
        Flux::toast(variant: 'success', text: "Koneksi '{$sysblas->nama}' berhasil {$statusText}.");
    }

    public function hapus(int $id): void
    {
        $sysblas = Sysblas::findOrFail($id);

        if ($sysblas->is_default && Sysblas::count() > 1) {
            Flux::toast(variant: 'danger', text: 'Koneksi default tidak dapat dihapus. Silahkan tentukan koneksi default lain terlebih dahulu.');

            return;
        }

        $nama = $sysblas->nama;
        $sysblas->delete();

        Flux::toast(variant: 'success', text: "Koneksi '{$nama}' berhasil dihapus.");
    }

    public function openPingModal(int $id): void
    {
        $this->pingSysblasId = $id;
        $this->pingingSysblas = Sysblas::findOrFail($id);
        $this->pingResult = null;
        $this->showPingModal = true;

        $this->eksekusiPing();
    }

    public function eksekusiPing(): void
    {
        if (! $this->pingingSysblas) {
            return;
        }

        $this->isPinging = true;
        try {
            $client = $this->pingingSysblas->makeClient();
            $this->pingResult = $client->pingConnection();
        } catch (\Throwable $e) {
            $this->pingResult = [
                'connected' => false,
                'phone' => $this->pingingSysblas->nomor ?? '-',
                'quota' => '-',
                'expired_at' => null,
                'message' => 'Error: '.$e->getMessage(),
                'raw' => [],
            ];
        } finally {
            $this->isPinging = false;
        }
    }

    public function restartSessionAndPing(int $id): void
    {
        $sysblas = Sysblas::findOrFail($id);
        $client = $sysblas->makeClient();
        $result = $client->restartSession();

        if ($result['success']) {
            Flux::toast(variant: 'success', text: "Session '{$sysblas->session_name}' berhasil direstart. Memeriksa status...");
        } else {
            Flux::toast(variant: 'danger', text: "Gagal restart session: {$result['message']}");
        }

        $this->eksekusiPing();
    }

    public function openQrModal(int $id): void
    {
        $this->qrSysblasId = $id;
        $this->qrSysblas = Sysblas::findOrFail($id);
        $this->qrCodeImage = null;
        $this->qrSessionStatus = 'UNKNOWN';
        $this->qrMessage = 'Memeriksa status session...';
        $this->showQrModal = true;

        $this->refreshQrStatus();
    }

    public function refreshQrStatus(): void
    {
        if (! $this->qrSysblasId) {
            return;
        }

        $this->qrSysblas = Sysblas::find($this->qrSysblasId);
        if (! $this->qrSysblas) {
            return;
        }

        $this->isLoadingQr = true;
        try {
            $client = $this->qrSysblas->makeClient();
            $info = $client->getDeviceInfo();
            $status = $info['session_status'] ?? ($info['connected'] ? 'WORKING' : 'UNKNOWN');
            $this->qrSessionStatus = $status;
            $this->qrMessage = $info['message'] ?? '';

            if ($status === 'WORKING') {
                $this->qrCodeImage = null;
                $this->qrSysblas->update(['is_aktif' => true]);
            } else {
                $qrResult = $client->getQrCode();
                if ($qrResult['success'] && ! empty($qrResult['qr'])) {
                    $this->qrCodeImage = $qrResult['qr'];
                    $this->qrMessage = 'Silahkan scan QR Code berikut dengan aplikasi WhatsApp Anda.';
                } else {
                    $this->qrMessage = $qrResult['message'];
                }
            }
        } catch (\Throwable $e) {
            $this->qrMessage = 'Error: '.$e->getMessage();
            $this->qrSessionStatus = 'ERROR';
        } finally {
            $this->isLoadingQr = false;
        }
    }

    public function startSession(int $id): void
    {
        $sysblas = Sysblas::findOrFail($id);
        $client = $sysblas->makeClient();
        $result = $client->startSession();

        if ($result['success']) {
            Flux::toast(variant: 'success', text: "Session '{$sysblas->session_name}' berhasil dijalankan.");
        } else {
            Flux::toast(variant: 'danger', text: "Gagal start session: {$result['message']}");
        }

        if ($this->showQrModal && $this->qrSysblasId === $id) {
            $this->refreshQrStatus();
        }
    }

    public function stopSession(int $id): void
    {
        $sysblas = Sysblas::findOrFail($id);
        $client = $sysblas->makeClient();
        $result = $client->stopSession();

        if ($result['success']) {
            Flux::toast(variant: 'success', text: "Session '{$sysblas->session_name}' berhasil dihentikan.");
        } else {
            Flux::toast(variant: 'danger', text: "Gagal stop session: {$result['message']}");
        }

        if ($this->showQrModal && $this->qrSysblasId === $id) {
            $this->refreshQrStatus();
        }
    }

    public function restartSession(int $id): void
    {
        $sysblas = Sysblas::findOrFail($id);
        $client = $sysblas->makeClient();
        $result = $client->restartSession();

        if ($result['success']) {
            Flux::toast(variant: 'success', text: "Session '{$sysblas->session_name}' berhasil direstart.");
        } else {
            Flux::toast(variant: 'danger', text: "Gagal restart session: {$result['message']}");
        }

        if ($this->showQrModal && $this->qrSysblasId === $id) {
            $this->refreshQrStatus();
        }
    }

    public function logoutSession(int $id): void
    {
        $sysblas = Sysblas::findOrFail($id);
        $client = $sysblas->makeClient();
        $result = $client->logoutSession();

        if ($result['success']) {
            Flux::toast(variant: 'success', text: "Session '{$sysblas->session_name}' berhasil logout.");
        } else {
            Flux::toast(variant: 'danger', text: "Gagal logout session: {$result['message']}");
        }

        if ($this->showQrModal && $this->qrSysblasId === $id) {
            $this->refreshQrStatus();
        }
    }

    public function tarikSesiWaha(): void
    {
        if ($this->provider !== SysblasProvider::Waha->value) {
            return;
        }

        $this->isLoadingSessions = true;
        try {
            $client = new WhatsappClient(
                host: $this->url_api ?: 'https://waha.gobilling.id',
                number: $this->nomor ?: '',
                username: $this->username ?: '',
                password: $this->password ?: '',
                apiKey: $this->api_token ?: null,
                sessionName: $this->session_name ?: 'default',
                provider: 'waha'
            );

            $sessions = $client->listSessions();
            $this->wahaAvailableSessions = $sessions;

            if (! empty($sessions)) {
                Flux::toast(variant: 'success', text: 'Ditemukan '.count($sessions).' session pada server WAHA.');

                // Auto-select session pertama yang WORKING
                $workingSession = collect($sessions)->firstWhere('connected', true) ?? $sessions[0];
                if (empty($this->session_name) || $this->session_name === 'default') {
                    $this->session_name = $workingSession['name'];
                }
                if (! empty($workingSession['phone']) && empty($this->nomor)) {
                    $this->nomor = $workingSession['phone'];
                }
            } else {
                Flux::toast(variant: 'warning', text: 'Tidak ada session ditemukan di server WAHA.');
            }
        } catch (\Throwable $e) {
            Flux::toast(variant: 'danger', text: 'Gagal mengambil session dari WAHA: '.$e->getMessage());
        } finally {
            $this->isLoadingSessions = false;
        }
    }

    public function pilihSesiWaha(string $sessionName, ?string $phone = null): void
    {
        $this->session_name = $sessionName;
        if ($phone && (empty($this->nomor) || $this->nomor === '08970919525')) {
            $this->nomor = $phone;
        }
        Flux::toast(variant: 'success', text: "Session '{$sessionName}' dipilih.");
    }

    /**
     * Ambil daftar device yang sudah terpasang (paired) di server GOWA. Pairing device baru
     * dilakukan langsung lewat dashboard GOWA (gowa-ui), bukan dari aplikasi ini — di sini
     * admin hanya memilih device yang sudah ada untuk dihubungkan ke koneksi ini.
     */
    public function tarikDeviceGowa(): void
    {
        if ($this->provider !== SysblasProvider::Gowa->value) {
            return;
        }

        $this->isLoadingSessions = true;
        try {
            $client = new WhatsappClient(
                host: $this->url_api ?: 'http://localhost:3000',
                username: $this->username ?: '',
                password: $this->password ?: '',
                sessionName: $this->session_name ?: 'default',
                provider: 'gowa'
            );

            $devices = $client->listSessions();
            $this->gowaAvailableDevices = $devices;

            if (! empty($devices)) {
                Flux::toast(variant: 'success', text: 'Ditemukan '.count($devices).' device pada server GOWA.');

                $workingDevice = collect($devices)->firstWhere('connected', true) ?? $devices[0];
                if (empty($this->session_name) || $this->session_name === 'default') {
                    $this->session_name = $workingDevice['name'];
                }
                if (! empty($workingDevice['phone']) && empty($this->nomor)) {
                    $this->nomor = $workingDevice['phone'];
                }
            } else {
                Flux::toast(variant: 'warning', text: 'Tidak ada device ditemukan di server GOWA. Pastikan device sudah dipasangkan (paired) lewat dashboard GOWA.');
            }
        } catch (\Throwable $e) {
            Flux::toast(variant: 'danger', text: 'Gagal mengambil daftar device dari GOWA: '.$e->getMessage());
        } finally {
            $this->isLoadingSessions = false;
        }
    }

    public function pilihDeviceGowa(string $deviceId, ?string $phone = null): void
    {
        $this->session_name = $deviceId;
        if ($phone && empty($this->nomor)) {
            $this->nomor = $phone;
        }
        Flux::toast(variant: 'success', text: "Device '{$deviceId}' dipilih.");
    }

    public function openTestSendModal(int $id): void
    {
        $this->testSysblasId = $id;
        $this->testingSysblas = Sysblas::findOrFail($id);
        $this->testPhone = '';
        $this->showTestSendModal = true;
    }

    public function kirimPesanTest(): void
    {
        $this->validate([
            'testPhone' => ['required', 'string', 'min:9', 'max:20'],
            'testMessage' => ['required', 'string', 'min:3', 'max:1000'],
        ], [
            'testPhone.required' => 'Nomor HP pengujian wajib diisi.',
            'testMessage.required' => 'Pesan uji coba wajib diisi.',
        ]);

        if (! $this->testingSysblas) {
            return;
        }

        $this->isSendingTest = true;
        try {
            $client = $this->testingSysblas->makeClient();
            $result = $client->sendMessage($this->testPhone, $this->testMessage);

            if ($result['success']) {
                Flux::toast(variant: 'success', text: "Pesan uji coba berhasil dikirim via {$this->testingSysblas->nama} ke {$this->testPhone}!");
                $this->showTestSendModal = false;
            } else {
                Flux::toast(variant: 'danger', text: "Gagal kirim: {$result['message']}");
            }
        } catch (\Throwable $e) {
            Flux::toast(variant: 'danger', text: 'Terjadi kesalahan: '.$e->getMessage());
        } finally {
            $this->isSendingTest = false;
        }
    }

    protected function resetForm(): void
    {
        $this->editingId = null;
        $this->nama = '';
        $this->provider = 'gowa';
        $this->session_name = 'default';
        $this->nomor = '';
        $this->url_api = 'http://localhost:3000';
        $this->username = '';
        $this->password = '';
        $this->api_token = '';
        $this->api_secret = '';
        $this->limit_per_menit = 4;
        $this->delay_detik = 15;
        $this->jitter_detik = 2;
        $this->is_typing_simulation = true;
        $this->is_default = false;
        $this->is_aktif = true;
        $this->keterangan = '';
        $this->wahaAvailableSessions = [];
        $this->gowaAvailableDevices = [];
    }

    public function render(): View
    {
        $query = Sysblas::query();

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('nama', 'like', "%{$this->search}%")
                    ->orWhere('nomor', 'like', "%{$this->search}%")
                    ->orWhere('url_api', 'like', "%{$this->search}%")
                    ->orWhere('keterangan', 'like', "%{$this->search}%");
            });
        }

        $koneksis = $query->orderByDesc('is_default')->orderBy('nama')->get();

        return view('livewire.sysblas.koneksi.index', [
            'koneksis' => $koneksis,
            'providers' => SysblasProvider::cases(),
            'totalKoneksi' => Sysblas::count(),
            'totalAktif' => Sysblas::where('is_aktif', true)->count(),
        ]);
    }
}
