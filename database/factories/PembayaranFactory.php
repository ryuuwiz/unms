<?php

namespace Database\Factories;

use App\Enums\MetodePembayaran;
use App\Models\Invoice;
use App\Models\Pembayaran;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Pembayaran>
 */
class PembayaranFactory extends Factory
{
    protected $model = Pembayaran::class;

    public function definition(): array
    {
        return [
            'invoice_id' => Invoice::factory(),
            'metode' => MetodePembayaran::ManualAdmin,
            'referensi_transaksi' => 'TRX-'.strtoupper($this->faker->bothify('##??##')),
            'jumlah_dibayar' => 250000,
            'dibayar_pada' => now(),
            'bukti_pembayaran_path' => null,
            'dicatat_oleh' => null,
            'catatan' => 'Pembayaran manual kasir',
        ];
    }
}
