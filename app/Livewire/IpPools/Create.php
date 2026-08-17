<?php

namespace App\Livewire\IpPools;

use App\Models\IpPool;
use App\Models\Router;
use App\Utils\IpNetworkHelper;
use Flux\Flux;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Create IP Pool')]
class Create extends Component
{
    #[Validate(['required', 'string', 'max:255', 'unique:ip_pools,name', 'regex:/^[A-Z0-9_]+$/'])]
    public string $name = '';

    #[Validate(['required', 'exists:routers,id'])]
    public string $router_id = '';

    #[Validate(['required', 'string', 'ipv4'])]
    public string $ip_network = '';

    #[Validate(['required', 'integer', 'min:1', 'max:32'])]
    public string $cidr = '';

    #[Validate(['nullable', 'string', 'ipv4'])]
    public string $ip_range_start = '';

    #[Validate(['nullable', 'string', 'ipv4'])]
    public string $ip_range_end = '';

    #[Validate(['required', 'numeric', 'min:0'])]
    public string $queue_tx_mbps = '';

    #[Validate(['required', 'numeric', 'min:0'])]
    public string $queue_rx_mbps = '';

    #[Validate(['required', 'integer', 'min:1', 'max:8'])]
    public string $priority_tx = '8';

    #[Validate(['required', 'integer', 'min:1', 'max:8'])]
    public string $priority_rx = '8';

    public function generateRange(): void
    {
        $this->validateOnly('ip_network');
        $this->validateOnly('cidr');

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
        $this->validate();

        // Custom validation for range matching the network logic (simplified for now, ideally checked properly)
        if ($this->ip_range_start || $this->ip_range_end) {
            if (! $this->ip_range_start || ! $this->ip_range_end) {
                $this->addError('ip_range_start', 'Kedua rentang IP (awal dan akhir) harus diisi atau dikosongkan bersamaan.');

                return;
            }
        }

        IpPool::create([
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

        Flux::toast(variant: 'success', text: 'IP Pool berhasil dibuat.');

        $this->redirectRoute('ip-pools.index', navigate: true);
    }

    public function render(): View
    {
        return view('livewire.ip-pools.create', [
            'routers' => Router::orderBy('name')->get(),
        ]);
    }
}
