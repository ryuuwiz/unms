<?php

namespace App\Livewire\Barang;

use App\Actions\Barang\CatatBarangMasukAction;
use App\Enums\Barang\TipeMutasiBarang;
use App\Exports\DataBarangExport;
use App\Models\JenisBarang;
use App\Models\KategoriBarang;
use App\Models\KondisiBarang;
use App\Models\PengaturanPrefixRegistrasi;
use Flux\Flux;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Data Barang: rekap Stok Periode per jenis barang -- lihat CONTEXT.md "Stok Periode".
 */
#[Layout('layouts.app')]
#[Title('Data Barang')]
class Index extends Component
{
    use WithPagination;

    #[Url]
    public string $bulan = '';

    #[Url]
    public string $search = '';

    #[Url]
    public ?int $kategoriId = null;

    /** Filter jumlah: tampilkan hanya barang dengan Stok Akhir <= nilai ini (0 = habis). */
    #[Url]
    public ?int $stokMaks = null;

    #[Url]
    public string $urut = 'nama';

    public bool $showModal = false;

    public ?int $editingId = null;

    public string $kode = '';

    public string $nama = '';

    public ?int $formKategoriId = null;

    public string $satuan = 'pcs';

    public bool $dilacakPerUnit = false;

    /** Saldo Awal Barang -- hanya selama jenis barang belum punya mutasi (CONTEXT.md "Saldo Awal Barang"). */
    public int $stokAwal = 0;

    public string $tanggalStokAwal = '';

    public ?int $kondisiId = null;

    public ?int $brandId = null;

    public function mount(): void
    {
        $this->authorize('barang.lihat');

        if (! preg_match('/^\d{4}-\d{2}$/', $this->bulan)) {
            $this->bulan = Carbon::now()->format('Y-m');
        }
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['bulan', 'search', 'kategoriId', 'stokMaks', 'urut'], true)) {
            $this->resetPage();
        }
    }

    public function openCreateModal(): void
    {
        $this->authorize('barang.buat');
        $this->resetForm();
        $this->showModal = true;
    }

    public function openEditModal(int $id): void
    {
        $this->authorize('barang.ubah');
        $jenis = JenisBarang::findOrFail($id);

        $this->editingId = $jenis->id;
        $this->kode = $jenis->kode;
        $this->nama = $jenis->nama;
        $this->formKategoriId = $jenis->kategori_barang_id;
        $this->satuan = $jenis->satuan;
        $this->dilacakPerUnit = $jenis->dilacak_per_unit;
        $this->stokAwal = 0;
        $this->tanggalStokAwal = Carbon::today()->toDateString();
        $this->kondisiId = null;
        $this->brandId = null;
        $this->resetValidation();
        $this->showModal = true;
    }

    public function simpan(CatatBarangMasukAction $catatMasuk): void
    {
        $this->authorize($this->editingId ? 'barang.ubah' : 'barang.buat');

        if ($this->stokAwal > 0) {
            $this->authorize('barang.masuk');
        }

        $this->kode = JenisBarang::normalkanKode($this->kode);

        $this->validate([
            'kode' => ['required', 'string', 'max:40', function (string $attribute, mixed $value, \Closure $gagal): void {
                if (! JenisBarang::kodeValid((string) $value)) {
                    $gagal('Kode hanya huruf kapital, angka, spasi, dan tanda - : / .');
                }
            }, Rule::unique('jenis_barang', 'kode')->ignore($this->editingId)],
            'nama' => ['required', 'string', 'max:150'],
            'formKategoriId' => ['required', 'integer', 'exists:kategori_barang,id'],
            'satuan' => ['required', 'string', 'max:20'],
            'dilacakPerUnit' => ['boolean'],
            'stokAwal' => ['integer', 'min:0', 'max:100000'],
            'tanggalStokAwal' => [Rule::requiredIf($this->stokAwal > 0), 'nullable', 'date', 'before_or_equal:today'],
            'kondisiId' => [Rule::requiredIf($this->stokAwal > 0 && $this->dilacakPerUnit), 'nullable', 'integer', 'exists:kondisi_barang,id'],
            'brandId' => ['nullable', 'integer', 'exists:pengaturan_prefix_registrasi,id'],
        ], [
            'kondisiId.required' => 'Kondisi wajib dipilih untuk stok awal barang yang dilacak per unit.',
        ]);

        $data = [
            'kode' => $this->kode,
            'nama' => trim($this->nama),
            'kategori_barang_id' => $this->formKategoriId,
            'satuan' => trim($this->satuan),
        ];

        $user = auth('web')->user();
        abort_unless($user !== null, 403);

        DB::transaction(function () use ($data, $catatMasuk, $user) {
            $jenis = $this->editingId ? JenisBarang::findOrFail($this->editingId) : new JenisBarang;
            $tanpaMutasi = ! $jenis->exists || ! $jenis->mutasi()->exists();

            // Mode pelacakan dikunci setelah ada mutasi: mengubahnya membuat stok & unit tidak konsisten.
            if ($tanpaMutasi) {
                $data['dilacak_per_unit'] = $this->dilacakPerUnit;
            }

            $jenis->fill($data)->save();

            if ($tanpaMutasi && $this->stokAwal > 0) {
                $catatMasuk->execute(
                    jenis: $jenis->load('kategori'),
                    tipe: TipeMutasiBarang::SaldoAwal,
                    tanggal: Carbon::parse($this->tanggalStokAwal),
                    jumlah: $this->stokAwal,
                    actor: $user,
                    kondisi: $this->kondisiId ? KondisiBarang::find($this->kondisiId) : null,
                    brand: $this->brandId ? PengaturanPrefixRegistrasi::find($this->brandId) : null,
                );
            }
        });

        Flux::toast(variant: 'success', text: 'Data barang disimpan.');
        $this->showModal = false;
        $this->resetForm();
    }

    public function hapus(int $id): void
    {
        $this->authorize('barang.hapus');
        $jenis = JenisBarang::findOrFail($id);

        if ($jenis->mutasi()->exists()) {
            Flux::toast(variant: 'danger', text: 'Barang yang sudah memiliki mutasi tidak dapat dihapus.');

            return;
        }

        $jenis->delete();
        Flux::toast(variant: 'success', text: 'Data barang dihapus.');
    }

    public function exportExcel(): BinaryFileResponse
    {
        $this->authorize('barang.lihat');

        return Excel::download($this->export(), 'Data-Barang-'.$this->bulan.'.xlsx');
    }

    protected function export(): DataBarangExport
    {
        return new DataBarangExport(
            bulan: Carbon::createFromFormat('!Y-m', $this->bulan) ?: Carbon::now(),
            search: trim($this->search),
            kategoriId: $this->kategoriId ?: null,
            stokMaks: $this->stokMaks,
            urut: $this->urut,
        );
    }

    protected function resetForm(): void
    {
        $this->editingId = null;
        $this->kode = '';
        $this->nama = '';
        $this->formKategoriId = null;
        $this->satuan = 'pcs';
        $this->dilacakPerUnit = false;
        $this->stokAwal = 0;
        $this->tanggalStokAwal = Carbon::today()->toDateString();
        $this->kondisiId = null;
        $this->brandId = null;
        $this->resetValidation();
    }

    public function render(): View
    {
        return view('livewire.barang.index', [
            'barangs' => $this->export()->query()->paginate(20),
            'kategoris' => KategoriBarang::query()->orderBy('nama')->get(),
            'bolehStokAwal' => ! $this->editingId || ! JenisBarang::find($this->editingId)?->mutasi()->exists(),
            'satuanList' => JenisBarang::query()->distinct()->orderBy('satuan')->pluck('satuan'),
            'kondisis' => KondisiBarang::query()->orderBy('kode')->get(),
            'brands' => PengaturanPrefixRegistrasi::query()->where('is_active', true)->orderBy('kode')->get(),
        ]);
    }
}
