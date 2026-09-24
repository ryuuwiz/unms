<?php

namespace App\Livewire\IpPublik;

use App\Livewire\Concerns\ValidatesIpPublik;
use App\Models\IpPublik;
use App\Models\Router;
use Flux\Flux;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Tambah IP Publik')]
class Create extends Component
{
    use ValidatesIpPublik;

    public ?int $router_id = null;

    public string $alamat_ip = '';

    public string $gateway = '';

    public string|int|float $harga_bulanan = 0;

    public string $keterangan = '';

    public function mount(): void
    {
        $this->authorize('create', IpPublik::class);

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
        return $this->ipPublikRules();
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return $this->ipPublikMessages();
    }

    public function save(): void
    {
        $this->authorize('create', IpPublik::class);
        $this->validate();

        IpPublik::create([
            'router_id' => $this->router_id,
            'alamat_ip' => $this->alamat_ip,
            'gateway' => $this->gateway,
            'harga_bulanan' => $this->harga_bulanan,
            'keterangan' => $this->keterangan ?: null,
        ]);

        Flux::toast(variant: 'success', text: 'IP Publik berhasil ditambahkan ke inventaris.');

        $this->redirectRoute('ip-publik.index', navigate: true);
    }

    public function render(): View
    {
        return view('livewire.ip-publik.create', [
            'routers' => Router::orderBy('nama_router')->get(),
        ]);
    }
}
