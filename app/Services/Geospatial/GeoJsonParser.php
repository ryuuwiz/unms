<?php

namespace App\Services\Geospatial;

class GeoJsonParser
{
    /**
     * Parse konten GeoJSON dan ekstrak titik (Point) dan poligon (Polygon/MultiPolygon).
     * Otomatis mendeteksi kapasitas port dan nama perumahan dari properties atau deskripsi.
     *
     * @return array{
     *     points: array<int, array{nama: string, keterangan: string|null, perumahan: string|null, kapasitas: int, latitude: float, longitude: float}>,
     *     polygons: array<int, array{nama: string, coordinates: array<int, array{0: float, 1: float}>}>
     * }
     */
    public function parse(string $geoJsonContent): array
    {
        $points = [];
        $polygons = [];

        $data = json_decode($geoJsonContent, true);
        if (! is_array($data)) {
            return ['points' => [], 'polygons' => []];
        }

        $features = [];
        if (isset($data['type']) && $data['type'] === 'FeatureCollection' && isset($data['features']) && is_array($data['features'])) {
            $features = $data['features'];
        } elseif (isset($data['type']) && $data['type'] === 'Feature') {
            $features = [$data];
        }

        // Global collection name
        $globalName = $data['name'] ?? $data['title'] ?? null;

        foreach ($features as $feature) {
            $geometry = $feature['geometry'] ?? null;
            $properties = $feature['properties'] ?? [];

            if (! is_array($geometry) || ! isset($geometry['type'], $geometry['coordinates'])) {
                continue;
            }

            $name = $properties['nama'] ?? $properties['name'] ?? $properties['title'] ?? $properties['nama_odp'] ?? null;
            $description = $properties['keterangan'] ?? $properties['description'] ?? $properties['desc'] ?? null;

            // Deteksi perumahan
            $perumahan = $properties['perumahan'] ?? $properties['cluster'] ?? $properties['area'] ?? $properties['nama_perumahan'] ?? $globalName ?? $this->extractPerumahanFromText($description) ?? $this->extractPerumahanFromText($name);

            // Deteksi kapasitas port
            $rawCap = $properties['kapasitas'] ?? $properties['capacity'] ?? $properties['port'] ?? $properties['ports'] ?? $properties['total_port'] ?? null;
            $capacity = (is_numeric($rawCap) && (int) $rawCap > 0)
                ? (int) $rawCap
                : ($this->extractCapacityFromText($name) ?? $this->extractCapacityFromText($description) ?? 8);

            // Ekstrak Point
            if ($geometry['type'] === 'Point') {
                $coords = $geometry['coordinates'];
                if (is_array($coords) && count($coords) >= 2) {
                    $lng = (float) $coords[0];
                    $lat = (float) $coords[1];

                    if ($lat >= -90 && $lat <= 90 && $lng >= -180 && $lng <= 180) {
                        $points[] = [
                            'nama' => $name ? (string) $name : 'ODP-'.(count($points) + 1),
                            'keterangan' => $description ? (string) $description : null,
                            'perumahan' => $perumahan ? (string) $perumahan : null,
                            'kapasitas' => $capacity,
                            'latitude' => $lat,
                            'longitude' => $lng,
                        ];
                    }
                }
            }

            // Ekstrak Polygon
            if ($geometry['type'] === 'Polygon') {
                $polyRings = $geometry['coordinates'];
                if (is_array($polyRings) && isset($polyRings[0]) && is_array($polyRings[0])) {
                    $polygonCoords = [];
                    foreach ($polyRings[0] as $pt) {
                        if (is_array($pt) && count($pt) >= 2) {
                            $lng = (float) $pt[0];
                            $lat = (float) $pt[1];
                            $polygonCoords[] = [$lat, $lng];
                        }
                    }

                    if (! empty($polygonCoords)) {
                        $polygons[] = [
                            'nama' => $name ? (string) $name : ($perumahan ?: 'Area-'.(count($polygons) + 1)),
                            'coordinates' => $polygonCoords,
                        ];
                    }
                }
            }
        }

        return [
            'points' => $points,
            'polygons' => $polygons,
        ];
    }

    private function extractCapacityFromText(?string $text): ?int
    {
        if (blank($text)) {
            return null;
        }

        if (preg_match('/1\s*:\s*(4|8|16|24|32)/i', $text, $matches)) {
            return (int) $matches[1];
        }

        if (preg_match('/\b(4|8|16|24|32)\s*(?:port|p|slot)\b/i', $text, $matches)) {
            return (int) $matches[1];
        }

        if (preg_match('/kapasitas\s*[:=]\s*(\d+)/i', $text, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }

    private function extractPerumahanFromText(?string $text): ?string
    {
        if (blank($text)) {
            return null;
        }

        if (preg_match('/(?:perumahan|cluster|area)\s*[:=]\s*([^\n\r,<]+)/i', $text, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }
}
