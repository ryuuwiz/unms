<?php

namespace App\Http\Controllers;

use App\Models\Barang;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;

class BarangLabelController extends Controller
{
    /**
     * Tampilkan halaman cetak label barcode untuk satu barang.
     */
    public function cetak(Barang $barang): View
    {
        Gate::authorize('view', $barang);

        return view('barang.label', [
            'barang' => $barang,
        ]);
    }
}
