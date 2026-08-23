<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('layanan_pelanggan', function (Blueprint $table) {
            $table->timestamp('terprovisi_pada')->nullable()->after('tanggal_expired');
            $table->string('provisioning_status')->default('pending')->after('terprovisi_pada')->index();
            $table->text('last_provisioning_error')->nullable()->after('provisioning_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('layanan_pelanggan', function (Blueprint $table) {
            $table->dropColumn([
                'terprovisi_pada',
                'provisioning_status',
                'last_provisioning_error',
            ]);
        });
    }
};
