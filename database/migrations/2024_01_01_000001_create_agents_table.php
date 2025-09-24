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
        Schema::create('agents', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('hostname');
            $table->ipAddress('ip_address');
            $table->string('environment')->default('production');
            $table->enum('status', ['active', 'inactive', 'error', 'pending'])->default('pending');
            $table->timestamp('last_heartbeat')->nullable();
            $table->json('telegraf_config')->nullable();
            $table->text('tls_certificate')->nullable();
            $table->string('api_token', 64)->unique();
            $table->string('version')->nullable();
            $table->json('os_info')->nullable();
            $table->string('architecture')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['environment', 'status']);
            $table->index('last_heartbeat');
            $table->index('hostname');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agents');
    }
};