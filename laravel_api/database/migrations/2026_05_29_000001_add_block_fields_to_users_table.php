<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'blocked')) {
                $table->boolean('blocked')->default(false)->index()->after('last_location');
            }
            if (! Schema::hasColumn('users', 'blocked_reason')) {
                $table->text('blocked_reason')->nullable()->after('blocked');
            }
            if (! Schema::hasColumn('users', 'blocked_at')) {
                $table->timestamp('blocked_at')->nullable()->after('blocked_reason');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['blocked', 'blocked_reason', 'blocked_at']);
        });
    }
};
