<?php

namespace App\Livewire\Wilayah\Kota;

use App\Models\Kota;
use Flux\Flux;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Tambah Kota')]
class Create extends Component
{
    public string $nama_kota = '';

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
            'nama_kota' => ['required', 'string', 'max:100', 'unique:kota,nama_kota'],
            'keterangan' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'nama_kota.required' => 'Nama kota wajib diisi.',
            'nama_kota.unique' => 'Kota sudah terdaftar.',
        ];
    }

    public function save(): void
    {
        $this->authorize('create', new Kota);
        $this->validate();

        Kota::create([
            'nama_kota' => $this->nama_kota,
            'keterangan' => $this->keterangan ?: null,
        ]);

        Flux::toast(variant: 'success', text: "Kota {$this->nama_kota} berhasil ditambahkan.");
        $this->redirectRoute('wilayah.kota.index', navigate: true);
    }

    public function render(): View
    {
        return view('livewire.wilayah.kota.create');
    }
}
