<?php

namespace App\Livewire\Wilayah\Kecamatan;

use App\Models\Kecamatan;
use App\Models\Kota;
use Flux\Flux;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Edit Kecamatan')]
class Edit extends Component
{
    #[Locked]
    public int $kecamatanId;

    public ?int $kota_id = null;

    public string $nama_kecamatan = '';

    public string $keterangan = '';

    public function mount(Kecamatan $kecamatan): void
    {
        $this->authorize('update', $kecamatan->kota);
        $this->kecamatanId = $kecamatan->id;
        $this->kota_id = $kecamatan->kota_id;
        $this->nama_kecamatan = $kecamatan->nama_kecamatan;
        $this->keterangan = $kecamatan->keterangan ?? '';
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'kota_id' => ['required', 'integer', 'exists:kota,id'],
            'nama_kecamatan' => [
                'required',
                'string',
                'max:100',
                Rule::unique('kecamatan', 'nama_kecamatan')
                    ->where('kota_id', $this->kota_id)
                    ->ignore($this->kecamatanId),
            ],
            'keterangan' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'kota_id.required' => 'Kota induk wajib dipilih.',
            'kota_id.exists' => 'Kota yang dipilih tidak valid.',
            'nama_kecamatan.required' => 'Nama kecamatan wajib diisi.',
            'nama_kecamatan.unique' => 'Kecamatan dengan nama ini sudah terdaftar di kota terpilih.',
        ];
    }

    public function save(): void
    {
        $kecamatan = Kecamatan::findOrFail($this->kecamatanId);
        $this->authorize('update', $kecamatan->kota);
        $this->validate();

        $kecamatan->update([
            'kota_id' => $this->kota_id,
            'nama_kecamatan' => $this->nama_kecamatan,
            'keterangan' => $this->keterangan ?: null,
        ]);

        Flux::toast(variant: 'success', text: 'Data kecamatan berhasil diperbarui.');
        $this->redirectRoute('wilayah.kecamatan.index', navigate: true);
    }

    public function render(): View
    {
        $kotas = Kota::orderBy('nama_kota')->get();

        return view('livewire.wilayah.kecamatan.edit', [
            'kotas' => $kotas,
        ]);
    }
}
