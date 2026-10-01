<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Perusahaan;
use App\Services\Billing\InvoiceCetak;
use App\Support\BrandPelanggan;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class InvoicePdfController extends Controller
{
    /**
     * Download or stream PDF invoice.
     */
    public function cetak(Invoice $invoice): Response
    {
        $authorized = false;

        // 1. Staff User Check
        if (Auth::guard('web')->check()) {
            $user = Auth::guard('web')->user();
            if ($user && ($user->can('invoice.cetak') || $user->hasRole('super_admin'))) {
                $authorized = true;
            }
        }

        // 2. Customer Portal Check (Can only print own invoice)
        if (! $authorized && Auth::guard('pelanggan')->check()) {
            $akunPelanggan = Auth::guard('pelanggan')->user();
            if ($akunPelanggan && (int) $invoice->pelanggan_id === (int) $akunPelanggan->pelanggan_id) {
                $authorized = true;
            }
        }

        abort_unless($authorized, 403, 'Anda tidak memiliki akses untuk mencetak invoice ini.');

        $invoice->load(['pelanggan', 'layananPelanggan.paketLayanan', 'promo', 'pembayarans']);
        $brand = BrandPelanggan::untukNoReg($invoice->pelanggan?->no_reg);

        $pdf = Pdf::loadView('pdf.invoice', [
            'invoice' => $invoice,
            'perusahaan' => Perusahaan::default(),
            'namaBrand' => $brand->nama(),
            'logoBase64' => $brand->logoBase64(),
            'cetak' => InvoiceCetak::dari($invoice),
        ])->setPaper('a4', 'portrait');

        return $pdf->stream("Invoice-{$invoice->no_invoice}.pdf");
    }
}
