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
        Schema::create('ip_pools', function (Blueprint $table) {
            $table->id();
            $table->foreignId('router_id')->constrained('routers')->restrictOnDelete();
            $table->string('name')->unique();
            $table->string('ip_network');
            $table->unsignedTinyInteger('cidr');
            $table->string('ip_range_start')->nullable();
            $table->string('ip_range_end')->nullable();
            $table->decimal('queue_tx_mbps', 8, 2);
            $table->decimal('queue_rx_mbps', 8, 2);
            $table->unsignedTinyInteger('priority_tx')->default(8);
            $table->unsignedTinyInteger('priority_rx')->default(8);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ip_pools');
    }
};
