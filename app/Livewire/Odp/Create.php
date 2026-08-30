<?php

namespace App\Livewire\Odp;

use App\Models\Odp;
use App\Models\Perumahan;
use Flux\Flux;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Tambah ODP')]
class Create extends Component
{
    public string $nama_odp = '';

    public ?int $perumahan_id = null;

    public ?int $kapasitas_port = 8;

    public string $keterangan = '';

    public ?float $latitude = null;

    public ?float $longitude = null;

    public function mount(): void
    {
        $this->authorize('create', Odp::class);
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'nama_odp' => ['required', 'string', 'max:100', 'unique:odp,nama_odp'],
            'perumahan_id' => ['nullable', 'integer', 'exists:perumahan,id'],
            'kapasitas_port' => ['required', 'integer', 'min:1', 'max:128'],
            'keterangan' => ['nullable', 'string', 'max:1000'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'nama_odp.required' => 'Nama / ID ODP wajib diisi.',
            'nama_odp.unique' => 'Nama ODP sudah digunakan.',
            'kapasitas_port.required' => 'Kapasitas port wajib ditentukan.',
        ];
    }

    public function save(): void
    {
        $this->authorize('create', Odp::class);
        $this->validate();

        $odp = Odp::create([
            'nama_odp' => strtoupper(trim($this->nama_odp)),
            'perumahan_id' => $this->perumahan_id,
            'kapasitas_port' => $this->kapasitas_port,
            'keterangan' => $this->keterangan ?: null,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
        ]);

        $odp->generateDefaultPorts();

        Flux::toast(variant: 'success', text: "ODP {$odp->nama_odp} ({$odp->kapasitas_port} Port) berhasil didaftarkan.");

        $this->redirectRoute('odp.index', navigate: true);
    }

    public function render(): View
    {
        return view('livewire.odp.create', [
            'perumahans' => Perumahan::orderBy('nama_perumahan')->get(),
        ]);
    }
}
