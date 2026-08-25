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
        Schema::create('ticket_divisi', function (Blueprint $table) {
            $table->foreignId('ticket_id')->constrained('ticket')->cascadeOnDelete();
            $table->string('divisi', 50); // admin, customer_service, sales, noc, teknisi

            $table->primary(['ticket_id', 'divisi']);
            $table->index('divisi');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ticket_divisi');
    }
};
