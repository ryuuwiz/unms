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
        Schema::table('sysblas', function (Blueprint $table) {
            $table->string('username')->nullable()->after('provider')->comment('Username (e.g. for WAHA API)');
            $table->string('password')->nullable()->after('username')->comment('Password (e.g. for WAHA API)');
            // Change token to nullable since WAHA might not use it
            $table->string('api_token')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sysblas', function (Blueprint $table) {
            $table->dropColumn(['username', 'password']);
            $table->string('api_token')->nullable(false)->change();
        });
    }
};
