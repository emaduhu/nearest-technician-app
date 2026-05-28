<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('service_requests', 'client_rating')) {
                $table->unsignedTinyInteger('client_rating')->nullable()->after('response_message');
            }
            if (! Schema::hasColumn('service_requests', 'client_report')) {
                $table->text('client_report')->nullable()->after('client_rating');
            }
        });
    }

    public function down(): void
    {
        Schema::table('service_requests', function (Blueprint $table) {
            $table->dropColumn(['client_rating', 'client_report']);
        });
    }
};
