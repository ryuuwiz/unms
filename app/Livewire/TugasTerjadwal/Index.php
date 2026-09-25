<?php

namespace App\Livewire\TugasTerjadwal;

use App\Enums\StatusTugasTerjadwal;
use App\Models\LogTugasTerjadwal;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Log Tugas Terjadwal')]
class Index extends Component
{
    use WithPagination;

    #[Url]
    public string $perintah = '';

    #[Url]
    public string $status = '';

    public ?int $outputLogId = null;

    public function updatedPerintah(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function outputLog(): ?LogTugasTerjadwal
    {
        return $this->outputLogId ? LogTugasTerjadwal::find($this->outputLogId) : null;
    }

    public function render(): View
    {
        $ringkasan = LogTugasTerjadwal::whereIn('id', LogTugasTerjadwal::selectRaw('MAX(id)')->groupBy('perintah'))
            ->orderBy('perintah')
            ->get();

        $logs = LogTugasTerjadwal::query()
            ->when($this->perintah !== '', fn ($q) => $q->where('perintah', $this->perintah))
            ->when($this->status !== '', fn ($q) => $q->where('status', $this->status))
            ->latest('id')
            ->paginate(25);

        return view('livewire.tugas-terjadwal.index', [
            'ringkasan' => $ringkasan,
            'logs' => $logs,
            'statuses' => StatusTugasTerjadwal::cases(),
        ]);
    }
}
