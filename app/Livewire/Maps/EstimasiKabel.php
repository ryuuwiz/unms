<?php

namespace App\Livewire\Maps;

use App\Models\Odp;
use Flux\Flux;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Kalkulator Estimasi Kabel')]
class EstimasiKabel extends Component
{
    #[Url(as: 'lat')]
    public ?float $lat = null;

    #[Url(as: 'lng')]
    public ?float $lng = null;

    public string $search = '';

    public int $limit = 5;

    public float $faktor = 1.3;

    public int $reserve = 25;

    /**
     * @var array<int, array{
     *     id: int,
     *     nama_odp: string,
     *     keterangan: string|null,
     *     perumahan: string|null,
     *     lat: float,
     *     lng: float,
     *     jarak_lurus: float,
     *     estimasi_kabel: float,
     *     total_port: int,
     *     port_kosong: int,
     *     port_terpakai: int,
     *     port_rusak: int
     * }>
     */
    public array $results = [];

    public ?int $selectedOdpId = null;

    public function mount(?float $lat = null, ?float $lng = null): void
    {
        if ($lat !== null) {
            $this->lat = $lat;
        }
        if ($lng !== null) {
            $this->lng = $lng;
        }

        if ($this->lat && $this->lng) {
            $this->hitungEstimasi();
        }
    }

    public function hitungEstimasi(): void
    {
        $this->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'limit' => ['required', 'integer', 'min:1', 'max:20'],
            'faktor' => ['required', 'numeric', 'min:1.0', 'max:3.0'],
            'reserve' => ['required', 'integer', 'min:0', 'max:500'],
        ], [
            'lat.required' => 'Titik koordinat Latitude wajib diisi atau ditentukan di peta.',
            'lng.required' => 'Titik koordinat Longitude wajib diisi atau ditentukan di peta.',
        ]);

        $odps = Odp::query()
            ->terdekat((float) $this->lat, (float) $this->lng, null, $this->search)
            ->withCount([
                'ports as port_kosong_count' => fn ($q) => $q->where('status', 'kosong'),
                'ports as port_terpakai_count' => fn ($q) => $q->where('status', 'terpakai'),
                'ports as port_rusak_count' => fn ($q) => $q->where('status', 'rusak'),
            ])
            ->with('perumahan:id,nama_perumahan')
            ->take($this->limit)
            ->get();

        $this->results = $odps->map(function ($odp) {
            $jarakLurus = (float) $odp->jarak;
            $estimasiKabel = round(($jarakLurus * $this->faktor) + $this->reserve);

            return [
                'id' => $odp->id,
                'nama_odp' => $odp->nama_odp,
                'keterangan' => $odp->keterangan,
                'perumahan' => $odp->perumahan?->nama_perumahan,
                'lat' => (float) $odp->latitude,
                'lng' => (float) $odp->longitude,
                'jarak_lurus' => round($jarakLurus),
                'estimasi_kabel' => $estimasiKabel,
                'total_port' => (int) $odp->kapasitas_port,
                'port_kosong' => (int) $odp->port_kosong_count,
                'port_terpakai' => (int) $odp->port_terpakai_count,
                'port_rusak' => (int) $odp->port_rusak_count,
            ];
        })->toArray();

        if (empty($this->results)) {
            Flux::toast(variant: 'warning', text: 'Tidak ada ODP yang ditemukan dengan kriteria pencarian tersebut.');
        } else {
            $this->selectedOdpId = $this->results[0]['id'] ?? null;
            Flux::toast(variant: 'success', text: sprintf('Ditemukan %d ODP terdekat.', count($this->results)));
        }
    }

    public function selectOdp(int $id): void
    {
        $this->selectedOdpId = $id;
    }

    public function render(): View
    {
        return view('livewire.maps.estimasi-kabel');
    }
}
