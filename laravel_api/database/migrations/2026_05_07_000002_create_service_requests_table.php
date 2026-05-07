<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('technician_id')->constrained()->cascadeOnDelete();
            $table->string('skill')->index();
            $table->text('description')->nullable();
            $table->string('status', 32)->default('pending')->index();
            $table->decimal('client_latitude', 10, 7);
            $table->decimal('client_longitude', 10, 7);
            $table->decimal('technician_latitude_at_request', 10, 7)->nullable();
            $table->decimal('technician_longitude_at_request', 10, 7)->nullable();
            $table->decimal('distance_km', 8, 2)->nullable();
            $table->text('response_message')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_requests');
    }
};
