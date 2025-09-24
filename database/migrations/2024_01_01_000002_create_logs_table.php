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
        Schema::create('logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained()->onDelete('cascade');
            $table->timestamp('timestamp');
            $table->enum('level', ['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug']);
            $table->text('message');
            $table->enum('source', ['syslog', 'journald', 'nginx', 'apache', 'application', 'custom']);
            $table->string('facility')->nullable();
            $table->string('hostname');
            $table->string('process_name')->nullable();
            $table->integer('process_id')->nullable();
            $table->string('environment');
            $table->json('tags')->nullable();
            $table->json('metadata')->nullable();
            $table->string('elasticsearch_id')->nullable()->unique();
            $table->timestamps();

            $table->index(['agent_id', 'timestamp']);
            $table->index(['environment', 'level', 'timestamp']);
            $table->index(['source', 'timestamp']);
            $table->index('hostname');
            $table->fullText(['message']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('logs');
    }
};