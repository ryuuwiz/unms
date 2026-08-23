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
        Schema::table('ip_pool', function (Blueprint $table) {
            $table->timestamp('applied_to_router_at')->nullable()->after('priority_rx');
            $table->string('sync_status')->nullable()->after('applied_to_router_at')->index();
            $table->text('last_sync_error')->nullable()->after('sync_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ip_pool', function (Blueprint $table) {
            $table->dropColumn([
                'applied_to_router_at',
                'sync_status',
                'last_sync_error',
            ]);
        });
    }
};
