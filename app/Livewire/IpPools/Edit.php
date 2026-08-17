<?php

namespace App\Livewire\IpPools;

use App\Models\IpPool;
use App\Models\Router;
use App\Utils\IpNetworkHelper;
use Flux\Flux;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Edit IP Pool')]
class Edit extends Component
{
    #[Locked]
    public int $poolId;

    public string $name = '';

    public string $router_id = '';

    public string $ip_network = '';

    public string $cidr = '';

    public string $ip_range_start = '';

    public string $ip_range_end = '';

    public string $queue_tx_mbps = '';

    public string $queue_rx_mbps = '';

    public string $priority_tx = '';

    public string $priority_rx = '';

    public function mount(IpPool $pool): void
    {
        $this->poolId = $pool->id;
        $this->name = $pool->name;
        $this->router_id = (string) $pool->router_id;
        $this->ip_network = $pool->ip_network;
        $this->cidr = (string) $pool->cidr;
        $this->ip_range_start = $pool->ip_range_start ?? '';
        $this->ip_range_end = $pool->ip_range_end ?? '';
        $this->queue_tx_mbps = (string) (float) $pool->queue_tx_mbps;
        $this->queue_rx_mbps = (string) (float) $pool->queue_rx_mbps;
        $this->priority_tx = (string) $pool->priority_tx;
        $this->priority_rx = (string) $pool->priority_rx;
    }

    public function generateRange(): void
    {
        $this->validateOnly('ip_network', ['required', 'ipv4']);
        $this->validateOnly('cidr', ['required', 'integer', 'min:1', 'max:32']);

        $range = IpNetworkHelper::calculateSuggestedRange($this->ip_network, (int) $this->cidr);

        if ($range['start'] && $range['end']) {
            $this->ip_range_start = $range['start'];
            $this->ip_range_end = $range['end'];
            Flux::toast(variant: 'success', text: 'Rentang IP saran berhasil digunakan.');
        } else {
            Flux::toast(variant: 'danger', text: 'Kombinasi Network & CIDR tidak valid untuk rentang IP.');
        }
    }

    public function save(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:255', "unique:ip_pools,name,{$this->poolId}", 'regex:/^[A-Z0-9_]+$/'],
            'router_id' => ['required', 'exists:routers,id'],
            'ip_network' => ['required', 'string', 'ipv4'],
            'cidr' => ['required', 'integer', 'min:1', 'max:32'],
            'ip_range_start' => ['nullable', 'string', 'ipv4'],
            'ip_range_end' => ['nullable', 'string', 'ipv4'],
            'queue_tx_mbps' => ['required', 'numeric', 'min:0'],
            'queue_rx_mbps' => ['required', 'numeric', 'min:0'],
            'priority_tx' => ['required', 'integer', 'min:1', 'max:8'],
            'priority_rx' => ['required', 'integer', 'min:1', 'max:8'],
        ]);

        if ($this->ip_range_start || $this->ip_range_end) {
            if (! $this->ip_range_start || ! $this->ip_range_end) {
                $this->addError('ip_range_start', 'Kedua rentang IP (awal dan akhir) harus diisi atau dikosongkan bersamaan.');

                return;
            }
        }

        $pool = IpPool::findOrFail($this->poolId);
        $pool->update([
            'router_id' => $this->router_id,
            'name' => $this->name,
            'ip_network' => $this->ip_network,
            'cidr' => $this->cidr,
            'ip_range_start' => $this->ip_range_start ?: null,
            'ip_range_end' => $this->ip_range_end ?: null,
            'queue_tx_mbps' => $this->queue_tx_mbps,
            'queue_rx_mbps' => $this->queue_rx_mbps,
            'priority_tx' => $this->priority_tx,
            'priority_rx' => $this->priority_rx,
        ]);

        Flux::toast(variant: 'success', text: 'IP Pool berhasil diperbarui.');

        $this->redirectRoute('ip-pools.index', navigate: true);
    }

    public function render(): View
    {
        return view('livewire.ip-pools.edit', [
            'routers' => Router::orderBy('name')->get(),
        ]);
    }
}
