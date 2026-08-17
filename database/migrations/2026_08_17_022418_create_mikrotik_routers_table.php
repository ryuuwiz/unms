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
        Schema::create('routers', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('ip_address');
            $table->integer('api_port')->default(8728);
            $table->string('username');
            $table->string('password')->nullable();
            $table->text('description')->nullable();
            $table->enum('status', ['online', 'offline', 'unknown'])->default('unknown');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('routers');
    }
};
