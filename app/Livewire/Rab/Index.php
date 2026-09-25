<?php

namespace App\Livewire\Rab;

use App\Enums\Ticket\DivisiTicket;
use App\Exports\RabExport;
use App\Models\RabItem;
use Carbon\CarbonInterface;
use Flux\Flux;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * RAB Kantor -- lihat CONTEXT.md "RAB Kantor".
 */
#[Layout('layouts.app')]
#[Title('RAB Kantor')]
class Index extends Component
{
    public const DIVISI_LAINNYA = 'lainnya';

    #[Url]
    public string $bulan = '';

    public bool $showModal = false;

    public ?int $editId = null;

    public string $uraian = '';

    public int $qty = 1;

    public int $harga = 0;

    public string $divisi = '';

    public string $divisiLainnya = '';

    public function mount(): void
    {
        $this->authorize('rab.lihat');

        if (! preg_match('/^\d{4}-\d{2}$/', $this->bulan)) {
            $this->bulan = Carbon::now()->format('Y-m');
        }
    }

    public function periode(): Carbon
    {
        return (Carbon::createFromFormat('!Y-m', $this->bulan) ?: Carbon::now())->startOfMonth();
    }

    public function openCreateModal(): void
    {
        $this->authorize('rab.kelola');
        $this->pastikanTidakTerkunci($this->periode());
        $this->reset(['editId', 'uraian', 'qty', 'harga', 'divisi', 'divisiLainnya']);
        $this->resetValidation();
        $this->showModal = true;
    }

    public function openEditModal(int $id): void
    {
        $this->authorize('rab.kelola');
        $item = RabItem::findOrFail($id);
        $this->pastikanTidakTerkunci($item->periode);

        $this->editId = $item->id;
        $this->uraian = $item->uraian;
        $this->qty = $item->qty;
        $this->harga = $item->harga;
        $this->divisi = $item->divisi->value ?? self::DIVISI_LAINNYA;
        $this->divisiLainnya = (string) $item->divisi_lainnya;
        $this->resetValidation();
        $this->showModal = true;
    }

    public function simpan(): void
    {
        $this->authorize('rab.kelola');

        $item = $this->editId ? RabItem::findOrFail($this->editId) : new RabItem(['periode' => $this->periode()]);
        $this->pastikanTidakTerkunci($item->periode);

        $this->validate([
            'uraian' => ['required', 'string', 'max:255'],
            'qty' => ['required', 'integer', 'min:1'],
            'harga' => ['required', 'integer', 'min:0'],
            'divisi' => ['required', Rule::in([...array_column(DivisiTicket::cases(), 'value'), self::DIVISI_LAINNYA])],
            'divisiLainnya' => ['nullable', 'string', 'max:255', 'required_if:divisi,'.self::DIVISI_LAINNYA],
        ]);

        $lainnya = $this->divisi === self::DIVISI_LAINNYA;

        $item->fill([
            'uraian' => trim($this->uraian),
            'qty' => $this->qty,
            'harga' => $this->harga,
            'divisi' => $lainnya ? null : $this->divisi,
            'divisi_lainnya' => $lainnya ? trim($this->divisiLainnya) : null,
        ])->save();

        $this->showModal = false;
        Flux::toast(variant: 'success', text: 'Item RAB disimpan.');
    }

    public function hapus(int $id): void
    {
        $this->authorize('rab.kelola');
        $item = RabItem::findOrFail($id);
        $this->pastikanTidakTerkunci($item->periode);

        $item->delete();
        Flux::toast(variant: 'success', text: 'Item RAB dihapus.');
    }

    public function salinBulanLalu(): void
    {
        $this->authorize('rab.kelola');
        $periode = $this->periode();
        $this->pastikanTidakTerkunci($periode);

        if (RabItem::whereDate('periode', $periode)->exists()) {
            Flux::toast(variant: 'danger', text: 'Bulan ini sudah berisi item RAB.');

            return;
        }

        $sumber = RabItem::whereDate('periode', $periode->copy()->subMonth())->orderBy('id')->get();

        if ($sumber->isEmpty()) {
            Flux::toast(variant: 'warning', text: 'Bulan sebelumnya tidak memiliki item RAB.');

            return;
        }

        $sumber->each(fn (RabItem $item) => $item->replicate()->fill(['periode' => $periode])->save());
        Flux::toast(variant: 'success', text: "{$sumber->count()} item disalin dari bulan sebelumnya.");
    }

    public function exportExcel(): BinaryFileResponse
    {
        $this->authorize('rab.lihat');

        return Excel::download(new RabExport($this->periode()), "RAB-Kantor-{$this->bulan}.xlsx");
    }

    public function bolehUbah(): bool
    {
        $user = auth('web')->user();

        return (bool) $user?->can('rab.kelola')
            && (! RabItem::bulanTerkunci($this->periode()) || $user->can('rab.buka_kunci'));
    }

    private function pastikanTidakTerkunci(CarbonInterface $periode): void
    {
        abort_if(
            RabItem::bulanTerkunci($periode) && ! auth('web')->user()?->can('rab.buka_kunci'),
            403,
            'Bulan ini sudah terkunci.',
        );
    }

    public function render(): View
    {
        $items = RabItem::whereDate('periode', $this->periode())->orderBy('id')->get();

        return view('livewire.rab.index', [
            'items' => $items,
            'grandTotal' => $items->sum(fn (RabItem $item) => $item->jumlah()),
            'terkunci' => RabItem::bulanTerkunci($this->periode()),
            'bolehUbah' => $this->bolehUbah(),
            'divisiList' => DivisiTicket::cases(),
        ]);
    }
}
