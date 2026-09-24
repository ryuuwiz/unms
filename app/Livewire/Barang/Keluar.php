<?php

namespace App\Livewire\Barang;

use App\Actions\Barang\CatatBarangKeluarAction;
use App\Enums\Barang\ArahMutasiBarang;
use App\Enums\Barang\StatusUnitBarang;
use App\Enums\Barang\TipeMutasiBarang;
use App\Livewire\Concerns\MemilihUnitBarang;
use App\Models\JenisBarang;
use App\Models\MutasiBarang;
use App\Models\Ticket;
use App\Models\User;
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
 * Barang Keluar -- lihat CONTEXT.md "Barang Masuk / Barang Keluar".
 */
#[Layout('layouts.app')]
#[Title('Barang Keluar')]
class Keluar extends Component
{
    use MemilihUnitBarang, WithPagination;

    #[Url]
    public string $bulan = '';

    #[Url]
    public string $search = '';

    #[Url]
    public ?int $filterTeknisiId = null;

    public bool $showModal = false;

    public ?int $jenisId = null;

    public string $tipe = 'pemakaian';

    public string $tanggal = '';

    public int $jumlah = 1;

    public ?int $teknisiId = null;

    public string $nomorTicket = '';

    public string $keterangan = '';

    public function mount(): void
    {
        $this->authorize('barang.lihat');

        if (! preg_match('/^\d{4}-\d{2}$/', $this->bulan)) {
            $this->bulan = Carbon::now()->format('Y-m');
        }
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['bulan', 'search', 'filterTeknisiId'], true)) {
            $this->resetPage();
        }

        if ($property === 'jenisId') {
            $this->unitIds = [];
        }
    }

    public function openCreateModal(): void
    {
        $this->authorize('barang.keluar');
        $this->reset(['jenisId', 'jumlah', 'teknisiId', 'nomorTicket', 'keterangan', 'unitIds', 'scanKode']);
        $this->tipe = TipeMutasiBarang::Pemakaian->value;
        $this->tanggal = Carbon::today()->toDateString();
        $this->resetValidation();
        $this->showModal = true;
    }

    public function simpan(CatatBarangKeluarAction $action): void
    {
        $this->authorize('barang.keluar');

        $this->validate([
            'jenisId' => ['required', 'integer', 'exists:jenis_barang,id'],
            'tipe' => ['required', Rule::in(array_map(fn (TipeMutasiBarang $t) => $t->value, TipeMutasiBarang::keluar()))],
            'tanggal' => ['required', 'date', 'before_or_equal:today'],
            'jumlah' => ['required', 'integer', 'min:1', 'max:100000'],
            'teknisiId' => [Rule::requiredIf($this->tipe === TipeMutasiBarang::Pemakaian->value), 'nullable', 'integer', 'exists:users,id'],
            'nomorTicket' => ['nullable', 'string', Rule::exists('ticket', 'nomor_ticket')],
            'keterangan' => ['nullable', 'string', 'max:500'],
        ], [
            'teknisiId.required' => 'Teknisi penerima wajib dipilih.',
            'nomorTicket.exists' => 'Nomor tiket tidak ditemukan.',
        ]);

        $user = auth('web')->user();
        abort_unless($user !== null, 403);

        $mutasi = $action->execute(
            jenis: JenisBarang::findOrFail($this->jenisId),
            tipe: TipeMutasiBarang::from($this->tipe),
            tanggal: Carbon::parse($this->tanggal),
            jumlah: $this->jumlah,
            actor: $user,
            keterangan: trim($this->keterangan) ?: null,
            teknisi: $this->teknisiId ? User::find($this->teknisiId) : null,
            ticket: trim($this->nomorTicket) !== '' ? Ticket::where('nomor_ticket', trim($this->nomorTicket))->first() : null,
            unitIds: $this->unitIds,
        );

        $this->showModal = false;
        Flux::toast(variant: 'success', text: "Barang keluar dicatat ({$mutasi->jumlah} {$mutasi->jenisBarang->satuan}).");
    }

    protected function statusUnitDipilih(): array
    {
        return StatusUnitBarang::tersedia();
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
            ->with(['jenisBarang', 'units', 'teknisi', 'ticket'])
            ->where('arah', ArahMutasiBarang::Keluar)
            ->whereBetween('tanggal', [$bulan->copy()->startOfMonth()->toDateString(), $bulan->copy()->endOfMonth()->toDateString()])
            ->when(trim($this->search) !== '', fn (Builder $q) => $q->whereHas('jenisBarang', fn (Builder $j) => $j
                ->where('nama', 'like', '%'.trim($this->search).'%')
                ->orWhere('kode', 'like', '%'.trim($this->search).'%')))
            ->when($this->filterTeknisiId, fn (Builder $q) => $q->where('teknisi_id', $this->filterTeknisiId))
            ->orderByDesc('tanggal')
            ->orderByDesc('id')
            ->paginate(20);

        return view('livewire.barang.keluar', [
            'mutasi' => $mutasi,
            'jenisList' => JenisBarang::query()->orderBy('nama')->get(),
            'jenisTerpilih' => $jenis,
            'stokTersedia' => $jenis?->stok(),
            'tipes' => TipeMutasiBarang::keluar(),
            'teknisis' => User::role('teknisi')->orderBy('name')->get(['id', 'name']),
            'unitTerpilih' => $this->unitTerpilih(),
            'unitKandidat' => $this->unitKandidat(),
        ]);
    }
}
