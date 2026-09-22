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
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Tambah IP Pool')]
class Create extends Component
{
    use ValidatesIpPoolRange;

    public string $nama_pool = '';

    public ?int $router_id = null;

    public string $ip_network = '';

    public ?int $cidr = 24;

    public string $rentang_ip_awal = '';

    public string $rentang_ip_akhir = '';

    public ?int $priority_tx = 8;

    public ?int $priority_rx = 8;

    public function mount(): void
    {
        $this->authorize('create', IpPool::class);
        $this->initSingleRouterSelection();
    }

    /**
     * Auto-assign router_id jika hanya ada 1 Router terdaftar di sistem.
     */
    protected function initSingleRouterSelection(): void
    {
        $routers = Router::get(['id']);
        if ($routers->count() === 1) {
            $this->router_id = $routers->first()->id;
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'nama_pool' => ['required', 'string', 'max:100', 'regex:/^[a-zA-Z0-9_-]+$/', Rule::unique('ip_pool', 'nama_pool')->where('router_id', $this->router_id)],
            'router_id' => ['required', 'integer', 'exists:router,id'],
            'ip_network' => ['required', 'string', 'ipv4'],
            'cidr' => ['required', 'integer', 'min:1', 'max:32'],
            'rentang_ip_awal' => ['required', 'string', 'ipv4'],
            'rentang_ip_akhir' => ['bail', 'required', 'string', 'ipv4', $this->rentangIpAkhirRule()],
            'priority_tx' => ['required', 'integer', 'min:1', 'max:8'],
            'priority_rx' => ['required', 'integer', 'min:1', 'max:8'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'nama_pool.required' => 'Nama pool wajib diisi.',
            'nama_pool.regex' => 'Nama pool hanya boleh berisi huruf, angka, strip, dan underscore (tanpa spasi) agar valid sebagai remote-address di MikroTik.',
            'nama_pool.unique' => 'Nama pool sudah digunakan pada router ini.',
            'router_id.required' => 'Router wajib dipilih.',
            'ip_network.required' => 'IP Network wajib diisi.',
            'rentang_ip_awal.required' => 'Rentang IP awal wajib diisi.',
            'rentang_ip_akhir.required' => 'Rentang IP akhir wajib diisi.',
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
        $this->authorize('create', IpPool::class);
        $this->validate();

        IpPool::create([
            'router_id' => $this->router_id,
            'nama_pool' => $this->nama_pool,
            'ip_network' => $this->ip_network,
            'cidr' => $this->cidr,
            'rentang_ip_awal' => $this->rentang_ip_awal,
            'rentang_ip_akhir' => $this->rentang_ip_akhir,
            'priority_tx' => $this->priority_tx,
            'priority_rx' => $this->priority_rx,
        ]);

        Flux::toast(variant: 'success', text: 'IP Pool berhasil dibuat.');

        $this->redirectRoute('ip-pool.index', navigate: true);
    }

    public function render(): View
    {
        return view('livewire.ip-pool.create', [
            'routers' => Router::orderBy('nama_router')->get(),
        ]);
    }
}
