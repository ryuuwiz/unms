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
        Schema::create('router', function (Blueprint $table) {
            $table->id();
            $table->string('nama_router')->unique();
            $table->string('ip_address');
            $table->unsignedSmallInteger('port')->default(8728);
            $table->string('username');
            $table->text('password_terenkripsi')->comment('Encrypted via Laravel encrypted cast');
            $table->text('deskripsi')->nullable();
            $table->string('status_koneksi')->default('unknown')->index();
            $table->timestamp('last_sync_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('router');
    }
};
