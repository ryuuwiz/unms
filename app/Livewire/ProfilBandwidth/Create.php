<?php

namespace App\Livewire\ProfilBandwidth;

use App\Models\ProfilBandwidth;
use Flux\Flux;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Tambah Profil Bandwidth')]
class Create extends Component
{
    public string $nama_bandwidth = '';

    public ?int $max_limit_tx = null;

    public ?int $max_limit_rx = null;

    public ?int $burst_rate_tx = null;

    public ?int $burst_rate_rx = null;

    public ?int $burst_threshold_tx = null;

    public ?int $burst_threshold_rx = null;

    public ?int $burst_time_tx = null;

    public ?int $burst_time_rx = null;

    public ?int $limit_rate_tx = null;

    public ?int $limit_rate_rx = null;

    public ?int $priority = 8;

    public bool $useBurst = false;

    public function mount(): void
    {
        $this->authorize('create', new ProfilBandwidth);
    }

    public function updatedMaxLimitTx(?int $value): void
    {
        // Auto-sync TX ke RX jika RX belum diubah
        if ($value !== null && $this->max_limit_rx === null) {
            $this->max_limit_rx = $value;
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        $rules = [
            'nama_bandwidth' => ['required', 'string', 'min:4', 'max:30', 'unique:profil_bandwidth,nama_bandwidth'],
            'max_limit_tx' => ['required', 'integer', 'min:1'],
            'max_limit_rx' => ['required', 'integer', 'min:1'],
            'priority' => ['required', 'integer', 'min:1', 'max:8'],
        ];

        if ($this->useBurst) {
            $rules['burst_rate_tx'] = ['required', 'integer', 'min:1', 'gte:max_limit_tx'];
            $rules['burst_rate_rx'] = ['required', 'integer', 'min:1', 'gte:max_limit_rx'];
            $rules['burst_threshold_tx'] = ['required', 'integer', 'min:1', 'lte:burst_rate_tx'];
            $rules['burst_threshold_rx'] = ['required', 'integer', 'min:1', 'lte:burst_rate_rx'];
            $rules['burst_time_tx'] = ['required', 'integer', 'min:1'];
            $rules['burst_time_rx'] = ['required', 'integer', 'min:1'];
            $rules['limit_rate_tx'] = ['nullable', 'integer', 'min:1', 'lte:max_limit_tx'];
            $rules['limit_rate_rx'] = ['nullable', 'integer', 'min:1', 'lte:max_limit_rx'];
        }

        return $rules;
    }

    public function save(): void
    {
        $this->authorize('create', new ProfilBandwidth);
        $this->validate();

        ProfilBandwidth::create([
            'nama_bandwidth' => $this->nama_bandwidth,
            'max_limit_tx' => $this->max_limit_tx,
            'max_limit_rx' => $this->max_limit_rx,
            'burst_rate_tx' => $this->useBurst ? $this->burst_rate_tx : null,
            'burst_rate_rx' => $this->useBurst ? $this->burst_rate_rx : null,
            'burst_threshold_tx' => $this->useBurst ? $this->burst_threshold_tx : null,
            'burst_threshold_rx' => $this->useBurst ? $this->burst_threshold_rx : null,
            'burst_time_tx' => $this->useBurst ? $this->burst_time_tx : null,
            'burst_time_rx' => $this->useBurst ? $this->burst_time_rx : null,
            'limit_rate_tx' => $this->useBurst ? $this->limit_rate_tx : null,
            'limit_rate_rx' => $this->useBurst ? $this->limit_rate_rx : null,
            'priority' => $this->priority,
        ]);

        Flux::toast(variant: 'success', text: "Profil bandwidth {$this->nama_bandwidth} berhasil ditambahkan.");
        $this->redirectRoute('profil-bandwidth.index', navigate: true);
    }

    public function render(): View
    {
        return view('livewire.profil-bandwidth.create');
    }
}
