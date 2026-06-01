<?php

namespace App\Services;

use App\Mail\EmailVerificationCodeMail;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Throwable;

class EmailVerificationService
{
    private const EXPIRES_IN_MINUTES = 15;

    public function sendCode(string $email): void
    {
        $email = $this->normalizeEmail($email);
        $now = now();
        $code = (string) random_int(100000, 999999);

        DB::table('email_verification_tokens')->updateOrInsert(
            ['email' => $email],
            [
                'token_hash' => Hash::make($code),
                'expires_at' => $now->copy()->addMinutes(self::EXPIRES_IN_MINUTES),
                'verified_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        try {
            Mail::to($email)->send(new EmailVerificationCodeMail($code, self::EXPIRES_IN_MINUTES));
        } catch (Throwable $exception) {
            Log::warning('Email verification code could not be sent.', [
                'email' => $email,
                'message' => $exception->getMessage(),
            ]);

            throw ValidationException::withMessages([
                'email' => ['We could not send the verification email. Please try again.'],
            ]);
        }
    }

    public function verifyCode(string $email, string $code): void
    {
        $email = $this->normalizeEmail($email);
        $row = DB::table('email_verification_tokens')->where('email', $email)->first();

        if (! $row || Carbon::parse($row->expires_at)->isPast() || ! Hash::check($code, $row->token_hash)) {
            throw ValidationException::withMessages([
                'emailVerificationCode' => ['The email verification code is invalid or expired.'],
            ]);
        }

        DB::table('email_verification_tokens')
            ->where('email', $email)
            ->update([
                'verified_at' => now(),
                'updated_at' => now(),
            ]);
    }

    public function assertVerified(string $email, string $code): void
    {
        $email = $this->normalizeEmail($email);
        $row = DB::table('email_verification_tokens')->where('email', $email)->first();

        if (! $row || Carbon::parse($row->expires_at)->isPast()) {
            throw ValidationException::withMessages([
                'emailVerificationCode' => ['Verify your email before creating the account.'],
            ]);
        }

        if (! $row->verified_at) {
            $this->verifyCode($email, $code);
        }
    }

    public function forget(string $email): void
    {
        DB::table('email_verification_tokens')->where('email', $this->normalizeEmail($email))->delete();
    }

    private function normalizeEmail(string $email): string
    {
        return strtolower(trim($email));
    }
}
