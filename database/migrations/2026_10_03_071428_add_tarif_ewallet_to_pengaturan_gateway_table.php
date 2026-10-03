<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tarif GoPay dan ShopeePay sebagai Metode Bayar Gateway sendiri. Nilai awal dari transaksi
     * asli akun Xendit (GoPay 5%, ShopeePay 3,6%, keduanya belum termasuk PPN); admin
     * menyesuaikannya dengan tarif akun live.
     */
    public function up(): void
    {
        Schema::table('pengaturan_gateway', function (Blueprint $table) {
            $table->decimal('fee_gopay_nominal', 12, 2)->default(0)->after('fee_qris_termasuk_ppn');
            $table->decimal('fee_gopay_persen', 5, 2)->default(5.00)->after('fee_gopay_nominal');
            $table->boolean('fee_gopay_termasuk_ppn')->default(false)->after('fee_gopay_persen');
            $table->decimal('fee_shopeepay_nominal', 12, 2)->default(0)->after('fee_gopay_termasuk_ppn');
            $table->decimal('fee_shopeepay_persen', 5, 2)->default(3.60)->after('fee_shopeepay_nominal');
            $table->boolean('fee_shopeepay_termasuk_ppn')->default(false)->after('fee_shopeepay_persen');
        });
    }

    public function down(): void
    {
        Schema::table('pengaturan_gateway', function (Blueprint $table) {
            $table->dropColumn([
                'fee_gopay_nominal', 'fee_gopay_persen', 'fee_gopay_termasuk_ppn',
                'fee_shopeepay_nominal', 'fee_shopeepay_persen', 'fee_shopeepay_termasuk_ppn',
            ]);
        });
    }
};
