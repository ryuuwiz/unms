<?php

namespace App\Services;

use App\Models\Pelanggan;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class CustomerDocumentService
{
    /**
     * Simpan berkas ke MediaLibrary dalam format terenkripsi at-rest.
     *
     * @param  array<string, mixed>  $customProperties
     */
    public function storeEncryptedMedia(
        Pelanggan $pelanggan,
        UploadedFile $file,
        string $collection,
        array $customProperties = []
    ): Media {
        $rawContent = file_get_contents($file->getRealPath());
        if ($rawContent === false) {
            throw new RuntimeException("Gagal membaca berkas sumber: {$file->getClientOriginalName()}");
        }

        $encryptedPayload = Crypt::encryptString($rawContent);

        $tempPath = tempnam(sys_get_temp_dir(), 'enc_media_');
        if ($tempPath === false) {
            throw new RuntimeException('Gagal membuat berkas penampung sementara.');
        }

        file_put_contents($tempPath, $encryptedPayload);

        $customProperties['original_mime_type'] = $file->getClientMimeType() ?: $file->getMimeType();
        $customProperties['original_size'] = $file->getSize();

        try {
            $media = $pelanggan
                ->addMedia($tempPath)
                ->usingFileName($file->getClientOriginalName())
                ->withCustomProperties($customProperties)
                ->toMediaCollection($collection);

            return $media;
        } finally {
            if (file_exists($tempPath)) {
                @unlink($tempPath);
            }
        }
    }

    /**
     * Ambil isi biner berkas yang telah didekripsi.
     */
    public function getDecryptedContent(Media $media): string
    {
        $disk = Storage::disk($media->disk);
        $path = $media->getPathRelativeToRoot();

        if (! $disk->exists($path)) {
            throw new RuntimeException("Berkas media tidak ditemukan pada storage: {$media->file_name}");
        }

        $encryptedContent = $disk->get($path);
        if ($encryptedContent === null) {
            throw new RuntimeException("Gagal membaca berkas terenkripsi: {$media->file_name}");
        }

        try {
            return Crypt::decryptString($encryptedContent);
        } catch (\Throwable $e) {
            // Fallback jika berkas disimpan tanpa enkripsi sebelumnya
            return $encryptedContent;
        }
    }

    /**
     * Dekripsi foto KTP dan sematkan watermark dinamis untuk pencegahan kebocoran visual.
     */
    public function generateWatermarkedKtp(Media $media, User $staff): string
    {
        $binary = $this->getDecryptedContent($media);
        if (empty($binary)) {
            return '';
        }

        $image = @imagecreatefromstring($binary);
        if ($image === false) {
            return $binary;
        }

        if (function_exists('imagepalettetotruecolor') && ! imageistruecolor($image)) {
            imagepalettetotruecolor($image);
        }

        imagealphablending($image, true);
        imagesavealpha($image, true);

        $width = imagesx($image);
        $height = imagesy($image);

        // Watermark Text Information
        $watermarkLine1 = 'DOKUMEN RAHASIA • HANYA UNTUK VERIFIKASI GOBILLING';
        $watermarkLine2 = "Diakses Oleh: {$staff->name} ({$staff->email})";
        $watermarkLine3 = 'Waktu Akses: '.now()->translatedFormat('d F Y H:i:s').' WIB';

        // 1. Gambar pita / banner semi-transparan di bagian bawah
        $bannerHeight = 70;
        $bannerY = $height - $bannerHeight;
        $bannerBg = imagecolorallocatealpha($image, 15, 23, 42, 40); // Dark Slate Alpha
        if ($bannerBg !== false) {
            imagefilledrectangle($image, 0, $bannerY, $width, $height, $bannerBg);
        }

        $textColor = imagecolorallocate($image, 255, 255, 255);
        $shadowColor = imagecolorallocate($image, 0, 0, 0);

        if ($textColor !== false && $shadowColor !== false) {
            // Text Banner Bottom (Font size 4)
            imagestring($image, 4, 16, $bannerY + 8, $watermarkLine1, $shadowColor);
            imagestring($image, 4, 15, $bannerY + 7, $watermarkLine1, $textColor);

            imagestring($image, 3, 16, $bannerY + 28, "{$watermarkLine2} • {$watermarkLine3}", $shadowColor);
            imagestring($image, 3, 15, $bannerY + 27, "{$watermarkLine2} • {$watermarkLine3}", $textColor);
            imagestring($image, 2, 15, $bannerY + 48, 'DILARANG MENYEBARLUASKAN DOKUMEN IDENTITAS INI TANPA IZIN TERTULIS (UU PDP)', $textColor);
        }

        // 2. Watermark diagonal berulang di area tengah gambar
        $watermarkColor = imagecolorallocatealpha($image, 255, 255, 255, 80);
        if ($watermarkColor !== false) {
            $diagonalText = "GOBILLING VERIFICATION • {$staff->name} • ".now()->format('d/m/Y H:i');
            $stepY = max(120, (int) ($height / 4));
            for ($y = 80; $y < $height - 100; $y += $stepY) {
                for ($x = -50; $x < $width; $x += 350) {
                    imagestring($image, 5, $x + ($y % 100), $y, $diagonalText, $watermarkColor);
                }
            }
        }

        ob_start();
        imagejpeg($image, null, 90);
        $output = ob_get_clean();
        imagedestroy($image);

        return $output !== false ? $output : $binary;
    }
}
