<?php

namespace App\Http\Controllers;

use App\Enums\StatusOdpPort;
use App\Models\LayananPelanggan;
use App\Models\Odp;
use App\Models\OdpPort;
use App\Models\Ticket;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

/**
 * Label Port: stiker thermal 50×30 mm, satu label per halaman -- lihat CONTEXT.md "Label Port".
 */
class LabelPortPdfController extends Controller
{
    /** Ukuran kertas 50×30 mm dalam point PDF (1 mm = 72/25.4 pt). */
    private const KERTAS = [0, 0, 141.73, 85.04];

    public function tiket(Ticket $ticket): Response
    {
        Gate::authorize('view', $ticket);

        $port = $ticket->portUntukLabel();
        abort_if($port === null, 404, 'Tiket ini tidak punya port untuk dicetak labelnya.');

        $port->loadMissing('odp');

        return $this->cetak(collect([$this->label($port, $ticket->layananPelanggan)]), "Label-Port-{$ticket->nomor_ticket}");
    }

    /**
     * Cetak massal untuk melabeli ulang ODP: semua port Terpakai, atau satu port lewat ?port=ID.
     */
    public function odp(Request $request, Odp $odp): Response
    {
        $request->validate(['port' => ['nullable', 'integer']]);

        $labels = $odp->ports()
            ->where('status', StatusOdpPort::Terpakai)
            ->whereNotNull('layanan_pelanggan_id')
            ->when($request->integer('port'), fn ($query, int $portId) => $query->whereKey($portId))
            ->with('layananPelanggan.pelanggan')
            ->orderBy('nomor_port')
            ->get()
            ->map(fn (OdpPort $port): array => $this->label($port->setRelation('odp', $odp), $port->layananPelanggan));

        abort_if($labels->isEmpty(), 404, 'Tidak ada port Terpakai untuk dicetak labelnya.');

        return $this->cetak($labels, "Label-Port-{$odp->nama_odp}");
    }

    /**
     * @return array{odp: string, port: int, nama: string, no_reg: string, site_id: string}
     */
    private function label(OdpPort $port, LayananPelanggan $layanan): array
    {
        return [
            'odp' => $port->odp->nama_odp,
            'port' => $port->nomor_port,
            'nama' => $layanan->pelanggan->namaLengkap(),
            'no_reg' => $layanan->pelanggan->no_reg,
            'site_id' => $layanan->site_id,
        ];
    }

    /**
     * @param  Collection<int, array{odp: string, port: int, nama: string, no_reg: string, site_id: string}>  $labels
     */
    private function cetak(Collection $labels, string $namaBerkas): Response
    {
        return Pdf::loadView('pdf.label-port', ['labels' => $labels->all()])
            ->setPaper(self::KERTAS)
            ->stream("{$namaBerkas}.pdf");
    }
}
