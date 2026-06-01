<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('email_verification_tokens')) {
            return;
        }

        Schema::create('email_verification_tokens', function (Blueprint $table): void {
            $table->string('email')->primary();
            $table->string('token_hash');
            $table->timestamp('expires_at');
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_verification_tokens');
    }
};
