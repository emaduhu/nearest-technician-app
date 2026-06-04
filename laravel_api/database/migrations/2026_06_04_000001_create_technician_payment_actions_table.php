<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('technician_payment_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('technician_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('operator', 32)->index();
            $table->string('payer_phone', 32)->index();
            $table->unsignedInteger('amount');
            $table->string('currency', 3)->default('TZS');
            $table->string('status', 40)->default('initiated')->index();
            $table->string('order_reference')->nullable()->unique();
            $table->string('payment_id')->nullable();
            $table->json('response')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('requested_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('technician_payment_actions');
    }
};
