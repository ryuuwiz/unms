<?php

namespace App\Exports;

use App\Models\RabItem;
use Carbon\CarbonInterface;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class RabExport implements FromArray, ShouldAutoSize, WithHeadings
{
    public function __construct(public CarbonInterface $periode) {}

    /**
     * @return array<int, array<int, int|string|null>>
     */
    public function array(): array
    {
        $items = RabItem::whereDate('periode', $this->periode)->orderBy('id')->get();

        $rows = $items->values()->map(fn (RabItem $item, int $i) => [
            $i + 1,
            $item->uraian,
            $item->qty,
            $item->harga,
            $item->jumlah(),
            $item->labelDivisi(),
        ])->all();

        $rows[] = [null, 'TOTAL', null, null, $items->sum(fn (RabItem $item) => $item->jumlah()), null];

        return $rows;
    }

    public function headings(): array
    {
        return ['No', 'Uraian', 'Qty', 'Harga (Rp)', 'Jumlah (Rp)', 'Divisi'];
    }
}
