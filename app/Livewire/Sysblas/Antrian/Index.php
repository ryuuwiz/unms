<?php

namespace App\Livewire\Sysblas\Antrian;

use App\Enums\Wa\StatusAntrianWa;
use App\Jobs\Wa\KirimWaBlastJob;
use App\Models\AntrianWaBlast;
use App\Models\Sysblas;
use App\Models\User;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('SysBlast - Monitoring Antrian Blast')]
class Index extends Component
{
    use WithPagination;

    // Filters
    public string $search = '';

    public string $status = '';

    public string $jenis = '';

    public string $sysblas_id = '';

    public string $tanggal_dari = '';

    public string $tanggal_sampai = '';

    // State Modal Detail
    public bool $showDetailModal = false;

    public ?AntrianWaBlast $selectedAntrian = null;

    // State Pemrosesan Manual
    public bool $isProcessing = false;

    public function mount(): void
    {
        $this->authorize('viewAny', User::class);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    public function updatingJenis(): void
    {
        $this->resetPage();
    }

    public function updatingSysblasId(): void
    {
        $this->resetPage();
    }

    public function retry(int $id): void
    {
        $antrian = AntrianWaBlast::findOrFail($id);

        $antrian->update([
            'status' => StatusAntrianWa::Menunggu,
            'pesan_error' => null,
            'dijadwalkan_pada' => Carbon::now(),
        ]);

        KirimWaBlastJob::dispatch($antrian);

        if ($this->selectedAntrian && $this->selectedAntrian->id === $id) {
            $this->selectedAntrian = $antrian->fresh(['sysblas', 'referensi']);
        }

        Flux::toast(variant: 'success', text: "Pesan ke {$antrian->no_hp_tujuan} berhasil dimasukkan ulang ke antrean pengiriman.");
    }

    public function batalkan(int $id): void
    {
        $antrian = AntrianWaBlast::findOrFail($id);

        if ($antrian->status !== StatusAntrianWa::Menunggu) {
            Flux::toast(variant: 'danger', text: 'Hanya antrean berstatus Menunggu yang dapat dibatalkan.');

            return;
        }

        $antrian->update([
            'status' => StatusAntrianWa::Gagal,
            'pesan_error' => 'Dibatalkan secara manual oleh administrator.',
        ]);

        if ($this->selectedAntrian && $this->selectedAntrian->id === $id) {
            $this->selectedAntrian = $antrian->fresh(['sysblas', 'referensi']);
        }

        Flux::toast(variant: 'success', text: "Antrean pesan ke {$antrian->no_hp_tujuan} berhasil dibatalkan.");
    }

    public function hapus(int $id): void
    {
        $antrian = AntrianWaBlast::findOrFail($id);
        $antrian->delete();

        Flux::toast(variant: 'success', text: 'Data antrean blast berhasil dihapus.');
    }

    public function openDetailModal(int $id): void
    {
        $this->selectedAntrian = AntrianWaBlast::with(['sysblas', 'referensi'])->findOrFail($id);
        $this->showDetailModal = true;
    }

    public function prosesAntrianSekarang(): void
    {
        $this->isProcessing = true;
        try {
            Artisan::call('wa:proses-antrian', ['--limit' => 50]);
            Flux::toast(variant: 'success', text: 'Pemrosesan antrean pesan WhatsApp berhasil dijalankan.');
        } catch (\Throwable $e) {
            Flux::toast(variant: 'danger', text: 'Gagal memproses antrean: '.$e->getMessage());
        } finally {
            $this->isProcessing = false;
        }
    }

    public function render(): View
    {
        $query = AntrianWaBlast::query()
            ->with(['sysblas', 'referensi'])
            ->latest('id');

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('no_hp_tujuan', 'like', "%{$this->search}%")
                    ->orWhere('pesan', 'like', "%{$this->search}%")
                    ->orWhere('jenis', 'like', "%{$this->search}%")
                    ->orWhere('pesan_error', 'like', "%{$this->search}%");
            });
        }

        if ($this->status) {
            $query->where('status', $this->status);
        }

        if ($this->jenis) {
            $query->where('jenis', 'like', "%{$this->jenis}%");
        }

        if ($this->sysblas_id) {
            $query->where('sysblas_id', $this->sysblas_id);
        }

        if ($this->tanggal_dari) {
            $query->whereDate('tanggal_kirim', '>=', $this->tanggal_dari);
        }

        if ($this->tanggal_sampai) {
            $query->whereDate('tanggal_kirim', '<=', $this->tanggal_sampai);
        }

        /** @var LengthAwarePaginator<AntrianWaBlast> $antrianList */
        $antrianList = $query->paginate(15);

        $totalAntrian = AntrianWaBlast::count();
        $totalTerkirim = AntrianWaBlast::where('status', StatusAntrianWa::Terkirim)->count();
        $totalMenunggu = AntrianWaBlast::where('status', StatusAntrianWa::Menunggu)->count();
        $totalGagal = AntrianWaBlast::where('status', StatusAntrianWa::Gagal)->count();

        $sysblasKoneksis = Sysblas::query()->orderBy('nama')->get();

        return view('livewire.sysblas.antrian.index', [
            'antrianList' => $antrianList,
            'sysblasKoneksis' => $sysblasKoneksis,
            'totalAntrian' => $totalAntrian,
            'totalTerkirim' => $totalTerkirim,
            'totalMenunggu' => $totalMenunggu,
            'totalGagal' => $totalGagal,
            'statusCases' => StatusAntrianWa::cases(),
        ]);
    }
}
