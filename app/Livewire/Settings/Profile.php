<?php

namespace App\Livewire\Settings;

use App\Concerns\ProfileValidationRules;
use App\Services\CustomerDocumentService;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Title('Profile settings')]
class Profile extends Component
{
    use ProfileValidationRules, WithFileUploads;

    public string $name = '';

    public string $email = '';

    public string $phone = '';

    /** @var mixed */
    public $fotoProfil = null;

    /** @var mixed */
    public $fotoKtp = null;

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->name = Auth::guard('web')->user()->name;
        $this->email = Auth::guard('web')->user()->email;
        $this->phone = Auth::guard('web')->user()->phone ?? '';
    }

    /**
     * Unggah/ganti foto diri begitu berkas dipilih atau diseret (hook updated Livewire).
     */
    public function updatedFotoProfil(): void
    {
        $this->validate([
            'fotoProfil' => ['required', 'image', 'max:2048'],
        ], [
            'fotoProfil.image' => 'Foto harus berupa berkas gambar (jpg, png, webp).',
            'fotoProfil.max' => 'Ukuran foto maksimal 2 MB.',
        ]);

        Auth::guard('web')->user()->gantiFotoProfil($this->fotoProfil);
        $this->fotoProfil = null;

        Flux::toast(variant: 'success', text: 'Foto profil berhasil diperbarui.');
    }

    /**
     * Hapus foto diri, avatar kembali ke inisial.
     */
    public function hapusFotoProfil(): void
    {
        Auth::guard('web')->user()->clearMediaCollection('foto_profil');

        Flux::toast(variant: 'success', text: 'Foto profil dihapus.');
    }

    /**
     * Unggah/ganti KTP sendiri, disimpan terenkripsi di disk privat (singleFile mengganti yang lama).
     */
    public function uploadKtp(CustomerDocumentService $documentService): void
    {
        $this->validate([
            'fotoKtp' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ], [
            'fotoKtp.image' => 'KTP harus berupa berkas gambar (jpg, png, webp).',
            'fotoKtp.max' => 'Ukuran foto KTP maksimal 2 MB.',
        ]);

        $documentService->storeEncryptedMedia(Auth::guard('web')->user(), $this->fotoKtp, 'ktp');
        $this->fotoKtp = null;

        Flux::toast(variant: 'success', text: 'KTP berhasil diunggah.');
    }

    /**
     * Update the profile information for the currently authenticated user.
     */
    public function updateProfileInformation(): void
    {
        $user = Auth::guard('web')->user();

        $validated = $this->validate($this->profileRules($user->id));

        $user->fill($validated);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        Flux::toast(variant: 'success', text: __('Profile updated.'));
    }
}
