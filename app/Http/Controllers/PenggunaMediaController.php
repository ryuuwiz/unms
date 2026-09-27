<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\CustomerDocumentService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PenggunaMediaController extends Controller
{
    /**
     * Pratinjau KTP staf terdekripsi ber-watermark. Hanya pemilik akun atau pemegang izin
     * `pengguna.lihat_ktp` (super_admin lolos lewat Gate::before); setiap akses tercatat.
     */
    public function previewKtp(Request $request, User $user, CustomerDocumentService $documentService): Response
    {
        /** @var User $viewer */
        $viewer = $request->user();

        abort_unless($viewer->is($user) || $viewer->can('pengguna.lihat_ktp'), 403);

        $media = $user->getKtpMedia();
        abort_if($media === null, 404, 'Berkas KTP tidak ditemukan untuk pengguna ini.');

        activity('pengguna')
            ->performedOn($user)
            ->causedBy($viewer)
            ->withProperties([
                'action' => 'preview_ktp',
                'ip' => $request->ip(),
                'user_agent' => (string) $request->userAgent(),
                'media_id' => $media->id,
            ])
            ->log("Melihat foto KTP staf {$user->name} (Terenkripsi & Watermarked)");

        return response($documentService->generateWatermarkedKtp($media, $viewer), 200, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0, private',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
