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
        Schema::table('invoice', function (Blueprint $table) {
            $table->decimal('jumlah_tunggakan', 15, 2)->default(0)->after('jumlah_setelah_promo');
            $table->foreignId('digabung_ke_invoice_id')->nullable()->after('status')
                ->constrained('invoice')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoice', function (Blueprint $table) {
            $table->dropConstrainedForeignId('digabung_ke_invoice_id');
            $table->dropColumn('jumlah_tunggakan');
        });
    }
};
