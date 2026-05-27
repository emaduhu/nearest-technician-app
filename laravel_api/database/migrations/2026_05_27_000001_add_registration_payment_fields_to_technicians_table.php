<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('technicians', function (Blueprint $table) {
            $table->unsignedInteger('registration_fee_amount')->default(5000)->after('rating');
            $table->string('registration_fee_currency', 3)->default('TZS')->after('registration_fee_amount');
            $table->string('registration_payment_status')->default('not_requested')->index()->after('registration_fee_currency');
            $table->string('registration_payment_order_reference')->nullable()->unique()->after('registration_payment_status');
            $table->string('registration_payment_id')->nullable()->after('registration_payment_order_reference');
            $table->json('registration_payment_response')->nullable()->after('registration_payment_id');
            $table->timestamp('registration_payment_requested_at')->nullable()->after('registration_payment_response');
        });
    }

    public function down(): void
    {
        Schema::table('technicians', function (Blueprint $table) {
            $table->dropUnique(['registration_payment_order_reference']);
            $table->dropColumn([
                'registration_fee_amount',
                'registration_fee_currency',
                'registration_payment_status',
                'registration_payment_order_reference',
                'registration_payment_id',
                'registration_payment_response',
                'registration_payment_requested_at',
            ]);
        });
    }
};
