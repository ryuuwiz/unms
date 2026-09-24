<?php

namespace App\Livewire\IpPool;

use App\Livewire\Concerns\ValidatesIpPoolRange;
use App\Models\IpPool;
use App\Models\Router;
use App\Utils\IpNetworkHelper;
use Flux\Flux;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Edit IP Pool')]
class Edit extends Component
{
    use ValidatesIpPoolRange;

    #[Locked]
    public int $poolId;

    public string $nama_pool = '';

    public ?int $router_id = null;

    public string $ip_network = '';

    public ?int $cidr = 24;

    public string $rentang_ip_awal = '';

    public string $rentang_ip_akhir = '';

    public ?int $priority_tx = 8;

    public ?int $priority_rx = 8;

    public function mount(IpPool $pool): void
    {
        $this->authorize('update', $pool);

        $this->poolId = $pool->id;
        $this->nama_pool = $pool->nama_pool;
        $this->router_id = $pool->router_id;
        $this->ip_network = $pool->ip_network;
        $this->cidr = $pool->cidr;
        $this->rentang_ip_awal = $pool->rentang_ip_awal;
        $this->rentang_ip_akhir = $pool->rentang_ip_akhir;
        $this->priority_tx = $pool->priority_tx;
        $this->priority_rx = $pool->priority_rx;
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'nama_pool' => ['required', 'string', 'max:100', 'regex:/^[a-zA-Z0-9_-]+$/', Rule::unique('ip_pool', 'nama_pool')->where('router_id', $this->router_id)->ignore($this->poolId)],
            'router_id' => ['required', 'integer', 'exists:router,id'],
            'ip_network' => ['bail', 'required', 'string', 'ipv4', $this->ipNetworkRule()],
            'cidr' => ['required', 'integer', 'min:1', 'max:32'],
            'rentang_ip_awal' => ['required', 'string', 'ipv4'],
            'rentang_ip_akhir' => ['bail', 'required', 'string', 'ipv4', $this->rentangIpAkhirRule($this->poolId)],
            'priority_tx' => ['required', 'integer', 'min:1', 'max:8'],
            'priority_rx' => ['required', 'integer', 'min:1', 'max:8'],
        ];
    }

    public function generateRange(): void
    {
        $this->validateOnly('ip_network', ['ip_network' => ['required', 'ipv4']]);
        $this->validateOnly('cidr', ['cidr' => ['required', 'integer', 'min:1', 'max:32']]);

        $range = IpNetworkHelper::calculateSuggestedRange($this->ip_network, $this->cidr);

        if ($range['start'] && $range['end']) {
            $this->rentang_ip_awal = $range['start'];
            $this->rentang_ip_akhir = $range['end'];
            Flux::toast(variant: 'success', text: 'Rentang IP saran berhasil dihitung.');
        } else {
            Flux::toast(variant: 'danger', text: 'Kombinasi Network & CIDR tidak valid untuk rentang IP.');
        }
    }

    public function save(): void
    {
        $pool = IpPool::findOrFail($this->poolId);
        $this->authorize('update', $pool);
        $this->validate();

        if ($this->router_id !== $pool->router_id && ! $pool->canBeDeleted()) {
            $count = $pool->layanans()->withTrashed()->count();
            Flux::toast(variant: 'danger', text: "IP Pool {$pool->nama_pool} masih digunakan oleh {$count} layanan pelanggan dan tidak dapat dipindahkan ke router lain.");

            return;
        }

        // Nama dan network/CIDR menjadi nama Profile PPP per Pool, remote-address, dan gateway di router;
        // mengubahnya saat dipakai layanan meninggalkan pool/profile ganda dan risiko IP ganda.
        if (! $pool->canBeDeleted() && (
            $this->nama_pool !== $pool->nama_pool
            || $this->ip_network !== $pool->ip_network
            || (int) $this->cidr !== (int) $pool->cidr
        )) {
            Flux::toast(variant: 'danger', text: "IP Pool {$pool->nama_pool} sedang dipakai layanan pelanggan: nama dan network/CIDR tidak dapat diubah. Buat pool baru lalu pindahkan layanannya.");

            return;
        }

        $pool->update([
            'router_id' => $this->router_id,
            'nama_pool' => $this->nama_pool,
            'ip_network' => $this->ip_network,
            'cidr' => $this->cidr,
            'rentang_ip_awal' => $this->rentang_ip_awal,
            'rentang_ip_akhir' => $this->rentang_ip_akhir,
            'priority_tx' => $this->priority_tx,
            'priority_rx' => $this->priority_rx,
        ]);

        Flux::toast(variant: 'success', text: 'IP Pool berhasil diperbarui.');

        $this->redirectRoute('ip-pool.index', navigate: true);
    }

    public function render(): View
    {
        return view('livewire.ip-pool.edit', [
            'routers' => Router::orderBy('nama_router')->get(),
        ]);
    }
}
