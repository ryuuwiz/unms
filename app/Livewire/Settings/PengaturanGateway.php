<?php

namespace App\Livewire\Settings;

use App\DTO\PaymentGateway\PingConnectionResult;
use App\Models\PengaturanGateway as PengaturanGatewayModel;
use App\Services\PaymentGateway\PaymentGatewayManager;
use Flux\Flux;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Pengaturan Payment Gateway')]
class PengaturanGateway extends Component
{
    public string $search = '';

    // Modal Form State
    public bool $showModal = false;

    public ?int $editingId = null;

    public string $provider = 'ipaymu';

    public string $nama = '';

    // iPaymu Credentials
    public string $ipaymu_va = '';

    public string $ipaymu_api_key = '';

    public bool $bebankan_ke_pelanggan = true;

    public bool $is_default = false;

    public bool $is_active = true;

    public bool $sandbox_mode = true;

    public string $keterangan = '';

    // Ping / Test Koneksi State
    public bool $showPingModal = false;

    public ?int $pingGatewayId = null;

    public ?PengaturanGatewayModel $pingingGateway = null;

    public ?PingConnectionResult $pingResult = null;

    public bool $isPinging = false;

    public function openCreateModal(): void
    {
        $this->resetForm();
        $this->showModal = true;
    }

    public function openEditModal(int $id): void
    {
        $gateway = PengaturanGatewayModel::findOrFail($id);

        $this->editingId = $gateway->id;
        $this->provider = $gateway->provider ?: 'ipaymu';
        $this->nama = $gateway->nama ?: 'Gateway Connection';
        $this->bebankan_ke_pelanggan = (bool) $gateway->bebankan_ke_pelanggan;
        $this->is_default = (bool) $gateway->is_default;
        $this->is_active = (bool) $gateway->is_active;
        $this->sandbox_mode = (bool) $gateway->sandbox_mode;
        $this->keterangan = $gateway->keterangan ?? '';

        // Load credentials
        $creds = $gateway->credentials ?? [];
        $this->ipaymu_va = (string) ($creds['va'] ?? '');
        $this->ipaymu_api_key = (string) ($creds['api_key'] ?? '');

        $this->showModal = true;
    }

    public function simpan(PaymentGatewayManager $manager): void
    {
        $supportedProviders = array_keys($manager->getSupportedProviders());

        $this->validate([
            'provider' => ['required', Rule::in($supportedProviders)],
            'nama' => ['required', 'string', 'max:100'],
            'bebankan_ke_pelanggan' => ['required', 'boolean'],
            'is_default' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
            'sandbox_mode' => ['required', 'boolean'],
            'keterangan' => ['nullable', 'string', 'max:500'],
            'ipaymu_va' => ['nullable', 'string', 'max:50'],
            'ipaymu_api_key' => ['nullable', 'string', 'max:255'],
        ]);

        $credentials = ['va' => trim($this->ipaymu_va), 'api_key' => trim($this->ipaymu_api_key)];

        $data = [
            'provider' => $this->provider,
            'gateway' => $this->provider,
            'nama' => trim($this->nama),
            'credentials' => $credentials,
            'bebankan_ke_pelanggan' => $this->bebankan_ke_pelanggan,
            'is_active' => $this->is_active,
            'sandbox_mode' => $this->sandbox_mode,
            'keterangan' => $this->keterangan ? trim($this->keterangan) : null,
        ];

        if ($this->editingId) {
            $gateway = PengaturanGatewayModel::findOrFail($this->editingId);
            $gateway->update($data);

            if ($this->is_default) {
                $gateway->setAsDefault();
            }

            Flux::toast(variant: 'success', text: "Pengaturan gateway '{$gateway->nama}' berhasil diperbarui.");
        } else {
            $data['is_default'] = $this->is_default || PengaturanGatewayModel::count() === 0;
            $gateway = PengaturanGatewayModel::create($data);

            if ($data['is_default']) {
                $gateway->setAsDefault();
            }

            Flux::toast(variant: 'success', text: "Koneksi gateway '{$gateway->nama}' berhasil ditambahkan.");
        }

        $this->showModal = false;
        $this->resetForm();
    }

    public function setAsDefault(int $id): void
    {
        $gateway = PengaturanGatewayModel::findOrFail($id);
        $gateway->setAsDefault();

        Flux::toast(variant: 'success', text: "Gateway '{$gateway->nama}' disetel sebagai default gateway sistem.");
    }

    public function toggleStatus(int $id): void
    {
        $gateway = PengaturanGatewayModel::findOrFail($id);
        $gateway->update(['is_active' => ! $gateway->is_active]);

        $statusText = $gateway->is_active ? 'diaktifkan' : 'dinonaktifkan';
        Flux::toast(variant: 'success', text: "Gateway '{$gateway->nama}' berhasil {$statusText}.");
    }

    public function hapus(int $id): void
    {
        $gateway = PengaturanGatewayModel::findOrFail($id);

        if ($gateway->is_default && PengaturanGatewayModel::count() > 1) {
            Flux::toast(variant: 'danger', text: 'Gateway default tidak dapat dihapus. Silahkan tentukan gateway default lain terlebih dahulu.');

            return;
        }

        $nama = $gateway->nama;
        $gateway->delete();

        Flux::toast(variant: 'success', text: "Koneksi gateway '{$nama}' berhasil dihapus.");
    }

    public function openPingModal(int $id): void
    {
        $this->pingGatewayId = $id;
        $this->pingingGateway = PengaturanGatewayModel::findOrFail($id);
        $this->pingResult = null;
        $this->showPingModal = true;

        $this->eksekusiPing();
    }

    public function eksekusiPing(): void
    {
        if (! $this->pingingGateway) {
            return;
        }

        $this->isPinging = true;
        try {
            /** @var PaymentGatewayManager $manager */
            $manager = app(PaymentGatewayManager::class);
            $this->pingResult = $manager->pingConnection($this->pingingGateway);
        } catch (\Throwable $e) {
            Log::error('Pengujian koneksi payment gateway gagal.', [
                'gateway_id' => $this->pingingGateway->id,
                'provider' => $this->pingingGateway->provider,
                'exception' => $e::class,
            ]);

            $this->pingResult = new PingConnectionResult(
                success: false,
                message: 'Pengujian koneksi gagal. Periksa log aplikasi untuk detail teknis.'
            );
        } finally {
            $this->isPinging = false;
        }
    }

    protected function resetForm(): void
    {
        $this->editingId = null;
        $this->provider = 'ipaymu';
        $this->nama = '';
        $this->ipaymu_va = '';
        $this->ipaymu_api_key = '';
        $this->bebankan_ke_pelanggan = true;
        $this->is_default = false;
        $this->is_active = true;
        $this->sandbox_mode = true;
        $this->keterangan = '';
    }

    public function render(): View
    {
        $query = PengaturanGatewayModel::query();

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('nama', 'like', "%{$this->search}%")
                    ->orWhere('provider', 'like', "%{$this->search}%")
                    ->orWhere('keterangan', 'like', "%{$this->search}%");
            });
        }

        $gateways = $query->orderByDesc('is_default')->orderBy('nama')->get();

        return view('livewire.settings.pengaturan-gateway', [
            'gateways' => $gateways,
            'supportedProviders' => app(PaymentGatewayManager::class)->getSupportedProviders(),
            'totalGateways' => PengaturanGatewayModel::count(),
            'totalActive' => PengaturanGatewayModel::where('is_active', true)->count(),
        ]);
    }
}
