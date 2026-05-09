<?php

namespace App\Services;

use App\Mail\PasswordResetLinkMail;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PasswordResetService
{
    public function sendResetLink(string $email): void
    {
        $email = strtolower(trim($email));
        $user = User::where('email', $email)->first();

        if (!$user) {
            return;
        }

        $token = Str::random(64);
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $email],
            [
                'token' => Hash::make($token),
                'created_at' => now(),
            ],
        );

        $url = rtrim((string) config('app.frontend_url'), '/').'/reset-password?'.http_build_query([
            'email' => $email,
            'token' => $token,
        ]);

        try {
            Mail::to($email)->send(new PasswordResetLinkMail($user->name, $url));
        } catch (\Throwable $exception) {
            Log::warning('Password reset email could not be sent.', [
                'email' => $email,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    public function reset(string $email, string $token, string $password): void
    {
        $email = strtolower(trim($email));
        $row = DB::table('password_reset_tokens')->where('email', $email)->first();

        if (!$row || !Hash::check($token, $row->token)) {
            throw ValidationException::withMessages([
                'token' => ['The password reset link is invalid.'],
            ]);
        }

        if (Carbon::parse($row->created_at)->addMinutes(60)->isPast()) {
            DB::table('password_reset_tokens')->where('email', $email)->delete();
            throw ValidationException::withMessages([
                'token' => ['The password reset link has expired.'],
            ]);
        }

        $user = User::where('email', $email)->first();
        if (!$user) {
            throw ValidationException::withMessages([
                'email' => ['No account exists for this email address.'],
            ]);
        }

        $user->forceFill(['password' => $password])->save();
        DB::table('password_reset_tokens')->where('email', $email)->delete();
    }
}
