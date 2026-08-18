<?php

namespace App\Livewire\Wilayah\Kecamatan;

use App\Models\Kecamatan;
use App\Models\Kota;
use Flux\Flux;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Tambah Kecamatan')]
class Create extends Component
{
    public ?int $kota_id = null;

    public string $nama_kecamatan = '';

    public string $keterangan = '';

    public function mount(): void
    {
        $this->authorize('create', new Kota);
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
                Rule::unique('kecamatan', 'nama_kecamatan')->where('kota_id', $this->kota_id),
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
        $this->authorize('create', new Kota);
        $this->validate();

        Kecamatan::create([
            'kota_id' => $this->kota_id,
            'nama_kecamatan' => $this->nama_kecamatan,
            'keterangan' => $this->keterangan ?: null,
        ]);

        Flux::toast(variant: 'success', text: "Kecamatan {$this->nama_kecamatan} berhasil ditambahkan.");
        $this->redirectRoute('wilayah.kecamatan.index', navigate: true);
    }

    public function render(): View
    {
        $kotas = Kota::orderBy('nama_kota')->get();

        return view('livewire.wilayah.kecamatan.create', [
            'kotas' => $kotas,
        ]);
    }
}
