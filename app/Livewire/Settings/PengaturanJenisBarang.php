<?php

namespace App\Livewire\Settings;

use App\Models\PengaturanJenisBarang as PengaturanJenisBarangModel;
use Flux\Flux;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Pengaturan Jenis Barang')]
class PengaturanJenisBarang extends Component
{
    public string $search = '';

    public bool $showModal = false;

    public ?int $editingId = null;

    public string $kode = '';

    public string $nama = '';

    public bool $is_active = true;

    public function openCreateModal(): void
    {
        $this->resetForm();
        $this->showModal = true;
    }

    public function openEditModal(int $id): void
    {
        $jenis = PengaturanJenisBarangModel::findOrFail($id);

        $this->editingId = $jenis->id;
        $this->kode = $jenis->kode;
        $this->nama = $jenis->nama;
        $this->is_active = $jenis->is_active;

        $this->showModal = true;
    }

    public function simpan(): void
    {
        $this->kode = strtoupper(trim($this->kode));

        $this->validate([
            'kode' => [
                'required', 'string', 'regex:/^[A-Z0-9]{2,10}$/',
                Rule::unique('pengaturan_jenis_barang', 'kode')->ignore($this->editingId),
            ],
            'nama' => ['required', 'string', 'max:100'],
            'is_active' => ['required', 'boolean'],
        ], [
            'kode.regex' => 'Kode jenis harus 2-10 huruf/angka kapital (contoh: MDM).',
        ]);

        $data = [
            'kode' => $this->kode,
            'nama' => trim($this->nama),
            'is_active' => $this->is_active,
        ];

        if ($this->editingId) {
            $jenis = PengaturanJenisBarangModel::findOrFail($this->editingId);
            $jenis->update($data);

            Flux::toast(variant: 'success', text: "Jenis barang '{$jenis->kode}' berhasil diperbarui.");
        } else {
            $jenis = PengaturanJenisBarangModel::create($data);

            Flux::toast(variant: 'success', text: "Jenis barang '{$jenis->kode}' berhasil ditambahkan.");
        }

        $this->showModal = false;
        $this->resetForm();
    }

    public function toggleStatus(int $id): void
    {
        $jenis = PengaturanJenisBarangModel::findOrFail($id);
        $jenis->update(['is_active' => ! $jenis->is_active]);

        $statusText = $jenis->is_active ? 'diaktifkan' : 'dinonaktifkan';
        Flux::toast(variant: 'success', text: "Jenis barang '{$jenis->kode}' berhasil {$statusText}.");
    }

    protected function resetForm(): void
    {
        $this->editingId = null;
        $this->kode = '';
        $this->nama = '';
        $this->is_active = true;
    }

    public function render(): View
    {
        $query = PengaturanJenisBarangModel::query();

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('kode', 'like', "%{$this->search}%")
                    ->orWhere('nama', 'like', "%{$this->search}%");
            });
        }

        return view('livewire.settings.pengaturan-jenis-barang', [
            'items' => $query->orderBy('kode')->get(),
        ]);
    }
}
