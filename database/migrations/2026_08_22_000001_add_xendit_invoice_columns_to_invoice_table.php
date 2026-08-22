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
        Schema::table('invoice', function (Blueprint $table) {
            $table->string('xendit_invoice_id', 100)->nullable()->after('metode_pembayaran');
            $table->text('xendit_invoice_url')->nullable()->after('xendit_invoice_id');
            $table->string('xendit_status', 30)->nullable()->after('xendit_invoice_url');
            $table->timestamp('xendit_expired_at')->nullable()->after('xendit_status');

            $table->index(['xendit_status', 'xendit_expired_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoice', function (Blueprint $table) {
            $table->dropIndex(['xendit_status', 'xendit_expired_at']);
            $table->dropColumn([
                'xendit_invoice_id',
                'xendit_invoice_url',
                'xendit_status',
                'xendit_expired_at',
            ]);
        });
    }
};
