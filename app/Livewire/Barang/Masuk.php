<?php

namespace App\Livewire\Barang;

use App\Actions\Barang\CatatBarangMasukAction;
use App\Enums\Barang\ArahMutasiBarang;
use App\Enums\Barang\StatusUnitBarang;
use App\Enums\Barang\TipeMutasiBarang;
use App\Livewire\Concerns\MemilihUnitBarang;
use App\Models\JenisBarang;
use App\Models\KondisiBarang;
use App\Models\MutasiBarang;
use App\Models\PengaturanPrefixRegistrasi;
use Flux\Flux;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Barang Masuk -- lihat CONTEXT.md "Barang Masuk / Barang Keluar".
 */
#[Layout('layouts.app')]
#[Title('Barang Masuk')]
class Masuk extends Component
{
    use MemilihUnitBarang, WithPagination;

    #[Url]
    public string $bulan = '';

    #[Url]
    public string $search = '';

    public bool $showModal = false;

    public ?int $jenisId = null;

    public string $tipe = 'pembelian';

    public string $tanggal = '';

    public int $jumlah = 1;

    public ?int $kondisiId = null;

    public ?int $brandId = null;

    public string $keterangan = '';

    /** Mutasi terakhir yang meng-generate unit baru, untuk tautan cetak label. */
    public ?int $mutasiBaruId = null;

    public function mount(): void
    {
        $this->authorize('barang.lihat');

        if (! preg_match('/^\d{4}-\d{2}$/', $this->bulan)) {
            $this->bulan = Carbon::now()->format('Y-m');
        }
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['bulan', 'search'], true)) {
            $this->resetPage();
        }

        if (in_array($property, ['jenisId', 'tipe'], true)) {
            $this->unitIds = [];
        }
    }

    public function openCreateModal(): void
    {
        $this->authorize('barang.masuk');
        $this->reset(['jenisId', 'jumlah', 'kondisiId', 'brandId', 'keterangan', 'unitIds', 'scanKode']);
        $this->tipe = TipeMutasiBarang::Pembelian->value;
        $this->tanggal = Carbon::today()->toDateString();
        $this->resetValidation();
        $this->showModal = true;
    }

    public function simpan(CatatBarangMasukAction $action): void
    {
        $this->authorize('barang.masuk');

        $this->validate([
            'jenisId' => ['required', 'integer', 'exists:jenis_barang,id'],
            'tipe' => ['required', Rule::in(array_map(fn (TipeMutasiBarang $t) => $t->value, TipeMutasiBarang::masuk()))],
            'tanggal' => ['required', 'date', 'before_or_equal:today'],
            'jumlah' => ['required', 'integer', 'min:1', 'max:1000'],
            'kondisiId' => ['nullable', 'integer', 'exists:kondisi_barang,id'],
            'brandId' => ['nullable', 'integer', 'exists:pengaturan_prefix_registrasi,id'],
            'keterangan' => ['nullable', 'string', 'max:500'],
        ]);

        $user = auth('web')->user();
        abort_unless($user !== null, 403);

        $mutasi = $action->execute(
            jenis: JenisBarang::with('kategori')->findOrFail($this->jenisId),
            tipe: TipeMutasiBarang::from($this->tipe),
            tanggal: Carbon::parse($this->tanggal),
            jumlah: $this->jumlah,
            actor: $user,
            keterangan: trim($this->keterangan) ?: null,
            kondisi: $this->kondisiId ? KondisiBarang::find($this->kondisiId) : null,
            brand: $this->brandId ? PengaturanPrefixRegistrasi::find($this->brandId) : null,
            unitIds: $this->unitIds,
        );

        $this->mutasiBaruId = $mutasi->tipe !== TipeMutasiBarang::Pengembalian && $mutasi->units()->exists() ? $mutasi->id : null;
        $this->showModal = false;
        Flux::toast(variant: 'success', text: "Barang masuk dicatat ({$mutasi->jumlah} {$mutasi->jenisBarang->satuan}).");
    }

    protected function statusUnitDipilih(): array
    {
        return [StatusUnitBarang::Terpasang];
    }

    protected function jenisBarangTerpilih(): ?int
    {
        return $this->jenisId;
    }

    public function render(): View
    {
        $bulan = Carbon::createFromFormat('!Y-m', $this->bulan) ?: Carbon::now();
        $jenis = $this->jenisId ? JenisBarang::find($this->jenisId) : null;

        $mutasi = MutasiBarang::query()
            ->with(['jenisBarang', 'units'])
            ->where('arah', ArahMutasiBarang::Masuk)
            ->whereBetween('tanggal', [$bulan->copy()->startOfMonth()->toDateString(), $bulan->copy()->endOfMonth()->toDateString()])
            ->when(trim($this->search) !== '', fn (Builder $q) => $q->whereHas('jenisBarang', fn (Builder $j) => $j
                ->where('nama', 'like', '%'.trim($this->search).'%')
                ->orWhere('kode', 'like', '%'.trim($this->search).'%')))
            ->orderByDesc('tanggal')
            ->orderByDesc('id')
            ->paginate(20);

        return view('livewire.barang.masuk', [
            'mutasi' => $mutasi,
            'jenisList' => JenisBarang::query()->orderBy('nama')->get(),
            'jenisTerpilih' => $jenis,
            'tipes' => TipeMutasiBarang::masuk(),
            'kondisis' => KondisiBarang::query()->orderBy('kode')->get(),
            'brands' => PengaturanPrefixRegistrasi::query()->where('is_active', true)->orderBy('kode')->get(),
            'unitTerpilih' => $this->unitTerpilih(),
            'unitKandidat' => $this->unitKandidat(),
        ]);
    }
}
