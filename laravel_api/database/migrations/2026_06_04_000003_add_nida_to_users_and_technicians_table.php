<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'nida')) {
                $table->string('nida', 20)->nullable()->unique()->after('name');
            }
        });

        Schema::table('technicians', function (Blueprint $table): void {
            if (! Schema::hasColumn('technicians', 'nida')) {
                $table->string('nida', 20)->nullable()->unique()->after('name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('technicians', function (Blueprint $table): void {
            if (Schema::hasColumn('technicians', 'nida')) {
                $table->dropUnique('technicians_nida_unique');
                $table->dropColumn('nida');
            }
        });

        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'nida')) {
                $table->dropUnique('users_nida_unique');
                $table->dropColumn('nida');
            }
        });
    }
};
