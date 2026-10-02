<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Token Tautan Tagihan (ADR-0067): dibuat saat pertama kali dibutuhkan, diganti untuk
 * mencabut tautan lama.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice', function (Blueprint $table) {
            $table->string('token_tautan', 32)->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('invoice', function (Blueprint $table) {
            $table->dropUnique(['token_tautan']);
            $table->dropColumn('token_tautan');
        });
    }
};
