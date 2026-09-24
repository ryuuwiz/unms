<?php

namespace App\Enums\Barang;

enum StatusUnitBarang: string
{
    case DiGudang = 'di_gudang';
    case Terpasang = 'terpasang';
    case Dikembalikan = 'dikembalikan';
    case Rusak = 'rusak';

    public function label(): string
    {
        return match ($this) {
            self::DiGudang => 'Di Gudang',
            self::Terpasang => 'Terpasang',
            self::Dikembalikan => 'Dikembalikan (Siap Pakai)',
            self::Rusak => 'Rusak',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::DiGudang => 'green',
            self::Terpasang => 'blue',
            self::Dikembalikan => 'amber',
            self::Rusak => 'red',
        };
    }

    /**
     * Status unit yang terhitung stok gudang dan boleh dikeluarkan.
     *
     * @return list<self>
     */
    public static function tersedia(): array
    {
        return [self::DiGudang, self::Dikembalikan];
    }
}
