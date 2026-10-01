<?php

namespace App\Http\Middleware;

use App\Models\AkunPelanggan;
use App\Support\BrandPelanggan;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ingat Brand Pelanggan yang sedang login di perangkat ini sebagai Petunjuk Brand, supaya halaman
 * Portal sebelum login berikutnya tampil dengan brand yang sama (CONTEXT.md "Petunjuk Brand").
 * Sesi impersonasi staf tidak menulis petunjuk.
 */
class CatatPetunjukBrand
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        /** @var AkunPelanggan|null $akun */
        $akun = Auth::guard('pelanggan')->user();
        if ($akun && ! app('impersonate')->isImpersonating()) {
            $response->headers->setCookie(
                BrandPelanggan::untukNoReg($akun->pelanggan?->no_reg)->cookiePetunjuk()
            );
        }

        return $response;
    }
}
