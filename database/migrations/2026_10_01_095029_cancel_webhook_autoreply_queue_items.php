<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('antrian_wa_blast')
            ->where('jenis', 'webhook_autoreply')
            ->where('status', '!=', 'terkirim')
            ->update([
                'status' => 'gagal',
                'pesan_error' => 'Webhook auto-reply dinonaktifkan.',
                'updated_at' => now(),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Queue items are intentionally not restored after cancellation.
    }
};
