<?php

namespace App\Livewire\Odp;

use App\Models\Odp;
use App\Models\Perumahan;
use Flux\Flux;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Edit ODP')]
class Edit extends Component
{
    #[Locked]
    public int $odpId;

    public string $nama_odp = '';

    public ?int $perumahan_id = null;

    public int $kapasitas_port = 8;

    public string $keterangan = '';

    public ?float $latitude = null;

    public ?float $longitude = null;

    public function mount(Odp $odp): void
    {
        $this->authorize('update', $odp);

        $this->odpId = $odp->id;
        $this->nama_odp = $odp->nama_odp;
        $this->perumahan_id = $odp->perumahan_id;
        $this->kapasitas_port = $odp->kapasitas_port;
        $this->keterangan = $odp->keterangan ?? '';
        $this->latitude = $odp->latitude;
        $this->longitude = $odp->longitude;
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'nama_odp' => ['required', 'string', 'max:100', Rule::unique('odp', 'nama_odp')->ignore($this->odpId)],
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
        $odp = Odp::findOrFail($this->odpId);
        $this->authorize('update', $odp);
        $this->validate();

        $oldKapasitas = $odp->kapasitas_port;
        $newKapasitas = $this->kapasitas_port;

        if ($oldKapasitas !== $newKapasitas) {
            $adjusted = $odp->adjustPortCapacity($newKapasitas);
            if (! $adjusted) {
                Flux::toast(variant: 'danger', text: 'Gagal mengubah kapasitas port: terdapat port bernomor tinggi yang sedang digunakan oleh pelanggan.');

                return;
            }
        }

        $odp->update([
            'nama_odp' => strtoupper(trim($this->nama_odp)),
            'perumahan_id' => $this->perumahan_id,
            'keterangan' => $this->keterangan ?: null,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
        ]);

        Flux::toast(variant: 'success', text: "Data ODP {$odp->nama_odp} berhasil diperbarui.");

        $this->redirectRoute('odp.index', navigate: true);
    }

    public function render(): View
    {
        return view('livewire.odp.edit', [
            'perumahans' => Perumahan::orderBy('nama_perumahan')->get(),
        ]);
    }
}
