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
        Schema::create('mikrotik_job_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('router_id')->constrained('router')->cascadeOnDelete();
            $table->foreignId('layanan_pelanggan_id')->nullable()->constrained('layanan_pelanggan')->nullOnDelete();
            $table->foreignId('ip_pool_id')->nullable()->constrained('ip_pool')->nullOnDelete();
            $table->string('job_type')->index();
            $table->string('status')->default('pending')->index();
            $table->unsignedTinyInteger('attempt_count')->default(1);
            $table->text('error_message')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mikrotik_job_logs');
    }
};
