<?php

namespace App\Livewire\Wilayah\Kota;

use App\Models\Kota;
use Flux\Flux;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Edit Kota')]
class Edit extends Component
{
    #[Locked]
    public int $kotaId;

    public string $nama_kota = '';

    public string $keterangan = '';

    public function mount(Kota $kota): void
    {
        $this->authorize('update', $kota);
        $this->kotaId = $kota->id;
        $this->nama_kota = $kota->nama_kota;
        $this->keterangan = $kota->keterangan ?? '';
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'nama_kota' => ['required', 'string', 'max:100', "unique:kota,nama_kota,{$this->kotaId}"],
            'keterangan' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function save(): void
    {
        $kota = Kota::findOrFail($this->kotaId);
        $this->authorize('update', $kota);
        $this->validate();

        $kota->update([
            'nama_kota' => $this->nama_kota,
            'keterangan' => $this->keterangan ?: null,
        ]);

        Flux::toast(variant: 'success', text: 'Data kota berhasil diperbarui.');
        $this->redirectRoute('wilayah.kota.index', navigate: true);
    }

    public function render(): View
    {
        return view('livewire.wilayah.kota.edit');
    }
}
