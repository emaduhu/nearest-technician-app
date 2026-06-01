<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'phone_verified_at')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->timestamp('phone_verified_at')->nullable()->after('phone');
            });
        }

        if (! Schema::hasColumn('users', 'firebase_phone_uid')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->string('firebase_phone_uid')->nullable()->after('phone_verified_at');
            });
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $columns = array_values(array_filter([
                Schema::hasColumn('users', 'phone_verified_at') ? 'phone_verified_at' : null,
                Schema::hasColumn('users', 'firebase_phone_uid') ? 'firebase_phone_uid' : null,
            ]));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
