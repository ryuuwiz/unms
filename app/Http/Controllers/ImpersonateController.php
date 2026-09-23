<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
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

        // Portal Pelanggan bisa hidup di domain terpisah dari domain staf (lihat ADR-0049).
        // Login guard `pelanggan` TIDAK BOLEH dilakukan di sini kalau begitu -- cookie sesi
        // yang dihasilkan akan ter-scope ke domain staf saat ini, bukan domain Portal, dan
        // navigasi internal Portal (yang memakai route('portal.*') secara global) akan langsung
        // "logout" di klik pertama. Login sungguhan dilakukan di consumePortalHandoff() lewat
        // tautan bertanda tangan berumur pendek, di request yang host-nya memang domain Portal.
        $portalDomain = config('app.portal_domain');
        if ($guardName === 'pelanggan' && $portalDomain && $portalDomain !== $request->getHost()) {
            $handoffUrl = URL::temporarySignedRoute('portal.impersonate.consume', now()->addSeconds(60), [
                'impersonator' => $currentUser->getKey(),
                'pelanggan' => $userToImpersonate->getKey(),
            ]);

            return redirect()->away($handoffUrl);
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
     * Menyelesaikan handoff impersonasi staf->pelanggan lintas domain (lihat take()):
     * login guard `pelanggan` sungguhan terjadi di sini, pada request yang host-nya
     * domain Portal, supaya cookie sesi ter-scope dengan benar ke domain itu.
     */
    public function consumePortalHandoff(Request $request): RedirectResponse
    {
        abort_unless($request->hasValidSignature(), 403);

        $currentUser = User::findOrFail($request->integer('impersonator'));

        if (! method_exists($currentUser, 'canImpersonate') || ! $currentUser->canImpersonate()) {
            abort(403, 'Akses ditolak. Hanya Super Admin aktif yang dapat melakukan impersonasi.');
        }

        if ($this->manager->isImpersonating()) {
            abort(403, 'Sesi impersonasi sedang aktif. Akhiri sesi saat ini terlebih dahulu.');
        }

        $userToImpersonate = $this->manager->findUserById($request->integer('pelanggan'), 'pelanggan');

        if (! method_exists($userToImpersonate, 'canBeImpersonated') || ! $userToImpersonate->canBeImpersonated()) {
            abort(403, 'Akun ini tidak dapat diimpersonasi.');
        }

        if ($this->manager->take($currentUser, $userToImpersonate, 'pelanggan')) {
            return redirect()->route('portal.dashboard');
        }

        return redirect()->route('portal.login')->with('error', 'Gagal memulai sesi impersonasi.');
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
            // Sesi impersonasi pelanggan mungkin baru saja berjalan di domain Portal (lihat
            // consumePortalHandoff()). route() untuk rute tanpa domain constraint memakai host
            // request SAAT INI sebagai default, jadi harus dipaksa balik ke domain staf secara
            // eksplisit -- kalau tidak, staf akan "terdampar" melihat halaman staf di bawah
            // domain Portal.
            return redirect(rtrim(config('app.url'), '/').route('pelanggan.index', absolute: false));
        }

        return redirect()->route('users.index');
    }
}
