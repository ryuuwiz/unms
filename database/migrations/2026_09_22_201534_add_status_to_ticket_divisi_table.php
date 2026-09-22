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
        Schema::table('ticket_divisi', function (Blueprint $table) {
            $table->string('status')->default('belum')->after('divisi')
                ->comment('Status sign-off divisi ini pada tiket: belum, progress, selesai. Dipakai penuh oleh Ticket Pemasangan.');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ticket_divisi', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
