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
        Schema::create('pengaturan_gateway', function (Blueprint $table) {
            $table->id();
            $table->string('gateway', 30)->default('xendit')->unique();
            $table->decimal('fee_va_nominal', 12, 2)->default(0);
            $table->decimal('fee_qris_persen', 5, 2)->default(0); // misal 0.70%
            $table->decimal('fee_qris_nominal', 12, 2)->default(0); // flat fee opsional
            $table->boolean('bebankan_ke_pelanggan')->default(false); // jika false -> disubsidi ISP
            $table->boolean('is_active')->default(true);
            $table->boolean('sandbox_mode')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pengaturan_gateway');
    }
};
