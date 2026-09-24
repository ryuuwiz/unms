<?php

namespace App\Livewire\Barang;

use App\Models\Barang;
use Flux\Flux;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Ubah Barang')]
class Edit extends Component
{
    public int $barangId;

    public string $nama_barang = '';

    public string $satuan = 'unit';

    public string $keterangan = '';

    public bool $is_active = true;

    public function mount(Barang $barang): void
    {
        $this->authorize('update', $barang);

        $this->barangId = $barang->id;
        $this->nama_barang = $barang->nama_barang;
        $this->satuan = $barang->satuan;
        $this->keterangan = (string) $barang->keterangan;
        $this->is_active = $barang->is_active;
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'nama_barang' => ['required', 'string', 'max:255'],
            'satuan' => ['required', 'string', 'max:20'],
            'keterangan' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    public function save(): void
    {
        $barang = Barang::findOrFail($this->barangId);
        $this->authorize('update', $barang);
        $this->validate();

        $barang->update([
            'nama_barang' => $this->nama_barang,
            'satuan' => $this->satuan,
            'keterangan' => $this->keterangan ?: null,
            'is_active' => $this->is_active,
        ]);

        Flux::toast(variant: 'success', text: "Barang {$barang->kode_barang} berhasil diperbarui.");
        $this->redirectRoute('barang.index', navigate: true);
    }

    public function render(): View
    {
        $barang = Barang::with(['jenisBarang', 'kondisiBarang', 'cabangBarang'])->findOrFail($this->barangId);

        return view('livewire.barang.edit', [
            'barang' => $barang,
        ]);
    }
}
