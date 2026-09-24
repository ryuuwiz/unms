<?php

namespace App\Livewire\Settings;

use App\Models\PengaturanKondisiBarang as PengaturanKondisiBarangModel;
use Flux\Flux;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Pengaturan Kondisi Barang')]
class PengaturanKondisiBarang extends Component
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
        $kondisi = PengaturanKondisiBarangModel::findOrFail($id);

        $this->editingId = $kondisi->id;
        $this->kode = $kondisi->kode;
        $this->nama = $kondisi->nama;
        $this->is_active = $kondisi->is_active;

        $this->showModal = true;
    }

    public function simpan(): void
    {
        $this->kode = strtoupper(trim($this->kode));

        $this->validate([
            'kode' => [
                'required', 'string', 'regex:/^[A-Z0-9]{2,10}$/',
                Rule::unique('pengaturan_kondisi_barang', 'kode')->ignore($this->editingId),
            ],
            'nama' => ['required', 'string', 'max:100'],
            'is_active' => ['required', 'boolean'],
        ], [
            'kode.regex' => 'Kode kondisi harus 2-10 huruf/angka kapital (contoh: NEW, PGT).',
        ]);

        $data = [
            'kode' => $this->kode,
            'nama' => trim($this->nama),
            'is_active' => $this->is_active,
        ];

        if ($this->editingId) {
            $kondisi = PengaturanKondisiBarangModel::findOrFail($this->editingId);
            $kondisi->update($data);

            Flux::toast(variant: 'success', text: "Kondisi barang '{$kondisi->kode}' berhasil diperbarui.");
        } else {
            $kondisi = PengaturanKondisiBarangModel::create($data);

            Flux::toast(variant: 'success', text: "Kondisi barang '{$kondisi->kode}' berhasil ditambahkan.");
        }

        $this->showModal = false;
        $this->resetForm();
    }

    public function toggleStatus(int $id): void
    {
        $kondisi = PengaturanKondisiBarangModel::findOrFail($id);
        $kondisi->update(['is_active' => ! $kondisi->is_active]);

        $statusText = $kondisi->is_active ? 'diaktifkan' : 'dinonaktifkan';
        Flux::toast(variant: 'success', text: "Kondisi barang '{$kondisi->kode}' berhasil {$statusText}.");
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
        $query = PengaturanKondisiBarangModel::query();

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('kode', 'like', "%{$this->search}%")
                    ->orWhere('nama', 'like', "%{$this->search}%");
            });
        }

        return view('livewire.settings.pengaturan-kondisi-barang', [
            'items' => $query->orderBy('kode')->get(),
        ]);
    }
}
