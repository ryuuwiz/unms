<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rab_items', function (Blueprint $table) {
            $table->id();
            $table->date('periode')->index();
            $table->string('uraian');
            $table->unsignedInteger('qty');
            $table->unsignedBigInteger('harga');
            $table->string('divisi')->nullable();
            $table->string('divisi_lainnya')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rab_items');
    }
};
