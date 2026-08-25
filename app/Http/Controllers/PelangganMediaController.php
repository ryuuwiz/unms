<?php

namespace App\Http\Controllers;

use App\Models\Pelanggan;
use App\Models\User;
use App\Services\CustomerDocumentService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class PelangganMediaController extends Controller
{
    use AuthorizesRequests;

    /**
     * Tampilkan foto KTP terenkripsi dengan watermark dinamis & audit log.
     */
    public function previewKtp(
        Request $request,
        Pelanggan $pelanggan,
        CustomerDocumentService $documentService
    ): Response {
        $this->authorize('viewKtp', $pelanggan);

        $media = $pelanggan->getKtpMedia();
        if (! $media) {
            abort(404, 'Berkas KTP tidak ditemukan untuk pelanggan ini.');
        }

        activity('pelanggan')
            ->performedOn($pelanggan)
            ->causedBy(auth()->user())
            ->withProperties([
                'action' => 'preview_ktp',
                'ip' => $request->ip(),
                'user_agent' => (string) $request->userAgent(),
                'file_name' => $media->file_name,
                'media_id' => $media->id,
            ])
            ->log("Melihat foto KTP pelanggan {$pelanggan->identitasLengkap()} (Terenkripsi & Watermarked)");

        /** @var User $staff */
        $staff = auth()->user();
        $watermarkedImage = $documentService->generateWatermarkedKtp($media, $staff);

        return response($watermarkedImage, 200, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0, private',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * Alirkan (stream) berkas dokumen MOU / pendukung terenkripsi.
     */
    public function streamDokumen(
        Request $request,
        Pelanggan $pelanggan,
        Media $media,
        CustomerDocumentService $documentService
    ): Response {
        $this->authorize('viewDokumen', $pelanggan);

        if ($media->model_type !== Pelanggan::class || (int) $media->model_id !== (int) $pelanggan->id || $media->collection_name !== 'dokumen') {
            abort(404, 'Dokumen tidak ditemukan atau bukan milik pelanggan terkait.');
        }

        $jenisDokumen = $media->getCustomProperty('jenis_dokumen', 'Dokumen Pendukung');

        activity('pelanggan')
            ->performedOn($pelanggan)
            ->causedBy(auth()->user())
            ->withProperties([
                'action' => 'stream_dokumen',
                'ip' => $request->ip(),
                'file_name' => $media->file_name,
                'media_id' => $media->id,
                'jenis_dokumen' => $jenisDokumen,
            ])
            ->log("Membuka dokumen {$media->file_name} ({$jenisDokumen}) milik pelanggan {$pelanggan->identitasLengkap()}");

        $decryptedContent = $documentService->getDecryptedContent($media);
        $mimeType = $media->getCustomProperty('original_mime_type') ?: ($media->mime_type ?: 'application/octet-stream');

        return response($decryptedContent, 200, [
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'inline; filename="'.$media->file_name.'"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0, private',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
