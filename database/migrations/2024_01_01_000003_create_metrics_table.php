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
        Schema::create('metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained()->onDelete('cascade');
            $table->timestamp('timestamp');
            $table->string('measurement');
            $table->string('field_key');
            $table->decimal('field_value', 20, 6);
            $table->json('tags')->nullable();
            $table->string('environment');
            $table->json('metadata')->nullable();
            $table->bigInteger('influxdb_timestamp')->nullable();
            $table->timestamps();

            $table->index(['agent_id', 'timestamp']);
            $table->index(['environment', 'measurement', 'timestamp']);
            $table->index(['measurement', 'field_key', 'timestamp']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('metrics');
    }
};