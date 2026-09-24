<?php

namespace App\Livewire\IpPublik;

use App\Livewire\Concerns\ValidatesIpPublik;
use App\Models\IpPublik;
use App\Models\Router;
use Flux\Flux;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Edit IP Publik')]
class Edit extends Component
{
    use ValidatesIpPublik;

    #[Locked]
    public int $ipPublikId;

    public ?int $router_id = null;

    public string $alamat_ip = '';

    public string $gateway = '';

    public string|int|float $harga_bulanan = 0;

    public string $keterangan = '';

    public function mount(IpPublik $ipPublik): void
    {
        $this->authorize('update', $ipPublik);

        $this->ipPublikId = $ipPublik->id;
        $this->router_id = $ipPublik->router_id;
        $this->alamat_ip = $ipPublik->alamat_ip;
        $this->gateway = $ipPublik->gateway;
        $this->harga_bulanan = $ipPublik->harga_bulanan;
        $this->keterangan = $ipPublik->keterangan ?? '';
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return $this->ipPublikRules($this->ipPublikId);
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
        $ipPublik = IpPublik::findOrFail($this->ipPublikId);
        $this->authorize('update', $ipPublik);
        $this->validate();

        // Router/alamat menentukan secret pelanggan di RouterOS; kunci selama IP dipakai layanan.
        if (! $ipPublik->isTersedia() && ($this->router_id !== $ipPublik->router_id || $this->alamat_ip !== $ipPublik->alamat_ip)) {
            throw ValidationException::withMessages([
                'alamat_ip' => 'Router dan alamat IP tidak dapat diubah selama IP dipakai layanan. Lepas dari layanan terlebih dahulu.',
            ]);
        }

        // harga_ditagih (snapshot) sengaja tidak disentuh: perubahan harga daftar hanya berlaku untuk penetapan berikutnya.
        $ipPublik->update([
            'router_id' => $this->router_id,
            'alamat_ip' => $this->alamat_ip,
            'gateway' => $this->gateway,
            'harga_bulanan' => $this->harga_bulanan,
            'keterangan' => $this->keterangan ?: null,
        ]);

        Flux::toast(variant: 'success', text: 'IP Publik berhasil diperbarui.');

        $this->redirectRoute('ip-publik.index', navigate: true);
    }

    public function render(): View
    {
        return view('livewire.ip-publik.edit', [
            'routers' => Router::orderBy('nama_router')->get(),
            'terpakai' => ! IpPublik::findOrFail($this->ipPublikId)->isTersedia(),
        ]);
    }
}
