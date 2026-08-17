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
        Schema::create('ip_pool', function (Blueprint $table) {
            $table->id();
            $table->foreignId('router_id')->constrained('router')->cascadeOnDelete();
            $table->string('nama_pool')->unique();
            $table->string('ip_network');
            $table->unsignedTinyInteger('cidr');
            $table->string('rentang_ip_awal');
            $table->string('rentang_ip_akhir');
            $table->unsignedTinyInteger('priority_tx')->default(8);
            $table->unsignedTinyInteger('priority_rx')->default(8);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ip_pool');
    }
};
