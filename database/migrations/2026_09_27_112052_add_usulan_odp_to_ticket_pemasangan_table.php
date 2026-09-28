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
        Schema::table('ticket_pemasangan', function (Blueprint $table) {
            $table->foreignId('odp_usulan_id')->nullable()->after('odp_port_id')->constrained('odp')->nullOnDelete();
            $table->string('status_usulan_odp')->nullable()->after('odp_usulan_id');
            $table->boolean('tanpa_odp_dalam_jangkauan')->default(false)->after('status_usulan_odp');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ticket_pemasangan', function (Blueprint $table) {
            $table->dropConstrainedForeignId('odp_usulan_id');
            $table->dropColumn(['status_usulan_odp', 'tanpa_odp_dalam_jangkauan']);
        });
    }
};
