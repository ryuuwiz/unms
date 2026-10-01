<?php

namespace App\Livewire\Concerns;

use App\Models\PengaturanPrefixRegistrasi;
use App\Models\Perusahaan;
use App\Support\BrandPelanggan;
use Livewire\Features\SupportFileUploads\FileUploadConfiguration;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Spatie\MediaLibrary\HasMedia;

/**
 * Field identitas Aplikasi Pelanggan (nama pendek, warna utama, ikon aplikasi) yang sama di
 * form Prefix Registrasi dan Profil Perusahaan -- lihat ADR-0066. Komponen pemakainya wajib
 * memakai WithFileUploads.
 */
trait KelolaIdentitasAplikasi
{
    public string $nama_pendek = '';

    public string $warna_utama = '';

    public ?TemporaryUploadedFile $ikon_aplikasi = null;

    public ?string $existing_ikon_aplikasi_url = null;

    /**
     * Model brand yang sedang disunting; null bila belum tersimpan.
     */
    abstract protected function modelIdentitasAplikasi(): ?HasMedia;

    /**
     * @return array<string, array<int, string>>
     */
    protected function aturanIdentitasAplikasi(): array
    {
        return [
            'nama_pendek' => ['nullable', 'string', 'max:'.BrandPelanggan::PANJANG_NAMA_PENDEK],
            'warna_utama' => ['nullable', 'regex:'.BrandPelanggan::POLA_WARNA],
            'ikon_aplikasi' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048', 'dimensions:min_width=512,min_height=512,ratio=1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function pesanIdentitasAplikasi(): array
    {
        return [
            'warna_utama.regex' => 'Warna utama harus kode hex 6 digit, contoh #4f46e5.',
            'ikon_aplikasi.dimensions' => 'Ikon aplikasi harus persegi, minimal 512×512 piksel.',
        ];
    }

    /**
     * @return array{nama_pendek: ?string, warna_utama: ?string}
     */
    protected function dataIdentitasAplikasi(): array
    {
        return [
            'nama_pendek' => trim($this->nama_pendek) ?: null,
            'warna_utama' => strtolower(trim($this->warna_utama)) ?: null,
        ];
    }

    protected function isiIdentitasAplikasi(PengaturanPrefixRegistrasi|Perusahaan|null $model): void
    {
        $this->nama_pendek = $model->nama_pendek ?? '';
        $this->warna_utama = $model->warna_utama ?? '';
        $this->ikon_aplikasi = null;
        $this->existing_ikon_aplikasi_url = $model?->getFirstMediaUrl(BrandPelanggan::KOLEKSI_IKON) ?: null;
    }

    protected function simpanIkonAplikasi(HasMedia $model): void
    {
        if (! $this->ikon_aplikasi) {
            return;
        }

        $model->addMediaFromDisk(
            FileUploadConfiguration::path($this->ikon_aplikasi->getFilename(), false),
            FileUploadConfiguration::disk()
        )
            ->usingFileName($this->ikon_aplikasi->getClientOriginalName())
            ->toMediaCollection(BrandPelanggan::KOLEKSI_IKON);
    }

    public function hapusIkonAplikasi(): void
    {
        $this->modelIdentitasAplikasi()?->clearMediaCollection(BrandPelanggan::KOLEKSI_IKON);

        $this->ikon_aplikasi = null;
        $this->existing_ikon_aplikasi_url = null;
    }
}
