<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Perusahaan;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class InvoicePdfController extends Controller
{
    /**
     * Download or stream PDF invoice.
     */
    public function cetak(Invoice $invoice): Response
    {
        Gate::authorize('cetak', $invoice);

        $invoice->load(['pelanggan', 'layananPelanggan.paketLayanan', 'layananPelanggan.router', 'promo', 'pembayarans']);
        $perusahaan = Perusahaan::default();

        $pdf = Pdf::loadView('pdf.invoice', [
            'invoice' => $invoice,
            'perusahaan' => $perusahaan,
        ])->setPaper('a4', 'portrait');

        return $pdf->stream("Invoice-{$invoice->no_invoice}.pdf");
    }
}
