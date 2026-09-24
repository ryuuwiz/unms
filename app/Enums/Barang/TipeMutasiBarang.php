<?php

namespace App\Enums\Barang;

enum TipeMutasiBarang: string
{
    case SaldoAwal = 'saldo_awal';
    case Pembelian = 'pembelian';
    case Pengembalian = 'pengembalian';
    case Pemakaian = 'pemakaian';
    case Rusak = 'rusak';

    public function label(): string
    {
        return match ($this) {
            self::SaldoAwal => 'Saldo Awal',
            self::Pembelian => 'Pembelian / Masuk Baru',
            self::Pengembalian => 'Pengembalian dari Pelanggan',
            self::Pemakaian => 'Pemakaian Teknisi',
            self::Rusak => 'Rusak / Dihapusbukukan',
        };
    }

    public function arah(): ArahMutasiBarang
    {
        return match ($this) {
            self::SaldoAwal, self::Pembelian, self::Pengembalian => ArahMutasiBarang::Masuk,
            self::Pemakaian, self::Rusak => ArahMutasiBarang::Keluar,
        };
    }

    /**
     * @return list<self>
     */
    public static function masuk(): array
    {
        return [self::SaldoAwal, self::Pembelian, self::Pengembalian];
    }

    /**
     * @return list<self>
     */
    public static function keluar(): array
    {
        return [self::Pemakaian, self::Rusak];
    }
}
