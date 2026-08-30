<?php

namespace App\Livewire\ProfilBandwidth;

use App\Models\ProfilBandwidth;
use Flux\Flux;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Edit Profil Bandwidth')]
class Edit extends Component
{
    #[Locked]
    public int $profilId;

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

    public function mount(ProfilBandwidth $profilBandwidth): void
    {
        $this->authorize('update', $profilBandwidth);

        $this->profilId = $profilBandwidth->id;
        $this->nama_bandwidth = $profilBandwidth->nama_bandwidth;
        $this->max_limit_tx = $profilBandwidth->max_limit_tx;
        $this->max_limit_rx = $profilBandwidth->max_limit_rx;
        $this->priority = $profilBandwidth->priority;
        $this->useBurst = $profilBandwidth->hasBurst();
        $this->burst_rate_tx = $profilBandwidth->burst_rate_tx;
        $this->burst_rate_rx = $profilBandwidth->burst_rate_rx;
        $this->burst_threshold_tx = $profilBandwidth->burst_threshold_tx;
        $this->burst_threshold_rx = $profilBandwidth->burst_threshold_rx;
        $this->burst_time_tx = $profilBandwidth->burst_time_tx;
        $this->burst_time_rx = $profilBandwidth->burst_time_rx;
        $this->limit_rate_tx = $profilBandwidth->limit_rate_tx;
        $this->limit_rate_rx = $profilBandwidth->limit_rate_rx;
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        $rules = [
            'nama_bandwidth' => ['required', 'string', 'min:4', 'max:30', "unique:profil_bandwidth,nama_bandwidth,{$this->profilId}"],
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
        $profil = ProfilBandwidth::findOrFail($this->profilId);
        $this->authorize('update', $profil);
        $this->validate();

        $profil->update([
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

        Flux::toast(variant: 'success', text: 'Profil bandwidth berhasil diperbarui.');
        $this->redirectRoute('profil-bandwidth.index', navigate: true);
    }

    public function render(): View
    {
        return view('livewire.profil-bandwidth.edit');
    }
}
