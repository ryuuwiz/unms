<?php

namespace App\Livewire\Wilayah\Kelurahan;

use App\Models\Kecamatan;
use App\Models\Kelurahan;
use App\Models\Kota;
use Flux\Flux;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Edit Kelurahan')]
class Edit extends Component
{
    #[Locked]
    public int $kelurahanId;

    public ?int $kota_id = null;

    public ?int $kecamatan_id = null;

    public string $nama_kelurahan = '';

    public string $keterangan = '';

    public function mount(Kelurahan $kelurahan): void
    {
        $this->authorize('update', $kelurahan->kecamatan->kota);
        $this->kelurahanId = $kelurahan->id;
        $this->kota_id = $kelurahan->kecamatan->kota_id;
        $this->kecamatan_id = $kelurahan->kecamatan_id;
        $this->nama_kelurahan = $kelurahan->nama_kelurahan;
        $this->keterangan = $kelurahan->keterangan ?? '';
    }

    public function updatedKotaId(): void
    {
        $this->kecamatan_id = null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'kota_id' => ['required', 'integer', 'exists:kota,id'],
            'kecamatan_id' => ['required', 'integer', 'exists:kecamatan,id'],
            'nama_kelurahan' => [
                'required',
                'string',
                'max:100',
                Rule::unique('kelurahan', 'nama_kelurahan')
                    ->where('kecamatan_id', $this->kecamatan_id)
                    ->ignore($this->kelurahanId),
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
            'kecamatan_id.required' => 'Kecamatan induk wajib dipilih.',
            'kecamatan_id.exists' => 'Kecamatan yang dipilih tidak valid.',
            'nama_kelurahan.required' => 'Nama kelurahan wajib diisi.',
            'nama_kelurahan.unique' => 'Kelurahan dengan nama ini sudah terdaftar di kecamatan terpilih.',
        ];
    }

    public function save(): void
    {
        $kelurahan = Kelurahan::findOrFail($this->kelurahanId);
        $this->authorize('update', $kelurahan->kecamatan->kota);
        $this->validate();

        $kelurahan->update([
            'kecamatan_id' => $this->kecamatan_id,
            'nama_kelurahan' => $this->nama_kelurahan,
            'keterangan' => $this->keterangan ?: null,
        ]);

        Flux::toast(variant: 'success', text: 'Data kelurahan berhasil diperbarui.');
        $this->redirectRoute('wilayah.kelurahan.index', navigate: true);
    }

    public function render(): View
    {
        $kotas = Kota::orderBy('nama_kota')->get();
        $kecamatans = $this->kota_id
            ? Kecamatan::where('kota_id', $this->kota_id)->orderBy('nama_kecamatan')->get()
            : collect();

        return view('livewire.wilayah.kelurahan.edit', [
            'kotas' => $kotas,
            'kecamatans' => $kecamatans,
        ]);
    }
}
