<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Lab404\Impersonate\Services\ImpersonateManager;

class ImpersonateController extends Controller
{
    public function __construct(
        protected ImpersonateManager $manager
    ) {}

    /**
     * Memulai sesi impersonasi ke akun pengguna atau pelanggan lain.
     */
    public function take(Request $request, int|string $id, ?string $guardName = null): RedirectResponse
    {
        $guardName = $guardName ?? $this->manager->getDefaultSessionGuard();

        $currentUser = $request->user();

        if (! $currentUser || ! method_exists($currentUser, 'canImpersonate') || ! $currentUser->canImpersonate()) {
            abort(403, 'Akses ditolak. Hanya Super Admin aktif yang dapat melakukan impersonasi.');
        }

        if ($this->manager->isImpersonating()) {
            abort(403, 'Sesi impersonasi sedang aktif. Akhiri sesi saat ini terlebih dahulu.');
        }

        if ($id == $currentUser->getAuthIdentifier() && ($this->manager->getCurrentAuthGuardName() === $guardName)) {
            abort(403, 'Tidak dapat mengimpersonasi akun Anda sendiri.');
        }

        $userToImpersonate = $this->manager->findUserById($id, $guardName);

        if (! method_exists($userToImpersonate, 'canBeImpersonated') || ! $userToImpersonate->canBeImpersonated()) {
            abort(403, 'Akun ini tidak dapat diimpersonasi.');
        }

        if ($this->manager->take($currentUser, $userToImpersonate, $guardName)) {
            if ($guardName === 'pelanggan') {
                return redirect()->route('portal.dashboard');
            }

            return redirect()->route('dashboard');
        }

        return redirect()->back()->with('error', 'Gagal memulai sesi impersonasi.');
    }

    /**
     * Mengakhiri sesi impersonasi dan kembali ke akun Super Admin asli.
     */
    public function leave(): RedirectResponse
    {
        if (! $this->manager->isImpersonating()) {
            abort(403, 'Tidak ada sesi impersonasi yang aktif.');
        }

        $impersonatedGuard = $this->manager->getImpersonatorGuardUsingName();

        $this->manager->leave();

        if ($impersonatedGuard === 'pelanggan') {
            return redirect()->route('pelanggan.index');
        }

        return redirect()->route('users.index');
    }
}
