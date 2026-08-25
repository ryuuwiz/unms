<?php

namespace App\Http\Controllers\Api;

use App\Enums\StatusPelanggan;
use App\Http\Controllers\Controller;
use App\Models\LayananPelanggan;
use App\Models\Odp;
use App\Models\Pelanggan;
use App\Models\Perumahan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MapMarkerController extends Controller
{
    /**
     * Menyediakan data marker dan polygon secara teroptimasi untuk Data Maps.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $requestedLayers = array_filter(explode(',', (string) $request->query('layers', 'pelanggan,layanan,perumahan,odp,coverage')));
        $statusPelanggan = (string) $request->query('status_pelanggan', 'semua');
        $perumahanId = $request->query('perumahan_id');

        $markers = [];
        $polygons = [];

        // ── 1. Layer Pelanggan ──────────────────────────────────────────
        if (in_array('pelanggan', $requestedLayers, true)) {
            $query = Pelanggan::query()
                ->whereNotNull('latitude')
                ->whereNotNull('longitude')
                ->select(['id', 'no_reg', 'nama_depan', 'nama_belakang', 'no_hp', 'alamat_lengkap', 'status', 'latitude', 'longitude', 'perumahan_id']);

            if ($statusPelanggan !== 'semua') {
                $query->where('status', $statusPelanggan);
            }

            if (! empty($perumahanId)) {
                $query->where('perumahan_id', $perumahanId);
            }

            $pelanggans = $query->with('perumahan:id,nama_perumahan')->get();

            foreach ($pelanggans as $p) {
                $statusEnum = $p->status instanceof StatusPelanggan ? $p->status : StatusPelanggan::tryFrom((string) $p->status);
                $statusColor = match ($statusEnum) {
                    StatusPelanggan::Aktif => 'emerald',
                    StatusPelanggan::BelumTerpasang => 'zinc',
                    StatusPelanggan::ReqPemasangan => 'cyan',
                    StatusPelanggan::PemasanganSelesai => 'indigo',
                    StatusPelanggan::Expired => 'amber',
                    StatusPelanggan::Off => 'rose',
                    default => 'zinc',
                };

                $markers[] = [
                    'id' => $p->id,
                    'layer' => 'pelanggan',
                    'lat' => (float) $p->latitude,
                    'lng' => (float) $p->longitude,
                    'title' => $p->identitasLengkap(),
                    'subtitle' => ($p->perumahan?->nama_perumahan ? $p->perumahan->nama_perumahan.' • ' : '').$p->no_hp,
                    'badge' => $statusEnum?->label() ?? ucfirst((string) $p->status),
                    'color' => $statusColor,
                    'detail_url' => route('pelanggan.show', $p->id),
                    'icon' => 'user',
                ];
            }
        }

        // ── 2. Layer Layanan (Data Registrasi Billing / Site) ────────────
        if (in_array('layanan', $requestedLayers, true)) {
            $query = LayananPelanggan::query()
                ->whereHas('pelanggan', function ($q) use ($perumahanId) {
                    $q->whereNotNull('latitude')->whereNotNull('longitude');
                    if (! empty($perumahanId)) {
                        $q->where('perumahan_id', $perumahanId);
                    }
                })
                ->with([
                    'pelanggan:id,nama_depan,nama_belakang,no_reg,latitude,longitude',
                    'paketLayanan:id,nama_paket',
                    'router:id,nama_router',
                ])
                ->select(['id', 'pelanggan_id', 'paket_layanan_id', 'router_id', 'site_id', 'ppp_username', 'status']);

            $layanans = $query->get();

            foreach ($layanans as $l) {
                if (! $l->pelanggan || ! $l->pelanggan->latitude || ! $l->pelanggan->longitude) {
                    continue;
                }

                $markers[] = [
                    'id' => $l->id,
                    'layer' => 'layanan',
                    'lat' => (float) $l->pelanggan->latitude,
                    'lng' => (float) $l->pelanggan->longitude,
                    'title' => $l->site_id.' ('.$l->ppp_username.')',
                    'subtitle' => ($l->paketLayanan?->nama_paket ?? 'Paket').' • Router: '.($l->router?->nama_router ?? '-'),
                    'badge' => $l->statusBadgeLabel(),
                    'color' => $l->statusBadgeColor(),
                    'detail_url' => route('pelanggan.show', $l->pelanggan_id),
                    'icon' => 'rss',
                ];
            }
        }

        // ── 3. Layer ODP (Optical Distribution Point) ────────────────────
        if (in_array('odp', $requestedLayers, true)) {
            $query = Odp::query()
                ->whereNotNull('latitude')
                ->whereNotNull('longitude')
                ->withCount([
                    'ports as port_kosong_count' => fn ($q) => $q->where('status', 'kosong'),
                    'ports as port_terpakai_count' => fn ($q) => $q->where('status', 'terpakai'),
                ])
                ->with('perumahan:id,nama_perumahan')
                ->select(['id', 'nama_odp', 'perumahan_id', 'kapasitas_port', 'keterangan', 'latitude', 'longitude']);

            if (! empty($perumahanId)) {
                $query->where('perumahan_id', $perumahanId);
            }

            $odps = $query->get();

            foreach ($odps as $odp) {
                $sisaPort = (int) $odp->port_kosong_count;
                $totalPort = (int) $odp->kapasitas_port;
                $color = $sisaPort > 0 ? 'sky' : 'rose';

                $markers[] = [
                    'id' => $odp->id,
                    'layer' => 'odp',
                    'lat' => (float) $odp->latitude,
                    'lng' => (float) $odp->longitude,
                    'title' => $odp->nama_odp,
                    'subtitle' => ($odp->perumahan?->nama_perumahan ? $odp->perumahan->nama_perumahan.' • ' : '').($odp->keterangan ?: 'Kapasitas '.$totalPort.' Port'),
                    'badge' => "{$sisaPort}/{$totalPort} Port Kosong",
                    'color' => $color,
                    'detail_url' => route('odp.show', $odp->id),
                    'icon' => 'circle-stack',
                ];
            }
        }

        // ── 4. Layer Perumahan (Titik Pusat Cluster) ────────────────────
        if (in_array('perumahan', $requestedLayers, true)) {
            $query = Perumahan::query()
                ->whereNotNull('latitude')
                ->whereNotNull('longitude')
                ->withCount(['pelanggans', 'odps'])
                ->select(['id', 'nama_perumahan', 'singkatan', 'latitude', 'longitude', 'keterangan']);

            if (! empty($perumahanId)) {
                $query->where('id', $perumahanId);
            }

            $perumahans = $query->get();

            foreach ($perumahans as $prm) {
                $markers[] = [
                    'id' => $prm->id,
                    'layer' => 'perumahan',
                    'lat' => (float) $prm->latitude,
                    'lng' => (float) $prm->longitude,
                    'title' => $prm->nama_perumahan.($prm->singkatan ? " ({$prm->singkatan})" : ''),
                    'subtitle' => "{$prm->pelanggans_count} Pelanggan • {$prm->odps_count} ODP",
                    'badge' => 'Cluster Coverage',
                    'color' => 'indigo',
                    'detail_url' => route('wilayah.perumahan.index'),
                    'icon' => 'home-modern',
                ];
            }
        }

        // ── 5. Layer Coverage Polygons (GeoJSON) ────────────────────────
        if (in_array('coverage', $requestedLayers, true)) {
            $query = Perumahan::query()
                ->whereNotNull('geojson')
                ->select(['id', 'nama_perumahan', 'geojson']);

            if (! empty($perumahanId)) {
                $query->where('id', $perumahanId);
            }

            $perumahans = $query->get();

            foreach ($perumahans as $prm) {
                $rawJson = $prm->geojson;
                if (! empty($rawJson)) {
                    $decoded = json_decode($rawJson, true);
                    if (is_array($decoded)) {
                        $polygons[] = [
                            'id' => $prm->id,
                            'nama' => $prm->nama_perumahan,
                            'geojson' => $decoded,
                        ];
                    }
                }
            }
        }

        return response()->json([
            'status' => 'success',
            'total_markers' => count($markers),
            'markers' => $markers,
            'polygons' => $polygons,
        ]);
    }
}
