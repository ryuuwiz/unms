<?php

namespace App\Livewire\Barang;

use App\Models\Barang;
use App\Models\PengaturanCabangBarang;
use App\Models\PengaturanJenisBarang;
use App\Models\PengaturanKondisiBarang;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Tambah Barang')]
class Create extends Component
{
    public ?int $jenis_barang_id = null;

    public ?int $kondisi_barang_id = null;

    public ?int $cabang_barang_id = null;

    public string $nama_barang = '';

    public string $satuan = 'unit';

    public ?int $stok = 0;

    public string $keterangan = '';

    public function mount(): void
    {
        $this->authorize('create', Barang::class);
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'jenis_barang_id' => ['required', 'integer', 'exists:pengaturan_jenis_barang,id'],
            'kondisi_barang_id' => ['required', 'integer', 'exists:pengaturan_kondisi_barang,id'],
            'cabang_barang_id' => ['required', 'integer', 'exists:pengaturan_cabang_barang,id'],
            'nama_barang' => ['required', 'string', 'max:255'],
            'satuan' => ['required', 'string', 'max:20'],
            'stok' => ['required', 'integer', 'min:0'],
            'keterangan' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'jenis_barang_id.required' => 'Jenis barang wajib dipilih.',
            'kondisi_barang_id.required' => 'Kondisi barang wajib dipilih.',
            'cabang_barang_id.required' => 'Cabang wajib dipilih.',
            'nama_barang.required' => 'Nama barang wajib diisi.',
        ];
    }

    public function save(): void
    {
        $this->authorize('create', Barang::class);
        $this->validate();

        $barang = Barang::create([
            'jenis_barang_id' => $this->jenis_barang_id,
            'kondisi_barang_id' => $this->kondisi_barang_id,
            'cabang_barang_id' => $this->cabang_barang_id,
            'nama_barang' => $this->nama_barang,
            'satuan' => $this->satuan,
            'stok' => $this->stok ?? 0,
            'keterangan' => $this->keterangan ?: null,
            'dibuat_oleh' => Auth::id(),
        ]);

        Flux::toast(variant: 'success', text: "Barang {$barang->kode_barang} berhasil ditambahkan.");
        $this->redirectRoute('barang.index', navigate: true);
    }

    public function render(): View
    {
        return view('livewire.barang.create', [
            'jenisList' => PengaturanJenisBarang::active()->orderBy('nama')->get(),
            'kondisiList' => PengaturanKondisiBarang::active()->orderBy('nama')->get(),
            'cabangList' => PengaturanCabangBarang::active()->orderBy('nama')->get(),
        ]);
    }
}
