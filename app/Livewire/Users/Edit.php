<?php

namespace App\Livewire\Users;

use App\Enums\UserStatus;
use App\Models\User;
use App\Services\CustomerDocumentService;
use Flux\Flux;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use Spatie\Permission\Models\Role;

#[Layout('layouts.app')]
#[Title('Edit User')]
class Edit extends Component
{
    use WithFileUploads;

    #[Locked]
    public int $userId;

    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public string $role = '';

    public string $status = '';

    /** Holds the generated password after a reset, cleared on navigate. */
    public string $generatedPassword = '';

    /** @var mixed */
    public $fotoKtp = null;

    /** @var mixed */
    public $fotoProfil = null;

    public function mount(User $user): void
    {
        $this->userId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->phone = $user->phone ?? '';
        $this->role = $user->getRoleNames()->first() ?? '';
        $this->status = $user->status->value;
    }

    public function save(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', "unique:users,email,{$this->userId}"],
            'phone' => ['nullable', 'string', 'max:20'],
            'role' => ['required', 'string', 'exists:roles,name'],
        ]);

        $user = User::findOrFail($this->userId);
        $oldRole = $user->getRoleNames()->first() ?? '';

        $user->update([
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone ?: null,
        ]);

        if ($oldRole !== $this->role) {
            $user->syncRoles([$this->role]);
        }

        Flux::toast(variant: 'success', text: 'User berhasil diperbarui.');

        $this->redirectRoute('users.index', navigate: true);
    }

    /**
     * Unggah/ganti KTP staf atas nama user ini (onboarding oleh admin), disimpan terenkripsi.
     */
    public function uploadKtp(CustomerDocumentService $documentService): void
    {
        abort_unless(auth()->user()->can('pengguna.ubah'), 403);

        $this->validate([
            'fotoKtp' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ], [
            'fotoKtp.image' => 'KTP harus berupa berkas gambar (jpg, png, webp).',
            'fotoKtp.max' => 'Ukuran foto KTP maksimal 2 MB.',
        ]);

        $documentService->storeEncryptedMedia(User::findOrFail($this->userId), $this->fotoKtp, 'ktp');
        $this->fotoKtp = null;

        Flux::toast(variant: 'success', text: 'KTP berhasil diunggah.');
    }

    /**
     * Unggah/ganti foto profil atas nama user ini oleh admin, begitu berkas dipilih atau diseret.
     */
    public function updatedFotoProfil(): void
    {
        abort_unless(auth()->user()->can('pengguna.ubah'), 403);

        $this->validate([
            'fotoProfil' => ['required', 'image', 'max:2048'],
        ], [
            'fotoProfil.image' => 'Foto harus berupa berkas gambar (jpg, png, webp).',
            'fotoProfil.max' => 'Ukuran foto maksimal 2 MB.',
        ]);

        $user = User::findOrFail($this->userId);
        $user->gantiFotoProfil($this->fotoProfil);
        $this->fotoProfil = null;
        $this->logFotoProfil($user, 'ganti_foto_profil', "Mengganti foto profil {$user->name}");

        Flux::toast(variant: 'success', text: 'Foto profil berhasil diperbarui.');
    }

    /**
     * Hapus foto profil user ini oleh admin, avatar kembali ke inisial.
     */
    public function hapusFotoProfil(): void
    {
        abort_unless(auth()->user()->can('pengguna.ubah'), 403);

        $user = User::findOrFail($this->userId);
        $user->clearMediaCollection('foto_profil');
        $this->logFotoProfil($user, 'hapus_foto_profil', "Menghapus foto profil {$user->name}");

        Flux::toast(variant: 'success', text: 'Foto profil dihapus.');
    }

    /**
     * Catat perubahan foto profil Pengguna lain; perubahan foto sendiri tidak dicatat.
     */
    private function logFotoProfil(User $user, string $action, string $description): void
    {
        if ($user->is(auth()->user())) {
            return;
        }

        activity()
            ->performedOn($user)
            ->causedBy(auth()->user())
            ->withProperties(['action' => $action])
            ->log($description);
    }

    public function resetPassword(): void
    {
        $user = User::findOrFail($this->userId);

        $newPassword = Str::random(12);

        $user->update(['password' => $newPassword]);

        $this->generatedPassword = $newPassword;

        Flux::toast(variant: 'success', text: 'Password berhasil direset.');
    }

    public function toggleStatus(): void
    {
        $user = User::findOrFail($this->userId);
        $newStatus = $user->status === UserStatus::Active
            ? UserStatus::Inactive
            : UserStatus::Active;

        // FR-1.8 / Q3 lockout guard: block if this would leave zero active super_admins
        if ($newStatus === UserStatus::Inactive) {
            $isLastActiveSuperAdmin = $user->hasRole('super_admin')
                && User::active()
                    ->whereHas('roles', fn ($q) => $q->where('name', 'super_admin'))
                    ->count() === 1;

            if ($isLastActiveSuperAdmin) {
                Flux::toast(
                    variant: 'danger',
                    text: 'Tidak dapat menonaktifkan satu-satunya super admin yang aktif.'
                );

                return;
            }
        }

        $user->update(['status' => $newStatus]);
        $this->status = $newStatus->value;

        $label = $newStatus === UserStatus::Active ? 'diaktifkan' : 'dinonaktifkan';
        Flux::toast(variant: 'success', text: "User berhasil {$label}.");
    }

    public function render(): View
    {
        $user = User::findOrFail($this->userId);

        /**
         * Group permissions by module derived from the permission name prefix.
         * e.g. "view_users" and "manage_users" → group "users"
         *
         * @var array<string, array<string>> $groupedPermissions
         */
        $groupedPermissions = $user->getAllPermissions()
            ->groupBy(fn ($perm) => explode('.', $perm->name)[0])
            ->map(fn ($perms) => $perms->pluck('name')->all())
            ->toArray();

        return view('livewire.users.edit', [
            'roles' => Role::orderBy('name')->get(),
            'statuses' => UserStatus::cases(),
            'user' => $user,
            'groupedPermissions' => $groupedPermissions,
        ]);
    }
}
