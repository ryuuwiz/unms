<?php

namespace App\Services\Geospatial;

use SimpleXMLElement;
use ZipArchive;

class KmlParser
{
    /**
     * Parse konten KML atau berkas KMZ dan ekstrak titik (Point) dan poligon (Polygon).
     *
     * @return array{
     *     points: array<int, array{nama: string, keterangan: string|null, perumahan: string|null, kapasitas: int, latitude: float, longitude: float}>,
     *     polygons: array<int, array{nama: string, coordinates: array<int, array{0: float, 1: float}>}>
     * }
     */
    public function parse(string $rawContent, ?string $filePath = null): array
    {
        $kmlString = $this->extractKmlContent($rawContent, $filePath);

        if (empty(trim($kmlString))) {
            return ['points' => [], 'polygons' => []];
        }

        // Coba parsing dengan XML Parser terlebih dahulu
        $result = $this->parseXml($kmlString);

        // Jika XML parser tidak menghasilkan point/polygon (misal karena malformed XML dari Google Earth), gunakan Regex Fallback
        if (empty($result['points']) && empty($result['polygons'])) {
            $result = $this->parseWithRegexFallback($kmlString);
        }

        return $result;
    }

    /**
     * Ekstrak isi KML dari raw content (mendukung format KMZ / ZIP archive).
     */
    private function extractKmlContent(string $content, ?string $filePath = null): string
    {
        // Deteksi apakah berkas adalah KMZ (Zip header: PK\x03\x04)
        if (str_starts_with($content, "PK\x03\x04") && class_exists(ZipArchive::class)) {
            $tempFile = $filePath ?: tempnam(sys_get_temp_dir(), 'kmz_');
            if (! $filePath) {
                file_put_contents($tempFile, $content);
            }

            $zip = new ZipArchive;
            if ($zip->open($tempFile) === true) {
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $stat = $zip->statIndex($i);
                    if ($stat && str_ends_with(strtolower($stat['name']), '.kml')) {
                        $kml = $zip->getFromIndex($i);
                        $zip->close();
                        if (! $filePath && file_exists($tempFile)) {
                            @unlink($tempFile);
                        }

                        return $kml ?: '';
                    }
                }
                $zip->close();
            }

            if (! $filePath && file_exists($tempFile)) {
                @unlink($tempFile);
            }
        }

        return $content;
    }

    /**
     * @return array{
     *     points: array<int, array{nama: string, keterangan: string|null, perumahan: string|null, kapasitas: int, latitude: float, longitude: float}>,
     *     polygons: array<int, array{nama: string, coordinates: array<int, array{0: float, 1: float}>}>
     * }
     */
    private function parseXml(string $kmlContent): array
    {
        $points = [];
        $polygons = [];

        // Bersihkan namespace XML untuk memudahkan query XPath
        $cleanXml = preg_replace('/xmlns[^=]*="[^"]*"/i', '', $kmlContent);
        $cleanXml = preg_replace('/<(\/?)[a-zA-Z0-9_-]+:([a-zA-Z0-9_-]+)/', '<$1$2', $cleanXml ?: $kmlContent);

        try {
            // Nonaktifkan error libxml eksternal
            libxml_use_internal_errors(true);
            $xml = new SimpleXMLElement($cleanXml ?: $kmlContent);
            libxml_clear_errors();
        } catch (\Throwable) {
            return ['points' => [], 'polygons' => []];
        }

        $placemarks = $xml->xpath('//Placemark');
        if (! $placemarks) {
            return ['points' => [], 'polygons' => []];
        }

        foreach ($placemarks as $placemark) {
            $name = trim((string) $placemark->name);
            $description = trim((string) $placemark->description);

            // Ekstrak folder induk jika ada
            $folderName = null;
            $parentFolder = $placemark->xpath('ancestor::Folder/name');
            if (! empty($parentFolder)) {
                $folderName = trim((string) $parentFolder[count($parentFolder) - 1]);
            }

            $perumahan = $folderName ?: $this->extractPerumahanFromText($description) ?: $this->extractPerumahanFromText($name);
            $kapasitas = $this->extractCapacityFromText($name) ?: $this->extractCapacityFromText($description) ?: 8;

            // 1. Ekstrak Point (Titik ODP)
            $pointNodes = $placemark->xpath('.//Point/coordinates');
            if (! empty($pointNodes)) {
                $coordString = trim((string) $pointNodes[0]);
                $parsedPoint = $this->parseSingleCoordinate($coordString);
                if ($parsedPoint) {
                    $points[] = [
                        'nama' => $name ?: 'ODP-'.(count($points) + 1),
                        'keterangan' => $description ? strip_tags($description) : null,
                        'perumahan' => $perumahan ?: null,
                        'kapasitas' => $kapasitas,
                        'latitude' => $parsedPoint['lat'],
                        'longitude' => $parsedPoint['lng'],
                    ];
                }
            }

            // 2. Ekstrak Polygon (Coverage Area)
            $polygonNodes = $placemark->xpath('.//Polygon//coordinates');
            if (! empty($polygonNodes)) {
                $rawCoords = trim((string) $polygonNodes[0]);
                $coordsList = $this->parseCoordinatesList($rawCoords);
                if (! empty($coordsList)) {
                    $polygons[] = [
                        'nama' => $name ?: ($folderName ?: 'Coverage-'.(count($polygons) + 1)),
                        'coordinates' => $coordsList,
                    ];
                }
            }
        }

        return [
            'points' => $points,
            'polygons' => $polygons,
        ];
    }

    /**
     * Fallback parser menggunakan Regular Expressions jika XML parser gagal.
     *
     * @return array{
     *     points: array<int, array{nama: string, keterangan: string|null, perumahan: string|null, kapasitas: int, latitude: float, longitude: float}>,
     *     polygons: array<int, array{nama: string, coordinates: array<int, array{0: float, 1: float}>}>
     * }
     */
    private function parseWithRegexFallback(string $kmlContent): array
    {
        $points = [];
        $polygons = [];

        // Match all <Placemark> blocks
        if (preg_match_all('/<Placemark\b[^>]*>(.*?)<\/Placemark>/is', $kmlContent, $placemarks)) {
            foreach ($placemarks[1] as $pmContent) {
                // Name
                $name = '';
                if (preg_match('/<name\b[^>]*>(.*?)<\/name>/is', $pmContent, $m)) {
                    $name = trim(strip_tags($m[1]));
                }

                // Description
                $description = null;
                if (preg_match('/<description\b[^>]*>(.*?)<\/description>/is', $pmContent, $m)) {
                    $description = trim(strip_tags($m[1]));
                }

                $kapasitas = $this->extractCapacityFromText($name) ?: $this->extractCapacityFromText($description) ?: 8;
                $perumahan = $this->extractPerumahanFromText($description) ?: $this->extractPerumahanFromText($name);

                // Point
                if (preg_match('/<Point\b[^>]*>.*?<coordinates\b[^>]*>(.*?)<\/coordinates>.*?<\/Point>/is', $pmContent, $m)) {
                    $parsedPoint = $this->parseSingleCoordinate(trim($m[1]));
                    if ($parsedPoint) {
                        $points[] = [
                            'nama' => $name ?: 'ODP-'.(count($points) + 1),
                            'keterangan' => $description ?: null,
                            'perumahan' => $perumahan ?: null,
                            'kapasitas' => $kapasitas,
                            'latitude' => $parsedPoint['lat'],
                            'longitude' => $parsedPoint['lng'],
                        ];
                    }
                }

                // Polygon
                if (preg_match('/<Polygon\b[^>]*>.*?<coordinates\b[^>]*>(.*?)<\/coordinates>.*?<\/Polygon>/is', $pmContent, $m)) {
                    $coordsList = $this->parseCoordinatesList(trim($m[1]));
                    if (! empty($coordsList)) {
                        $polygons[] = [
                            'nama' => $name ?: 'Coverage-'.(count($polygons) + 1),
                            'coordinates' => $coordsList,
                        ];
                    }
                }
            }
        }

        // Direct Point Coordinates fallback if no Placemark tags found
        if (empty($points)) {
            if (preg_match_all('/<coordinates\b[^>]*>([0-9.,\s\-\+]+)<\/coordinates>/is', $kmlContent, $coordBlocks)) {
                foreach ($coordBlocks[1] as $idx => $rawBlock) {
                    $cleanBlock = trim($rawBlock);
                    if (! str_contains($cleanBlock, "\n") && ! str_contains($cleanBlock, ' ')) {
                        $pt = $this->parseSingleCoordinate($cleanBlock);
                        if ($pt) {
                            $points[] = [
                                'nama' => 'ODP-'.($idx + 1),
                                'keterangan' => null,
                                'perumahan' => null,
                                'kapasitas' => 8,
                                'latitude' => $pt['lat'],
                                'longitude' => $pt['lng'],
                            ];
                        }
                    }
                }
            }
        }

        return [
            'points' => $points,
            'polygons' => $polygons,
        ];
    }

    /**
     * @return array{lat: float, lng: float}|null
     */
    private function parseSingleCoordinate(string $coordString): ?array
    {
        $coordString = preg_replace('/\s+/', '', $coordString);
        $parts = explode(',', $coordString);
        if (count($parts) >= 2) {
            $lng = (float) $parts[0];
            $lat = (float) $parts[1];

            if ($lat >= -90 && $lat <= 90 && $lng >= -180 && $lng <= 180) {
                return ['lat' => $lat, 'lng' => $lng];
            }
        }

        return null;
    }

    /**
     * @return array<int, array{0: float, 1: float}>
     */
    private function parseCoordinatesList(string $rawCoords): array
    {
        $result = [];
        $tuples = preg_split('/[\s\r\n]+/', trim($rawCoords));
        if ($tuples) {
            foreach ($tuples as $tuple) {
                $pt = $this->parseSingleCoordinate($tuple);
                if ($pt) {
                    $result[] = [$pt['lat'], $pt['lng']];
                }
            }
        }

        return $result;
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
