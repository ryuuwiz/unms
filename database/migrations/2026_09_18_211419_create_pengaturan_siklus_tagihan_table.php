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
        Schema::create('pengaturan_siklus_tagihan', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('hari_jatuh_tempo')->default(10);
            $table->unsignedTinyInteger('hari_terbit_invoice')->default(24);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pengaturan_siklus_tagihan');
    }
};
