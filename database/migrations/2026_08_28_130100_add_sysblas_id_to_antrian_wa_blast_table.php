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
        Schema::table('antrian_wa_blast', function (Blueprint $table) {
            $table->foreignId('sysblas_id')
                ->nullable()
                ->after('id')
                ->constrained('sysblas')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('antrian_wa_blast', function (Blueprint $table) {
            $table->dropForeign(['sysblas_id']);
            $table->dropColumn('sysblas_id');
        });
    }
};
