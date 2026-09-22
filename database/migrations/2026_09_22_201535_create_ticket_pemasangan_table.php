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
        Schema::create('ticket_pemasangan', function (Blueprint $table) {
            $table->foreignId('ticket_id')->primary()->constrained('ticket')->cascadeOnDelete();
            $table->foreignId('odp_port_id')->nullable()->constrained('odp_port')->nullOnDelete();
            $table->timestamp('diaktivasi_pada')->nullable();
            $table->foreignId('diaktivasi_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ticket_pemasangan');
    }
};
