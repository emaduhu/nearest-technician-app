<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('technicians', function (Blueprint $table) {
            $table->boolean('client_requests_blocked')->default(false)->index()->after('available');
            $table->text('client_requests_blocked_reason')->nullable()->after('client_requests_blocked');
        });
    }

    public function down(): void
    {
        Schema::table('technicians', function (Blueprint $table) {
            $table->dropColumn([
                'client_requests_blocked',
                'client_requests_blocked_reason',
            ]);
        });
    }
};
