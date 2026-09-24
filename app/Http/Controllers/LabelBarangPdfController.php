<?php

namespace App\Http\Controllers;

use App\Models\UnitBarang;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Picqer\Barcode\BarcodeGeneratorPNG;

/**
 * Label barcode Code128 untuk Unit Barang (ADR-0057): per mutasi masuk (?mutasi=ID) atau
 * per unit terpilih (?unit[]=ID).
 */
class LabelBarangPdfController extends Controller
{
    private const MAKS_LABEL = 500;

    public function __invoke(Request $request): Response
    {
        $request->validate([
            'mutasi' => ['nullable', 'integer'],
            'unit' => ['nullable', 'array', 'max:'.self::MAKS_LABEL],
            'unit.*' => ['integer'],
        ]);

        $units = UnitBarang::query()
            ->with('jenisBarang')
            ->when($request->integer('mutasi'), fn ($q, int $mutasiId) => $q->whereHas('mutasi', fn ($m) => $m->whereKey($mutasiId)))
            ->when($request->array('unit'), fn ($q, array $ids) => $q->whereKey($ids))
            ->when(! $request->filled('mutasi') && ! $request->filled('unit'), fn ($q) => $q->whereRaw('1 = 0'))
            ->orderBy('kode')
            ->limit(self::MAKS_LABEL)
            ->get();

        abort_if($units->isEmpty(), 404, 'Tidak ada unit untuk dicetak.');

        $generator = new BarcodeGeneratorPNG;
        $labels = $units->map(fn (UnitBarang $unit) => [
            'kode' => $unit->kode,
            'nama' => $unit->jenisBarang->nama,
            'barcode' => base64_encode($generator->getBarcode($unit->kode, $generator::TYPE_CODE_128, 2, 50)),
        ]);

        return Pdf::loadView('pdf.label-barang', ['labels' => $labels])
            ->setPaper('a4', 'portrait')
            ->stream('Label-Barang-'.now()->format('YmdHis').'.pdf');
    }
}
