<?php

namespace App\Livewire\Mikrotik;

use App\Enums\MikrotikJobStatus;
use App\Enums\MikrotikJobType;
use App\Jobs\Mikrotik\DisablePppoeAccountJob;
use App\Jobs\Mikrotik\EnablePppoeAccountJob;
use App\Jobs\Mikrotik\PingRouterJob;
use App\Jobs\Mikrotik\ProvisionPppoeAccountJob;
use App\Jobs\Mikrotik\SyncIpPoolToRouterJob;
use App\Models\MikrotikJobLog;
use App\Models\Router;
use Flux\Flux;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Log Integrasi MikroTik')]
class LogIndex extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $filterRouter = '';

    #[Url]
    public string $filterJobType = '';

    #[Url]
    public string $filterStatus = '';

    public bool $autoRefresh = true;

    public function toggleAutoRefresh(): void
    {
        $this->autoRefresh = ! $this->autoRefresh;
    }

    public function refreshData(): void
    {
        // Triggers re-render
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFilterRouter(): void
    {
        $this->resetPage();
    }

    public function updatingFilterJobType(): void
    {
        $this->resetPage();
    }

    public function updatingFilterStatus(): void
    {
        $this->resetPage();
    }

    public function retryJob(int $id): void
    {
        $this->authorize('viewAny', Router::class);

        $log = MikrotikJobLog::with(['router', 'layananPelanggan', 'ipPool'])->findOrFail($id);

        match ($log->job_type) {
            MikrotikJobType::ProvisionPppoe => $log->layananPelanggan ? ProvisionPppoeAccountJob::dispatch($log->layananPelanggan) : null,
            MikrotikJobType::EnablePppoe => $log->layananPelanggan ? EnablePppoeAccountJob::dispatch($log->layananPelanggan) : null,
            MikrotikJobType::DisablePppoe => $log->layananPelanggan ? DisablePppoeAccountJob::dispatch($log->layananPelanggan) : null,
            MikrotikJobType::SyncIpPool => $log->ipPool ? SyncIpPoolToRouterJob::dispatch($log->ipPool) : null,
            MikrotikJobType::Ping, MikrotikJobType::TestConnection => $log->router ? PingRouterJob::dispatch($log->router) : null,
        };

        Flux::toast(variant: 'success', text: "Job {$log->job_type->label()} berhasil dimasukkan ulang ke antrean.");
    }

    public function render(): View
    {
        $this->authorize('viewAny', Router::class);

        $logs = MikrotikJobLog::query()
            ->with(['router', 'layananPelanggan.pelanggan', 'ipPool'])
            ->when($this->search, function ($q) {
                $term = "%{$this->search}%";
                $q->where(function ($sub) use ($term) {
                    $sub->where('error_message', 'like', $term)
                        ->orWhereHas('layananPelanggan', fn ($lp) => $lp->where('ppp_username', 'like', $term))
                        ->orWhereHas('ipPool', fn ($ip) => $ip->where('nama_pool', 'like', $term));
                });
            })
            ->when($this->filterRouter, fn ($q) => $q->where('router_id', $this->filterRouter))
            ->when($this->filterJobType, fn ($q) => $q->where('job_type', $this->filterJobType))
            ->when($this->filterStatus, fn ($q) => $q->where('status', $this->filterStatus))
            ->latest()
            ->paginate(20);

        $routers = Router::orderBy('nama_router')->get();

        return view('livewire.mikrotik.log-index', [
            'logs' => $logs,
            'routers' => $routers,
            'jobTypes' => MikrotikJobType::cases(),
            'statuses' => MikrotikJobStatus::cases(),
        ]);
    }
}
