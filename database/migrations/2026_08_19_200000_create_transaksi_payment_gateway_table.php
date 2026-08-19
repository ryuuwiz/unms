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
        Schema::create('transaksi_payment_gateway', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoice')->cascadeOnDelete();
            $table->string('gateway', 30)->default('xendit');
            $table->string('external_id', 100)->unique();
            $table->string('xendit_reference_id', 100)->nullable();
            $table->string('channel', 30); // virtual_account, qris, ewallet, retail_outlet
            $table->string('channel_detail', 50)->nullable(); // bca, bni, bri, mandiri, permata, dll
            $table->string('nomor_pembayaran', 100)->nullable(); // No VA
            $table->text('qr_string')->nullable(); // QR string
            $table->decimal('total_tagihan', 12, 2);
            $table->decimal('fee_gateway', 12, 2)->default(0);
            $table->string('status', 30)->default('pending'); // pending, paid, expired, failed
            $table->timestamp('expired_at')->nullable();
            $table->json('payload_request')->nullable();
            $table->json('payload_response')->nullable();
            $table->timestamps();

            $table->index(['status', 'expired_at']);
            $table->index(['channel', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transaksi_payment_gateway');
    }
};
