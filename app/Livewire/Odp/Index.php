<?php

namespace App\Livewire\Odp;

use App\Models\Odp;
use App\Models\Perumahan;
use App\Services\Geospatial\GeoJsonParser;
use App\Services\Geospatial\KmlParser;
use Flux\Flux;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Manajemen ODP')]
class Index extends Component
{
    use WithFileUploads, WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    // ─── State Import Modal ──────────────────────────────────────────
    public bool $showImportModal = false;

    /** @var mixed */
    public $importFile = null;

    /** @var array<int, array{nama: string, keterangan: string|null, perumahan: string|null, kapasitas: int, latitude: float, longitude: float}> */
    public array $parsedOdps = [];

    /** @var array<int, array{nama: string, coordinates: array<int, array{0: float, 1: float}>}> */
    public array $parsedPolygons = [];

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function openImportModal(): void
    {
        $this->reset(['importFile', 'parsedOdps', 'parsedPolygons']);
        $this->showImportModal = true;
    }

    public function closeImportModal(): void
    {
        $this->showImportModal = false;
        $this->reset(['importFile', 'parsedOdps', 'parsedPolygons']);
    }

    public function updatedImportFile(): void
    {
        $this->validate([
            'importFile' => 'required|file|max:20480', // Max 20MB
        ]);

        $this->parseUploadedFile();
    }

    public function parseUploadedFile(): void
    {
        if (! $this->importFile) {
            return;
        }

        $filePath = $this->importFile->getRealPath();
        $extension = strtolower($this->importFile->getClientOriginalExtension());
        $content = file_get_contents($filePath);

        if ($content === false) {
            Flux::toast(variant: 'danger', text: 'Gagal membaca berkas unggahan.');

            return;
        }

        if (in_array($extension, ['kml', 'kmz'], true) || str_starts_with($content, 'PK') || str_starts_with(trim($content), '<')) {
            $parser = app(KmlParser::class);
            $result = $parser->parse($content, $filePath);
            $this->parsedOdps = $result['points'];
            $this->parsedPolygons = $result['polygons'];
        } else {
            $parser = app(GeoJsonParser::class);
            $result = $parser->parse($content);
            $this->parsedOdps = $result['points'];
            $this->parsedPolygons = $result['polygons'];
        }

        if (empty($this->parsedOdps) && empty($this->parsedPolygons)) {
            Flux::toast(variant: 'warning', text: 'Tidak ada data titik Point (ODP) atau Polygon yang terdeteksi dari berkas.');
        } else {
            Flux::toast(
                variant: 'success',
                text: sprintf('Berhasil mendeteksi %d titik ODP dan %d coverage polygon.', count($this->parsedOdps), count($this->parsedPolygons))
            );
        }
    }

    public function executeImport(): void
    {
        if (empty($this->parsedOdps) && empty($this->parsedPolygons)) {
            Flux::toast(variant: 'danger', text: 'Tidak ada data yang dapat diimpor.');

            return;
        }

        DB::transaction(function () {
            $createdCount = 0;

            foreach ($this->parsedOdps as $item) {
                $nama = trim($item['nama']);
                $kapasitas = $item['kapasitas'] > 0 ? $item['kapasitas'] : 8;

                // Match perumahan jika nama tertera
                $perumahanId = null;
                if (! empty($item['perumahan'])) {
                    $perumahanId = Perumahan::where('nama_perumahan', 'like', '%'.trim($item['perumahan']).'%')->value('id');
                }

                // Buat nama ODP unik jika sudah ada
                $uniqueName = $nama;
                $counter = 1;
                while (Odp::where('nama_odp', $uniqueName)->exists()) {
                    $uniqueName = "{$nama}-{$counter}";
                    $counter++;
                }

                $odp = Odp::create([
                    'nama_odp' => $uniqueName,
                    'perumahan_id' => $perumahanId,
                    'kapasitas_port' => $kapasitas,
                    'keterangan' => $item['keterangan'] ?? null,
                    'latitude' => $item['latitude'],
                    'longitude' => $item['longitude'],
                ]);

                // Generate port dasar
                $odp->generateDefaultPorts();
                $createdCount++;
            }

            // Simpan polygon coverage jika ada
            if (! empty($this->parsedPolygons)) {
                foreach ($this->parsedPolygons as $poly) {
                    $polyName = trim($poly['nama']);
                    $prm = Perumahan::where('nama_perumahan', 'like', "%{$polyName}%")->first();
                    if ($prm) {
                        $geoJsonData = [
                            'type' => 'FeatureCollection',
                            'features' => [
                                [
                                    'type' => 'Feature',
                                    'properties' => ['name' => $poly['nama']],
                                    'geometry' => [
                                        'type' => 'Polygon',
                                        'coordinates' => [array_map(fn ($coord) => [$coord[1], $coord[0]], $poly['coordinates'])],
                                    ],
                                ],
                            ],
                        ];
                        $prm->update(['geojson' => json_encode($geoJsonData)]);
                    }
                }
            }

            Flux::toast(variant: 'success', text: "Berhasil mengimpor {$createdCount} titik sebaran ODP.");
        });

        $this->closeImportModal();
    }

    public function deleteOdp(int $id): void
    {
        $odp = Odp::findOrFail($id);
        $this->authorize('delete', $odp);

        $nama = $odp->nama_odp;
        $odp->delete();

        Flux::toast(variant: 'success', text: "ODP {$nama} berhasil dihapus.");
    }

    public function render(): View
    {
        $this->authorize('viewAny', Odp::class);

        $query = Odp::query()->with('perumahan');

        if (filled($this->search)) {
            $query->search($this->search);
        }

        $odps = $query->latest('id')->paginate(15);

        $totalOdp = Odp::count();
        $totalTerpetakan = Odp::whereNotNull('latitude')->whereNotNull('longitude')->count();
        $totalCoveragePerumahan = Perumahan::whereNotNull('geojson')->count();

        return view('livewire.odp.index', [
            'odps' => $odps,
            'totalOdp' => $totalOdp,
            'totalTerpetakan' => $totalTerpetakan,
            'totalCoveragePerumahan' => $totalCoveragePerumahan,
        ]);
    }
}
