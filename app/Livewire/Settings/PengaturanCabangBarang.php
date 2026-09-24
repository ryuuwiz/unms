<?php

namespace App\Livewire\Settings;

use App\Models\PengaturanCabangBarang as PengaturanCabangBarangModel;
use Flux\Flux;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Pengaturan Cabang Barang')]
class PengaturanCabangBarang extends Component
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
        $cabang = PengaturanCabangBarangModel::findOrFail($id);

        $this->editingId = $cabang->id;
        $this->kode = $cabang->kode;
        $this->nama = $cabang->nama;
        $this->is_active = $cabang->is_active;

        $this->showModal = true;
    }

    public function simpan(): void
    {
        $this->kode = strtoupper(trim($this->kode));

        $this->validate([
            'kode' => [
                'required', 'string', 'regex:/^[A-Z0-9]{2,10}$/',
                Rule::unique('pengaturan_cabang_barang', 'kode')->ignore($this->editingId),
            ],
            'nama' => ['required', 'string', 'max:100'],
            'is_active' => ['required', 'boolean'],
        ], [
            'kode.regex' => 'Kode cabang harus 2-10 huruf/angka kapital (contoh: BF).',
        ]);

        $data = [
            'kode' => $this->kode,
            'nama' => trim($this->nama),
            'is_active' => $this->is_active,
        ];

        if ($this->editingId) {
            $cabang = PengaturanCabangBarangModel::findOrFail($this->editingId);
            $cabang->update($data);

            Flux::toast(variant: 'success', text: "Cabang '{$cabang->kode}' berhasil diperbarui.");
        } else {
            $cabang = PengaturanCabangBarangModel::create($data);

            Flux::toast(variant: 'success', text: "Cabang '{$cabang->kode}' berhasil ditambahkan.");
        }

        $this->showModal = false;
        $this->resetForm();
    }

    public function toggleStatus(int $id): void
    {
        $cabang = PengaturanCabangBarangModel::findOrFail($id);
        $cabang->update(['is_active' => ! $cabang->is_active]);

        $statusText = $cabang->is_active ? 'diaktifkan' : 'dinonaktifkan';
        Flux::toast(variant: 'success', text: "Cabang '{$cabang->kode}' berhasil {$statusText}.");
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
        $query = PengaturanCabangBarangModel::query();

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('kode', 'like', "%{$this->search}%")
                    ->orWhere('nama', 'like', "%{$this->search}%");
            });
        }

        return view('livewire.settings.pengaturan-cabang-barang', [
            'items' => $query->orderBy('kode')->get(),
        ]);
    }
}
