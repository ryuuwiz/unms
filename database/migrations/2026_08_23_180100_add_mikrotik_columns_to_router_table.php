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
        Schema::table('router', function (Blueprint $table) {
            $table->timestamp('last_ping_at')->nullable()->after('last_sync_at');
            $table->string('last_ping_status')->nullable()->after('last_ping_at');
            $table->string('last_ping_message')->nullable()->after('last_ping_status');
            $table->unsignedTinyInteger('cpu_load')->nullable()->after('last_ping_message');
            $table->unsignedBigInteger('memory_free')->nullable()->after('cpu_load');
            $table->unsignedBigInteger('memory_total')->nullable()->after('memory_free');
            $table->string('uptime')->nullable()->after('memory_total');
            $table->string('board_name')->nullable()->after('uptime');
            $table->string('routeros_version')->nullable()->after('board_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('router', function (Blueprint $table) {
            $table->dropColumn([
                'last_ping_at',
                'last_ping_status',
                'last_ping_message',
                'cpu_load',
                'memory_free',
                'memory_total',
                'uptime',
                'board_name',
                'routeros_version',
            ]);
        });
    }
};
