<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * `webhook_log.payload` menyimpan payload mentah gateway apa adanya -- termasuk email
     * pembayar, nomor Virtual Account, dan data pelanggan lain -- tanpa enkripsi, berbeda
     * dengan `pengaturan_gateway.credentials` yang sudah dienkripsi. Kolom diubah dari
     * `json` ke `longText` (ciphertext tidak muat di kolom json) dan baris yang sudah ada
     * dienkripsi ulang di migrasi ini (jumlahnya kecil karena aplikasi masih pre-launch).
     */
    public function up(): void
    {
        Schema::table('webhook_log', function (Blueprint $table) {
            $table->longText('payload_encrypted')->nullable()->after('payload');
        });

        DB::table('webhook_log')->orderBy('id')->select(['id', 'payload'])
            ->chunkById(200, function ($rows) {
                foreach ($rows as $row) {
                    if ($row->payload === null) {
                        continue;
                    }

                    DB::table('webhook_log')->where('id', $row->id)->update([
                        'payload_encrypted' => Crypt::encryptString($row->payload),
                    ]);
                }
            });

        Schema::table('webhook_log', function (Blueprint $table) {
            $table->dropColumn('payload');
        });

        Schema::table('webhook_log', function (Blueprint $table) {
            $table->renameColumn('payload_encrypted', 'payload');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('webhook_log', function (Blueprint $table) {
            $table->json('payload_plain')->nullable()->after('payload');
        });

        DB::table('webhook_log')->orderBy('id')->select(['id', 'payload'])
            ->chunkById(200, function ($rows) {
                foreach ($rows as $row) {
                    if ($row->payload === null) {
                        continue;
                    }

                    DB::table('webhook_log')->where('id', $row->id)->update([
                        'payload_plain' => Crypt::decryptString($row->payload),
                    ]);
                }
            });

        Schema::table('webhook_log', function (Blueprint $table) {
            $table->dropColumn('payload');
        });

        Schema::table('webhook_log', function (Blueprint $table) {
            $table->renameColumn('payload_plain', 'payload');
        });
    }
};
