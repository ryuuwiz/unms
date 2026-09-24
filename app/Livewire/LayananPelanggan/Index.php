<?php

namespace App\Livewire\LayananPelanggan;

use App\Actions\LayananPelanggan\UbahStatusLayananAction;
use App\Enums\MikrotikJobStatus;
use App\Enums\MikrotikJobType;
use App\Enums\ProvisioningStatus;
use App\Enums\StatusLayanan;
use App\Jobs\Mikrotik\ProvisionPppoeAccountJob;
use App\Models\LayananPelanggan;
use App\Models\MikrotikJobLog;
use App\Models\PengaturanSiklusTagihan;
use App\Services\Mikrotik\MikrotikService;
use Flux\Flux;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Data Registrasi Billing')]
class Index extends Component
{
    use WithPagination;

    public string $search = '';

    public string $filterStatus = '';

    #[Url]
    public string $expiry = '';

    public ?int $deletingId = null;

    public function updatingExpiry(): void
    {
        $this->resetPage();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFilterStatus(): void
    {
        $this->resetPage();
    }

    public function confirmDelete(int $id): void
    {
        $layanan = LayananPelanggan::findOrFail($id);
        $this->authorize('delete', $layanan);
        $this->deletingId = $id;
    }

    public function deleteLayanan(): void
    {
        if (! $this->deletingId) {
            return;
        }

        $layanan = LayananPelanggan::findOrFail($this->deletingId);
        $this->authorize('delete', $layanan);

        $layanan->delete();
        $this->deletingId = null;

        Flux::toast(variant: 'success', text: 'Layanan berhasil dihapus.');
    }

    public function provisionLayanan(int $id, MikrotikService $mikrotikService): void
    {
        $layanan = LayananPelanggan::with(['router', 'paketLayanan.profilBandwidth', 'pelanggan'])->findOrFail($id);
        $this->authorize('update', $layanan);

        if (! $layanan->router) {
            Flux::toast(variant: 'danger', text: "Layanan {$layanan->ppp_username} belum terhubung ke router manapun.");

            return;
        }

        if ($layanan->status === StatusLayanan::Proses) {
            $layanan->isiPppPasswordJikaKosong();
        }

        try {
            $result = $mikrotikService->createOrUpdatePppoeSecret($layanan->router, $layanan);
            $action = $result['action'] ?? 'terprovisi';

            MikrotikJobLog::create([
                'router_id' => $layanan->router_id,
                'layanan_pelanggan_id' => $layanan->id,
                'job_type' => MikrotikJobType::ProvisionPppoe,
                'status' => MikrotikJobStatus::Success,
                'attempt_count' => 1,
                'payload' => $result,
                'finished_at' => now(),
            ]);

            Flux::toast(
                variant: 'success',
                text: "Berhasil provisi akun PPPoE {$layanan->ppp_username} ({$action}) di router {$layanan->router->nama_router}."
            );
        } catch (\Throwable $e) {
            MikrotikJobLog::create([
                'router_id' => $layanan->router_id,
                'layanan_pelanggan_id' => $layanan->id,
                'job_type' => MikrotikJobType::ProvisionPppoe,
                'status' => MikrotikJobStatus::Failed,
                'attempt_count' => 1,
                'error_message' => $e->getMessage(),
                'finished_at' => now(),
            ]);

            Flux::toast(
                variant: 'danger',
                text: "Gagal provisi {$layanan->ppp_username}: {$e->getMessage()}"
            );
        }
    }

    /**
     * Provisi massal: hanya mengantrekan satu ProvisionPppoeAccountJob per layanan (Aktif/Suspend yang belum
     * terprovisi). Loop sinkron atas seluruh layanan dalam satu request web memblokir request, membuat router
     * kewalahan, dan melewati serialisasi per router; job berjalan di mikrotik-high dengan retry dan job log.
     * Layanan Berhenti tidak pernah diprovisi (secretnya sengaja dihapus).
     */
    public function provisionAllPending(): void
    {
        $this->authorize('viewAny', LayananPelanggan::class);

        $pending = LayananPelanggan::query()
            ->where('provisioning_status', '!=', ProvisioningStatus::Success)
            ->whereNotNull('router_id')
            ->whereNotNull('ppp_username')
            ->whereIn('status', [StatusLayanan::Aktif, StatusLayanan::Suspend])
            ->get();

        if ($pending->isEmpty()) {
            Flux::toast(variant: 'info', text: 'Semua layanan pelanggan sudah terprovisi.');

            return;
        }

        foreach ($pending as $layanan) {
            ProvisionPppoeAccountJob::dispatch($layanan);
        }

        Flux::toast(
            variant: 'success',
            text: "{$pending->count()} layanan diantrekan untuk diprovisi. Pantau hasilnya di Job Log MikroTik."
        );
    }

    public function toggleIsolir(int $id, UbahStatusLayananAction $ubahStatusAction): void
    {
        $layanan = LayananPelanggan::findOrFail($id);
        $this->authorize('update', $layanan);

        $statusBaru = $layanan->status === StatusLayanan::Suspend
            ? StatusLayanan::Aktif
            : StatusLayanan::Suspend;

        $catatan = $statusBaru === StatusLayanan::Suspend
            ? 'Isolir manual oleh admin'
            : 'Un-isolir / aktivasi manual oleh admin';

        $ubahStatusAction->execute(
            layanan: $layanan,
            statusBaru: $statusBaru,
            actor: auth()->user(),
            catatan: $catatan
        );

        Flux::toast(
            variant: 'success',
            text: "Status layanan {$layanan->ppp_username} berhasil diubah menjadi {$statusBaru->label()}."
        );
    }

    public function render(): View
    {
        $layanans = LayananPelanggan::query()
            ->with(['pelanggan', 'paketLayanan', 'router'])
            ->when($this->search, fn ($q) => $q->whereHas('pelanggan', fn ($pq) => $pq->search($this->search)))
            ->when($this->filterStatus, function ($q) {
                if ($this->filterStatus === 'expired') {
                    $q->whereNotNull('tanggal_expired')->where('tanggal_expired', '<', now());
                } else {
                    $q->where('status', $this->filterStatus);
                }
            })
            ->when(
                in_array($this->expiry, ['all', 'overdue', 'soon'], true),
                fn ($q) => $q->perluPerhatian(PengaturanSiklusTagihan::ambil()->leadDays(), $this->expiry)->orderBy('tanggal_expired'),
                fn ($q) => $q->latest(),
            )
            ->paginate(15);

        return view('livewire.layanan-pelanggan.index', [
            'layanans' => $layanans,
            'statuses' => StatusLayanan::cases(),
        ]);
    }
}
