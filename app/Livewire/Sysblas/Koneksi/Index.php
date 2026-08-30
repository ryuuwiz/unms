<?php

namespace App\Livewire\Sysblas\Koneksi;

use App\Enums\Sysblas\SysblasProvider;
use App\Models\Sysblas;
use App\Models\User;
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

    public string $provider = 'wablas';

    public string $nomor = '';

    public string $url_api = 'https://tegal.wablas.com';

    public string $api_token = '';

    public string $api_secret = '';

    public ?int $limit_per_menit = 25;

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

    // State Modal Test Kirim Pesan
    public bool $showTestSendModal = false;

    public ?int $testSysblasId = null;

    public ?Sysblas $testingSysblas = null;

    public string $testPhone = '';

    public string $testMessage = 'Halo! Ini adalah pesan uji coba integrasi WhatsApp SysBlast.';

    public bool $isSendingTest = false;

    public function mount(): void
    {
        $this->authorize('viewAny', User::class);
    }

    public function openCreateModal(): void
    {
        $this->resetForm();
        $this->showModal = true;
    }

    public function openEditModal(int $id): void
    {
        $sysblas = Sysblas::findOrFail($id);

        $this->editingId = $sysblas->id;
        $this->nama = $sysblas->nama;
        $this->provider = $sysblas->provider->value;
        $this->nomor = $sysblas->nomor ?? '';
        $this->url_api = $sysblas->url_api;
        $this->api_token = $sysblas->api_token;
        $this->api_secret = $sysblas->api_secret ?? '';
        $this->limit_per_menit = $sysblas->limit_per_menit;
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
            'nomor' => ['nullable', 'string', 'max:50'],
            'url_api' => ['required', 'url', 'max:255'],
            'api_token' => ['required', 'string', 'max:255'],
            'api_secret' => ['nullable', 'string', 'max:255'],
            'limit_per_menit' => ['required', 'integer', 'min:1', 'max:300'],
            'is_default' => ['required', 'boolean'],
            'is_aktif' => ['required', 'boolean'],
            'keterangan' => ['nullable', 'string', 'max:500'],
        ], [
            'nama.required' => 'Nama / Label koneksi wajib diisi.',
            'url_api.required' => 'URL Base API wajib diisi.',
            'url_api.url' => 'Format URL API tidak valid.',
            'api_token.required' => 'Token / API Key wajib diisi.',
            'limit_per_menit.min' => 'Limit minimal 1 pesan per menit.',
        ]);

        $token = trim($this->api_token);
        $secret = $this->api_secret ? trim($this->api_secret) : null;
        if ($secret === $token) {
            $secret = null;
        }

        $data = [
            'nama' => trim($this->nama),
            'provider' => $this->provider,
            'nomor' => $this->nomor ? trim($this->nomor) : null,
            'url_api' => rtrim(trim($this->url_api), '/'),
            'api_token' => $token,
            'api_secret' => $secret,
            'limit_per_menit' => $this->limit_per_menit,
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

        $this->showModal = false;
        $this->resetForm();
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
        $this->provider = 'wablas';
        $this->nomor = '';
        $this->url_api = 'https://tegal.wablas.com';
        $this->api_token = '';
        $this->api_secret = '';
        $this->limit_per_menit = 25;
        $this->is_default = false;
        $this->is_aktif = true;
        $this->keterangan = '';
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
