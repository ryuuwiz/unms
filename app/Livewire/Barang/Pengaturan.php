<?php

namespace App\Livewire\Barang;

use App\Models\KategoriBarang;
use App\Models\KondisiBarang;
use Flux\Flux;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Kategori & Kondisi Barang, segmen Kode Barang -- lihat CONTEXT.md "Kode Barang".
 * Kode tidak dapat diubah setelah dipakai: unit berkode lama tidak ikut berubah.
 */
#[Layout('layouts.app')]
#[Title('Kategori & Kondisi Barang')]
class Pengaturan extends Component
{
    public bool $showModal = false;

    /** 'kategori' atau 'kondisi'. */
    #[Locked]
    public string $master = 'kategori';

    #[Locked]
    public ?int $editingId = null;

    public string $kode = '';

    public string $nama = '';

    public function mount(): void
    {
        $this->authorize('barang.ubah');
    }

    public function openModal(string $master, ?int $id = null): void
    {
        $this->authorize('barang.ubah');
        abort_unless(in_array($master, ['kategori', 'kondisi'], true), 404);

        $this->master = $master;
        $this->editingId = $id;
        $baris = $id ? $this->model()::findOrFail($id) : null;
        $this->kode = $baris->kode ?? '';
        $this->nama = $baris->nama ?? '';
        $this->resetValidation();
        $this->showModal = true;
    }

    public function simpan(): void
    {
        $this->authorize('barang.ubah');
        $this->kode = strtoupper(trim($this->kode));
        $tabel = $this->master === 'kategori' ? 'kategori_barang' : 'kondisi_barang';

        $this->validate([
            'kode' => ['required', 'regex:/^[A-Z0-9]{2,5}$/', Rule::unique($tabel, 'kode')->ignore($this->editingId)],
            'nama' => ['required', 'string', 'max:100'],
        ], [
            'kode.regex' => 'Kode 2-5 huruf kapital/angka tanpa spasi (mis. MDM, NEW, PGT).',
        ]);

        if ($this->editingId) {
            $baris = $this->model()::findOrFail($this->editingId);
            $data = ['nama' => trim($this->nama)];

            if ($baris->kode !== $this->kode && $this->kodeDipakai($baris)) {
                $this->addError('kode', 'Kode sudah dipakai unit/barang dan tidak dapat diubah.');

                return;
            }

            $baris->update($data + ['kode' => $this->kode]);
        } else {
            $this->model()::create(['kode' => $this->kode, 'nama' => trim($this->nama)]);
        }

        Flux::toast(variant: 'success', text: 'Tersimpan.');
        $this->showModal = false;
    }

    /**
     * Hapus kategori/kondisi yang belum dipakai. Penghitung kode tidak ikut dihapus, jadi kode yang
     * dibuat ulang melanjutkan nomor -- lihat CONTEXT.md "Kode Barang".
     */
    public function hapus(string $master, int $id): void
    {
        $this->authorize('barang.ubah');
        abort_unless(in_array($master, ['kategori', 'kondisi'], true), 404);

        $this->master = $master;
        $baris = $this->model()::findOrFail($id);

        if ($this->kodeDipakai($baris)) {
            Flux::toast(variant: 'danger', text: "{$baris->kode} masih dipakai dan tidak dapat dihapus.");

            return;
        }

        $baris->delete();
        Flux::toast(variant: 'success', text: "{$baris->kode} dihapus.");
    }

    /**
     * @return class-string<KategoriBarang>|class-string<KondisiBarang>
     */
    private function model(): string
    {
        return $this->master === 'kategori' ? KategoriBarang::class : KondisiBarang::class;
    }

    private function kodeDipakai(KategoriBarang|KondisiBarang $baris): bool
    {
        return $baris instanceof KategoriBarang
            ? $baris->jenisBarang()->exists()
            : $baris->units()->exists();
    }

    public function render(): View
    {
        return view('livewire.barang.pengaturan', [
            'kategoris' => KategoriBarang::query()->withCount('jenisBarang as dipakai')->orderBy('kode')->get(),
            'kondisis' => KondisiBarang::query()->withCount('units as dipakai')->orderBy('kode')->get(),
        ]);
    }
}
