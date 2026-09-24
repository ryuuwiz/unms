<?php

namespace App\Livewire\Settings;

use App\Concerns\ProfileValidationRules;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\FileUploadConfiguration;
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
     * Unggah/ganti foto diri. Koleksi singleFile otomatis mengganti foto lama.
     */
    public function uploadFotoProfil(): void
    {
        $this->validate([
            'fotoProfil' => ['required', 'image', 'max:2048'],
        ], [
            'fotoProfil.image' => 'Foto harus berupa berkas gambar (jpg, png, webp).',
            'fotoProfil.max' => 'Ukuran foto maksimal 2 MB.',
        ]);

        $user = Auth::guard('web')->user();

        // Hapus eksplisit dulu -- jangan andalkan singleFile() otomatis, supaya tidak
        // pernah menyisakan foto lama walau ada kuirk state Auth::guard('web')->user() antar request.
        $user->clearMediaCollection('foto_profil');

        $user->addMediaFromDisk(
            FileUploadConfiguration::path($this->fotoProfil->getFilename(), false),
            FileUploadConfiguration::disk()
        )
            ->usingFileName($this->fotoProfil->getClientOriginalName())
            ->toMediaCollection('foto_profil');

        $this->fotoProfil = null;

        Flux::toast(variant: 'success', text: 'Foto profil berhasil diperbarui.');
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
