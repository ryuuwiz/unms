<?php

namespace App\Http\Controllers;

use App\Models\Perusahaan;
use App\Models\RabItem;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;

class RabPdfController extends Controller
{
    /**
     * Cetak RAB Kantor satu bulan (?bulan=Y-m).
     */
    public function __invoke(Request $request): Response
    {
        $request->validate(['bulan' => ['required', 'date_format:Y-m']]);

        $periode = Carbon::createFromFormat('!Y-m', $request->string('bulan')->toString());
        $items = RabItem::whereDate('periode', $periode)->orderBy('id')->get();

        return Pdf::loadView('pdf.rab', [
            'perusahaan' => Perusahaan::default(),
            'periode' => $periode,
            'items' => $items,
            'grandTotal' => $items->sum(fn (RabItem $item) => $item->jumlah()),
        ])->setPaper('a4', 'portrait')->stream("RAB-Kantor-{$periode->format('Y-m')}.pdf");
    }
}
