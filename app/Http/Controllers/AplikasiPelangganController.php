<?php

namespace App\Http\Controllers;

use App\Support\BrandPelanggan;
use App\Support\IkonAplikasi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Aset Aplikasi Pelanggan (PWA) yang ber-brand: manifest, ikon, dan service worker. Semua brand
 * berbagi satu domain Portal, jadi manifest mengikuti brand request ini (ADR-0066).
 */
class AplikasiPelangganController extends Controller
{
    private const PATH_MANIFEST = '/manifest.webmanifest';

    public function manifest(Request $request): JsonResponse
    {
        $brand = BrandPelanggan::untukPortal();
        $akar = substr($request->getPathInfo(), 0, -strlen(self::PATH_MANIFEST));

        $ikon = [];
        foreach ([192, 512] as $ukuran) {
            $ikon[] = ['src' => $brand->urlIkon($ukuran), 'sizes' => "{$ukuran}x{$ukuran}", 'type' => 'image/png', 'purpose' => 'any'];
            $ikon[] = ['src' => $brand->urlIkon($ukuran), 'sizes' => "{$ukuran}x{$ukuran}", 'type' => 'image/png', 'purpose' => 'maskable'];
        }

        return response()
            ->json([
                'id' => $akar.'/',
                'name' => $brand->nama(),
                'short_name' => $brand->namaPendek(),
                'start_url' => $akar.'/dashboard',
                'scope' => $akar.'/',
                'display' => 'standalone',
                'theme_color' => $brand->warnaUtama(),
                'background_color' => '#ffffff',
                'icons' => $ikon,
            ], options: JSON_UNESCAPED_SLASHES)
            ->header('Content-Type', 'application/manifest+json')
            ->header('Cache-Control', 'private, no-store');
    }

    public function ikon(string $brand, int $ukuran): Response
    {
        abort_unless(in_array($ukuran, IkonAplikasi::UKURAN, true), 404);
        $identitas = BrandPelanggan::dariPengenal($brand) ?? abort(404);

        return response(IkonAplikasi::png($identitas, $ukuran))
            ->header('Content-Type', 'image/png')
            ->header('Cache-Control', 'public, max-age=86400');
    }

    /**
     * Service worker minimal: hanya agar Portal memenuhi syarat dapat dipasang. Sengaja tanpa
     * cache offline -- tagihan dan Status Koneksi selalu dibaca dari server.
     */
    public function serviceWorker(): Response
    {
        $js = <<<'JS'
            self.addEventListener('install', () => self.skipWaiting());
            self.addEventListener('activate', (event) => event.waitUntil(self.clients.claim()));
            self.addEventListener('fetch', () => {});
            JS;

        return response($js)
            ->header('Content-Type', 'application/javascript')
            ->header('Cache-Control', 'no-cache');
    }
}
