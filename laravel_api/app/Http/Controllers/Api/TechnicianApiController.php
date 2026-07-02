<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ServiceRequest;
use App\Models\Technician;
use App\Models\User;
use App\Services\AppSettingsService;
use App\Services\BeemAfricaOtpService;
use App\Services\ClickPesaPaymentService;
use App\Services\EmailVerificationService;
use App\Services\InfobipOtpService;
use App\Services\PasswordResetService;
use App\Services\PhoneVerificationService;
use App\Services\PushNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

class TechnicianApiController extends Controller
{
    public function __construct(
        private readonly PushNotificationService $push,
        private readonly ClickPesaPaymentService $payments,
        private readonly EmailVerificationService $emailVerification,
        private readonly PhoneVerificationService $phoneVerification,
        private readonly AppSettingsService $settings,
        private readonly BeemAfricaOtpService $beemOtp,
        private readonly InfobipOtpService $infobipOtp,
    )
    {
    }

    public function health(): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'database' => config('database.default'),
            'firebasePush' => $this->push->configured(),
            'smsProvider' => $this->settings->smsProvider(),
            'beemOtp' => $this->beemOtp->configured(),
            'infobipOtp' => $this->infobipOtp->configured(),
        ]);
    }

    public function portalOverview(): JsonResponse
    {
        $recentRequests = ServiceRequest::with(['client', 'technician'])->latest()->limit(12)->get();
        $technicians = Technician::query()->orderByDesc('last_seen_at')->limit(20)->get();
        $topSkills = ServiceRequest::query()
            ->select('skill', DB::raw('count(*) as count'))
            ->where('skill', '!=', '')
            ->groupBy('skill')
            ->orderByDesc('count')
            ->limit(8)
            ->get();

        $totalTechnicians = Technician::count();
        $availableTechnicians = Technician::where('available', true)->count();

        return response()->json([
            'ok' => true,
            'generatedAt' => now()->toISOString(),
            'stats' => [
                'totalClients' => User::where('role', 'client')->count(),
                'totalTechnicians' => $totalTechnicians,
                'availableTechnicians' => $availableTechnicians,
                'unavailableTechnicians' => max($totalTechnicians - $availableTechnicians, 0),
                'totalRequests' => ServiceRequest::count(),
                'pendingRequests' => ServiceRequest::where('status', 'pending')->count(),
                'acceptedRequests' => ServiceRequest::where('status', 'accepted')->count(),
                'completedRequests' => ServiceRequest::where('status', 'completed')->count(),
            ],
            'recentRequests' => $recentRequests->map(fn (ServiceRequest $row) => $this->requestDto($row)),
            'technicians' => $technicians->map(fn (Technician $row) => $this->technicianDto($row)),
            'topSkills' => $topSkills->map(fn ($row) => ['skill' => $row->skill, 'count' => (int) $row->count]),
            'firebasePush' => $this->push->configured(),
        ]);
    }

    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'role' => ['required', Rule::in(['client', 'technician'])],
            'name' => ['required', 'string', 'max:255'],
            'nida' => ['required_if:role,technician', 'nullable', 'string', 'max:40'],
            'nidaIdImage' => ['required_if:role,technician', 'nullable', 'string'],
            'faceImage' => ['required_if:role,technician', 'nullable', 'string'],
            'phone' => ['required', 'string', 'max:80'],
            'phoneVerificationIdToken' => ['required', 'string'],
            'phoneVerificationProvider' => ['nullable', Rule::in(AppSettingsService::SMS_PROVIDERS)],
            'email' => ['required', 'email', 'max:255'],
            'emailVerificationCode' => ['required', 'string', 'size:6'],
            'password' => ['required', 'string', 'min:6'],
            'token' => ['nullable', 'string'],
            'deviceToken' => ['nullable', 'string'],
            'lat' => ['required', 'numeric'],
            'lon' => ['required', 'numeric'],
            'skills' => ['nullable'],
            'image' => ['nullable', 'string'],
        ]);

        $email = strtolower(trim($data['email']));
        $latitude = (float) $data['lat'];
        $longitude = (float) $data['lon'];
        $deviceToken = $data['token'] ?? $data['deviceToken'] ?? null;
        $nida = $this->normalizeNida((string) ($data['nida'] ?? ''));
        $phone = $this->normalizeTanzaniaPhone((string) $data['phone']);

        if ($data['role'] === 'technician' && ! $this->isValidNida($nida)) {
            return response()->json([
                'error' => 'Enter NIDA in the format XXXXXXXX-XXXXX-XXXXX-XX.',
                'code' => 'invalid_nida',
            ], 422);
        }

        if (! $this->isValidTanzaniaPhone($phone)) {
            return response()->json([
                'error' => 'Enter a valid Tanzania mobile phone number.',
                'code' => 'invalid_phone',
            ], 422);
        }

        $existingUser = User::where('email', $email)->first();
        if ($existingUser?->blocked) {
            return response()->json([
                'error' => 'This account has been blocked. Contact support for help.',
                'code' => 'account_blocked',
            ], 403);
        }
        if ($existingUser?->phone_verified_at && $this->normalizeTanzaniaPhone((string) $existingUser->phone) !== $phone) {
            return response()->json([
                'error' => 'This account already has a verified transaction phone number. It cannot be changed after verification.',
                'code' => 'verified_phone_locked',
            ], 422);
        }
        if ($nida !== '' && (
            User::where('nida', $nida)->when($existingUser, fn ($query) => $query->where('id', '!=', $existingUser->id))->exists()
            || Technician::where('nida', $nida)->where('email', '!=', $email)->exists()
        )) {
            return response()->json([
                'error' => 'This NIDA number is already registered.',
                'code' => 'nida_already_registered',
            ], 422);
        }

        $verifiedPhone = $this->phoneVerification->verifyRegistrationToken(
            (string) $data['phoneVerificationIdToken'],
            $phone,
            $data['phoneVerificationProvider'] ?? null,
        );
        $this->emailVerification->assertVerified($email, (string) $data['emailVerificationCode']);
        $nidaIdImage = $data['role'] === 'technician'
            ? $this->validatedImageDataUri((string) ($data['nidaIdImage'] ?? ''), 'nidaIdImage')
            : null;
        $faceImage = $data['role'] === 'technician'
            ? $this->validatedImageDataUri((string) ($data['faceImage'] ?? ''), 'faceImage')
            : null;

        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'role' => $data['role'],
                'name' => $data['name'],
                'nida' => $nida === '' ? $existingUser?->nida : $nida,
                'phone' => $phone,
                'phone_verified_at' => $existingUser?->phone_verified_at ?? now(),
                'firebase_phone_uid' => $verifiedPhone['uid'],
                'email_verified_at' => $existingUser?->email_verified_at ?? now(),
                'password' => $data['password'],
                'device_token' => $deviceToken,
                'last_location' => [
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'updatedAt' => now()->toISOString(),
                ],
            ],
        );

        $payload = ['ok' => true, 'user' => $this->userDto($user)];
        $technician = null;
        if ($data['role'] === 'technician') {
            $technicianAttributes = [
                'user_id' => $user->id,
                'name' => $data['name'],
                'nida' => $nida,
                'phone' => $phone,
                'password' => $data['password'],
                'device_token' => $deviceToken,
                'skills' => $this->normalizeSkills($data['skills'] ?? []),
                'image' => $data['image'] ?? null,
                'nida_id_image' => $nidaIdImage,
                'face_image' => $faceImage,
                'registration_review_status' => 'pending',
                'registration_review_note' => null,
                'registration_reviewed_at' => null,
                'registration_reviewed_by' => null,
                'registration_fee_amount' => $this->payments->technicianRegistrationFee(),
                'registration_fee_currency' => config('services.clickpesa.currency', 'TZS'),
                'registration_payment_status' => 'not_requested',
                'registration_payment_order_reference' => null,
                'registration_payment_id' => null,
                'registration_payment_response' => null,
                'registration_payment_requested_at' => null,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'available' => false,
                'last_seen_at' => now(),
            ];
            if (Schema::hasColumn('technicians', 'client_requests_blocked')) {
                $technicianAttributes['client_requests_blocked'] = false;
                $technicianAttributes['client_requests_blocked_reason'] = null;
            }

            $technician = Technician::updateOrCreate(
                ['email' => $email],
                $technicianAttributes,
            );

            $payload['technician'] = $this->technicianDto($technician->fresh());
        }

        if (in_array(($verifiedPhone['provider'] ?? null), [
            AppSettingsService::SMS_PROVIDER_BEEM,
            AppSettingsService::SMS_PROVIDER_INFOBIP,
        ], true)) {
            $this->phoneVerification->forgetLocalToken((string) $data['phoneVerificationIdToken']);
        }
        $this->emailVerification->forget($email);
        $this->audit($request, 'auth.registered', [
            'actor_role' => $user->role,
            'actor_user_id' => $user->id,
            'actor_technician_id' => $technician?->id,
            'entity_type' => 'user',
            'entity_id' => $user->id,
            'metadata' => [
                'role' => $data['role'],
                'email' => $email,
                'nida' => $this->nidaHint($nida),
                'phone' => $this->phoneHint($phone),
                'phoneVerificationProvider' => $verifiedPhone['provider'],
                'hasDeviceToken' => filled($deviceToken),
            ],
        ]);

        return response()->json($payload, 201);
    }

    public function phoneVerificationProvider(): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'provider' => $this->settings->smsProvider(),
            'providers' => $this->settings->smsProviderOptions(),
            'beemConfigured' => $this->beemOtp->configured(),
            'infobipConfigured' => $this->infobipOtp->configured(),
        ]);
    }

    public function sendPhoneVerification(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:80'],
        ]);

        $phone = $this->normalizeTanzaniaPhone((string) $data['phone']);
        if (! $this->isValidTanzaniaPhone($phone)) {
            return response()->json([
                'error' => 'Enter a valid Tanzania mobile phone number.',
                'code' => 'invalid_phone',
            ], 422);
        }

        $provider = $this->settings->smsProvider();
        if ($provider === AppSettingsService::SMS_PROVIDER_FIREBASE) {
            return response()->json([
                'ok' => true,
                'provider' => $provider,
                'message' => 'Use Firebase phone authentication on the client.',
            ]);
        }

        if ($provider === AppSettingsService::SMS_PROVIDER_BEEM && ! $this->beemOtp->configured()) {
            return response()->json([
                'error' => 'Beem Africa OTP is not configured. Check BEEM_ACCESS_KEY, BEEM_SECRET_KEY, and BEEM_OTP_APP_ID.',
                'code' => 'sms_provider_not_configured',
                'provider' => $provider,
            ], 503);
        }

        if ($provider === AppSettingsService::SMS_PROVIDER_INFOBIP && ! $this->infobipOtp->configured()) {
            return response()->json([
                'error' => 'Infobip OTP is not configured. Check INFOBIP_BASE_URL, INFOBIP_API_KEY, and INFOBIP_SENDER.',
                'code' => 'sms_provider_not_configured',
                'provider' => $provider,
            ], 503);
        }

        $result = match ($provider) {
            AppSettingsService::SMS_PROVIDER_BEEM => [
                'verificationId' => $this->beemOtp->send($phone)['pinId'],
            ],
            AppSettingsService::SMS_PROVIDER_INFOBIP => [
                'verificationId' => $this->infobipOtp->send($phone)['verificationId'],
            ],
            default => null,
        };
        if ($result === null) {
            return response()->json([
                'error' => 'Unsupported SMS provider.',
                'code' => 'unsupported_sms_provider',
                'provider' => $provider,
            ], 422);
        }
        $this->audit($request, 'phone_verification.sent', [
            'entity_type' => 'phone',
            'entity_id' => $this->phoneHint($phone),
            'metadata' => [
                'provider' => $provider,
                'phone' => $this->phoneHint($phone),
            ],
        ]);

        return response()->json([
            'ok' => true,
            'provider' => $provider,
            'verificationId' => $result['verificationId'],
            'message' => 'A verification code has been sent by SMS to your phone.',
        ]);
    }

    public function verifyPhone(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:80'],
            'verificationId' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'size:6'],
        ]);

        $phone = $this->normalizeTanzaniaPhone((string) $data['phone']);
        if (! $this->isValidTanzaniaPhone($phone)) {
            return response()->json([
                'error' => 'Enter a valid Tanzania mobile phone number.',
                'code' => 'invalid_phone',
            ], 422);
        }

        $provider = $this->settings->smsProvider();
        if ($provider === AppSettingsService::SMS_PROVIDER_FIREBASE) {
            return response()->json([
                'error' => 'Use Firebase phone authentication on the client.',
                'code' => 'firebase_phone_auth_required',
                'provider' => $provider,
            ], 409);
        }

        if ($provider === AppSettingsService::SMS_PROVIDER_BEEM) {
            $this->beemOtp->verify((string) $data['verificationId'], (string) $data['code'], $phone);
        } elseif ($provider === AppSettingsService::SMS_PROVIDER_INFOBIP) {
            $this->infobipOtp->verify((string) $data['verificationId'], (string) $data['code'], $phone);
        } else {
            return response()->json([
                'error' => 'Unsupported SMS provider.',
                'code' => 'unsupported_sms_provider',
                'provider' => $provider,
            ], 422);
        }

        $token = $this->phoneVerification->issueLocalToken($phone, $provider);
        $this->audit($request, 'phone_verification.verified', [
            'entity_type' => 'phone',
            'entity_id' => $this->phoneHint($phone),
            'metadata' => [
                'provider' => $provider,
                'phone' => $this->phoneHint($phone),
            ],
        ]);

        return response()->json([
            'ok' => true,
            'provider' => $provider,
            'phoneVerificationIdToken' => $token,
            'message' => 'Phone verified successfully.',
        ]);
    }

    public function sendEmailVerification(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        $email = strtolower(trim($data['email']));
        $user = User::where('email', $email)->first();
        if ($user?->blocked) {
            return response()->json([
                'error' => 'This account has been blocked. Contact support for help.',
                'code' => 'account_blocked',
            ], 403);
        }

        $this->emailVerification->sendCode($email);
        $this->audit($request, 'email_verification.sent', [
            'entity_type' => 'email',
            'entity_id' => $email,
            'metadata' => ['email' => $email],
        ]);

        return response()->json([
            'ok' => true,
            'message' => 'A verification code has been sent to your email.',
        ]);
    }

    public function verifyEmail(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'code' => ['required', 'string', 'size:6'],
        ]);

        $this->emailVerification->verifyCode($data['email'], $data['code']);
        $this->audit($request, 'email_verification.verified', [
            'entity_type' => 'email',
            'entity_id' => strtolower(trim($data['email'])),
            'metadata' => ['email' => strtolower(trim($data['email']))],
        ]);

        return response()->json([
            'ok' => true,
            'message' => 'Email verified successfully.',
        ]);
    }

    public function registrationPaymentStatus(Technician $technician): JsonResponse
    {
        if (! $this->technicianRegistrationApproved($technician)) {
            return response()->json([
                'error' => 'Technician registration is still under admin review.',
                'code' => 'admin_review_pending',
                'registrationReview' => $this->registrationReviewDto($technician),
            ], 403);
        }

        if (blank($technician->registration_payment_order_reference)) {
            return response()->json([
                'ok' => true,
                'registrationPayment' => $this->registrationPaymentDto($technician, true),
            ]);
        }

        try {
            $status = $this->payments->paymentStatus($technician->registration_payment_order_reference);
            $latest = is_array($status) && array_is_list($status) ? ($status[0] ?? []) : $status;

            if (is_array($latest) && filled($latest['status'] ?? null)) {
                $technician->update([
                    'registration_payment_status' => strtolower((string) $latest['status']),
                    'registration_payment_id' => $latest['id'] ?? $technician->registration_payment_id,
                    'registration_payment_response' => $status,
                ]);
            }
        } catch (Throwable $exception) {
            Log::warning('Unable to refresh technician registration payment status.', [
                'technician_id' => $technician->id,
                'order_reference' => $technician->registration_payment_order_reference,
                'message' => $exception->getMessage(),
            ]);
        }

        return response()->json([
            'ok' => true,
            'registrationPayment' => $this->registrationPaymentDto($technician->fresh(), true),
        ]);
    }

    public function requestRegistrationPayment(Request $request, Technician $technician): JsonResponse
    {
        if (! $this->technicianRegistrationApproved($technician)) {
            return response()->json([
                'error' => 'Technician registration is still under admin review.',
                'code' => 'admin_review_pending',
                'registrationReview' => $this->registrationReviewDto($technician),
            ], 403);
        }

        $data = $request->validate([
            'payerPhone' => ['required', 'string', 'max:80'],
            'operator' => ['required', 'string', Rule::in($this->registrationFeeSupportedOperators())],
        ]);

        $payerPhone = $this->normalizeTanzaniaPhone((string) $data['payerPhone']);
        $operator = (string) $data['operator'];

        if (! $this->isValidTanzaniaPhone($payerPhone)) {
            $this->audit($request, 'payment_push.rejected', [
                'actor_role' => 'technician',
                'actor_user_id' => $technician->user_id,
                'actor_technician_id' => $technician->id,
                'entity_type' => 'technician',
                'entity_id' => $technician->id,
                'metadata' => [
                    'reason' => 'invalid_phone',
                    'operator' => $operator,
                    'payerPhone' => $this->phoneHint($payerPhone),
                ],
            ]);

            return response()->json([
                'error' => 'Enter a valid Tanzania mobile phone number.',
                'code' => 'invalid_phone',
            ], 422);
        }

        if ($this->isMpesaPhoneNumber($payerPhone)) {
            $this->audit($request, 'payment_push.rejected', [
                'actor_role' => 'technician',
                'actor_user_id' => $technician->user_id,
                'actor_technician_id' => $technician->id,
                'entity_type' => 'technician',
                'entity_id' => $technician->id,
                'metadata' => [
                    'reason' => 'unsupported_payment_operator',
                    'operator' => $operator,
                    'payerPhone' => $this->phoneHint($payerPhone),
                ],
            ]);

            return response()->json([
                'error' => 'Registration fee supports Yas, Airtel, and Halopesa only. M-Pesa is not supported yet.',
                'code' => 'unsupported_payment_operator',
                'supportedOperators' => $this->registrationFeeSupportedOperators(),
            ], 422);
        }

        if ($this->registrationPaymentIsPaid($technician->registration_payment_status)) {
            $this->audit($request, 'payment_push.skipped', [
                'actor_role' => 'technician',
                'actor_user_id' => $technician->user_id,
                'actor_technician_id' => $technician->id,
                'entity_type' => 'technician',
                'entity_id' => $technician->id,
                'metadata' => [
                    'reason' => 'already_paid',
                    'operator' => $operator,
                    'payerPhone' => $this->phoneHint($payerPhone),
                    'status' => $technician->registration_payment_status,
                ],
            ]);

            return response()->json([
                'ok' => true,
                'message' => 'Registration fee is already paid.',
                'registrationPayment' => $this->registrationPaymentDto($technician, true),
            ]);
        }

        $amount = $this->payments->technicianRegistrationFee();
        $currency = config('services.clickpesa.currency', 'TZS');
        $orderReference = $this->payments->newOrderReference($technician->id);
        $actionId = $this->createPaymentAction($technician, [
            'operator' => $operator,
            'payer_phone' => $payerPhone,
            'amount' => $amount,
            'currency' => $currency,
            'status' => 'initiated',
            'order_reference' => $orderReference,
            'requested_at' => now(),
        ]);

        if (! $this->payments->configured()) {
            $message = 'ClickPesa credentials are not configured.';
            $this->updatePaymentAction($actionId, [
                'status' => 'not_configured',
                'error' => $message,
            ]);
            $technician->update([
                'registration_fee_amount' => $amount,
                'registration_fee_currency' => $currency,
                'registration_payment_status' => 'not_configured',
                'registration_payment_order_reference' => $orderReference,
                'registration_payment_response' => ['message' => $message],
                'registration_payment_requested_at' => now(),
            ]);
            $this->audit($request, 'payment_push.failed', [
                'actor_role' => 'technician',
                'actor_user_id' => $technician->user_id,
                'actor_technician_id' => $technician->id,
                'entity_type' => 'technician',
                'entity_id' => $technician->id,
                'metadata' => [
                    'reason' => 'payment_not_configured',
                    'operator' => $operator,
                    'payerPhone' => $this->phoneHint($payerPhone),
                    'orderReference' => $orderReference,
                ],
            ]);

            return response()->json([
                'error' => $message,
                'code' => 'payment_not_configured',
                'registrationPayment' => $this->registrationPaymentDto($technician->fresh(), true),
            ], 503);
        }

        try {
            $response = $this->payments->initiateTechnicianRegistrationPayment(
                $payerPhone,
                $orderReference,
            );
            $status = strtolower((string) ($response['status'] ?? 'processing'));

            $this->updatePaymentAction($actionId, [
                'status' => $status,
                'payment_id' => $response['id'] ?? null,
                'response' => $response,
            ]);
            $technician->update([
                'registration_fee_amount' => $amount,
                'registration_fee_currency' => $currency,
                'registration_payment_status' => $status,
                'registration_payment_order_reference' => $response['orderReference'] ?? $orderReference,
                'registration_payment_id' => $response['id'] ?? null,
                'registration_payment_response' => [
                    'operator' => $operator,
                    'payerPhone' => $payerPhone,
                    'providerResponse' => $response,
                ],
                'registration_payment_requested_at' => now(),
            ]);
            $this->audit($request, 'payment_push.sent', [
                'actor_role' => 'technician',
                'actor_user_id' => $technician->user_id,
                'actor_technician_id' => $technician->id,
                'entity_type' => 'technician',
                'entity_id' => $technician->id,
                'metadata' => [
                    'operator' => $operator,
                    'payerPhone' => $this->phoneHint($payerPhone),
                    'orderReference' => $response['orderReference'] ?? $orderReference,
                    'paymentId' => $response['id'] ?? null,
                    'status' => $status,
                ],
            ]);

            return response()->json([
                'ok' => true,
                'message' => 'Payment push has been sent.',
                'registrationPayment' => $this->registrationPaymentDto($technician->fresh(), true),
            ], 201);
        } catch (Throwable $exception) {
            Log::error('Unable to initiate technician registration payment from mobile app.', [
                'technician_id' => $technician->id,
                'operator' => $operator,
                'payer_phone' => $payerPhone,
                'message' => $exception->getMessage(),
            ]);

            $this->updatePaymentAction($actionId, [
                'status' => 'request_failed',
                'error' => $exception->getMessage(),
            ]);
            $technician->update([
                'registration_fee_amount' => $amount,
                'registration_fee_currency' => $currency,
                'registration_payment_status' => 'request_failed',
                'registration_payment_order_reference' => $orderReference,
                'registration_payment_response' => [
                    'operator' => $operator,
                    'payerPhone' => $payerPhone,
                    'message' => $exception->getMessage(),
                ],
                'registration_payment_requested_at' => now(),
            ]);
            $this->audit($request, 'payment_push.failed', [
                'actor_role' => 'technician',
                'actor_user_id' => $technician->user_id,
                'actor_technician_id' => $technician->id,
                'entity_type' => 'technician',
                'entity_id' => $technician->id,
                'metadata' => [
                    'reason' => 'payment_request_failed',
                    'operator' => $operator,
                    'payerPhone' => $this->phoneHint($payerPhone),
                    'orderReference' => $orderReference,
                    'message' => $exception->getMessage(),
                ],
            ]);

            return response()->json([
                'error' => $exception->getMessage(),
                'code' => 'payment_request_failed',
                'registrationPayment' => $this->registrationPaymentDto($technician->fresh(), true),
            ], 502);
        }
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'token' => ['nullable', 'string'],
            'deviceToken' => ['nullable', 'string'],
        ]);

        $email = strtolower(trim($data['email']));
        $user = User::where('email', $email)->first();
        if (!$user || !Hash::check($data['password'], $user->password)) {
            $this->audit($request, 'auth.login_failed', [
                'entity_type' => 'user',
                'entity_id' => $email,
                'metadata' => ['email' => $email, 'reason' => 'invalid_credentials'],
            ]);

            return response()->json([
                'error' => 'Incorrect email or password',
                'code' => 'invalid_credentials',
            ], 401);
        }
        if ($user->blocked) {
            $this->audit($request, 'auth.login_failed', [
                'actor_role' => $user->role,
                'actor_user_id' => $user->id,
                'entity_type' => 'user',
                'entity_id' => $user->id,
                'metadata' => ['email' => $email, 'reason' => 'account_blocked'],
            ]);

            return response()->json([
                'error' => 'This account has been blocked. Contact support for help.',
                'code' => 'account_blocked',
            ], 403);
        }

        $deviceToken = $data['token'] ?? $data['deviceToken'] ?? null;
        if ($deviceToken) {
            $user->update(['device_token' => $deviceToken]);
            Technician::where('email', $email)->update(['device_token' => $deviceToken]);
        }

        $payload = ['ok' => true, 'user' => $this->userDto($user->fresh())];
        $technician = Technician::where('email', $email)->first();
        if ($technician) {
            $payload['technician'] = $this->technicianDto($technician);
            $payload['registrationPayment'] = $this->registrationPaymentDto($technician, true);
        }
        $this->audit($request, 'auth.login', [
            'actor_role' => $user->role,
            'actor_user_id' => $user->id,
            'actor_technician_id' => $technician?->id,
            'entity_type' => 'user',
            'entity_id' => $user->id,
            'metadata' => [
                'email' => $email,
                'hasDeviceToken' => filled($deviceToken),
            ],
        ]);

        return response()->json($payload);
    }

    public function forgotPassword(Request $request, PasswordResetService $passwordReset): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $passwordReset->sendResetLink($data['email']);

        return response()->json([
            'ok' => true,
            'message' => 'If that email exists, a password reset link has been sent.',
        ]);
    }

    public function resetPassword(Request $request, PasswordResetService $passwordReset): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'token' => ['required', 'string'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ]);

        $passwordReset->reset($data['email'], $data['token'], $data['password']);

        return response()->json([
            'ok' => true,
            'message' => 'Password reset successfully.',
        ]);
    }

    public function searchTechnicians(Request $request): JsonResponse
    {
        $data = $request->validate([
            'skill' => ['nullable', 'string'],
            'lat' => ['nullable', 'numeric'],
            'lon' => ['nullable', 'numeric'],
            'maxDistanceKm' => ['nullable', 'numeric', 'min:0'],
            'available' => ['nullable'],
            'minRating' => ['nullable', 'numeric', 'min:0'],
        ]);

        $query = Technician::query();
        if (Schema::hasColumn('technicians', 'registration_review_status')) {
            $query->where('registration_review_status', 'approved');
        }
        if (Schema::hasColumn('technicians', 'client_requests_blocked')) {
            $query->where('client_requests_blocked', false);
        }
        $query->where(function ($query): void {
            $query->whereDoesntHave('user')
                ->orWhereHas('user', fn ($userQuery) => $userQuery->where('blocked', false));
        });
        $skill = strtolower(trim($data['skill'] ?? ''));

        if (array_key_exists('available', $data)) {
            $query->where('available', filter_var($data['available'], FILTER_VALIDATE_BOOLEAN));
        } else {
            $query->where('available', true);
        }

        if (isset($data['minRating'])) {
            $query->where('rating', '>=', (float) $data['minRating']);
        }

        $lat = isset($data['lat']) ? (float) $data['lat'] : null;
        $lon = isset($data['lon']) ? (float) $data['lon'] : null;
        $maxDistance = isset($data['maxDistanceKm']) ? (float) $data['maxDistanceKm'] : null;

        $technicians = $query->get()
            ->filter(fn (Technician $row) => $skill === '' || $this->skillMatches($row, $skill))
            ->map(fn (Technician $row) => ['model' => $row, 'distance' => $this->distanceKm($lat, $lon, $row->latitude, $row->longitude)])
            ->filter(fn ($row) => $maxDistance === null || $row['distance'] === null || $row['distance'] <= $maxDistance)
            ->sortBy(fn ($row) => $row['distance'] ?? PHP_FLOAT_MAX)
            ->values()
            ->map(fn ($row) => $this->technicianDto($row['model'], $row['distance']));

        return response()->json($technicians);
    }

    public function updateUserLocation(Request $request, string $user): JsonResponse
    {
        $user = User::find($user);
        if (! $user) {
            return response()->json([
                'error' => 'Account not found. Please sign in again.',
                'code' => 'account_not_found',
            ], 404);
        }
        if ($user->blocked) {
            return response()->json([
                'error' => 'This account has been blocked. Contact support for help.',
                'code' => 'account_blocked',
            ], 403);
        }

        $data = $request->validate([
            'lat' => ['required', 'numeric'],
            'lon' => ['required', 'numeric'],
            'token' => ['nullable', 'string'],
            'deviceToken' => ['nullable', 'string'],
        ]);

        $location = ['latitude' => (float) $data['lat'], 'longitude' => (float) $data['lon'], 'updatedAt' => now()->toISOString()];
        $user->update([
            'last_location' => $location,
            'device_token' => $data['token'] ?? $data['deviceToken'] ?? $user->device_token,
        ]);

        if ($user->role === 'technician') {
            Technician::where('user_id', $user->id)->update([
                'latitude' => $location['latitude'],
                'longitude' => $location['longitude'],
                'device_token' => $user->device_token,
                'last_seen_at' => now(),
            ]);
        }

        return response()->json(['ok' => true, 'user' => $this->userDto($user->fresh())]);
    }

    public function updateDeviceToken(Request $request, string $user): JsonResponse
    {
        $user = User::find($user);
        if (! $user) {
            return response()->json([
                'error' => 'Account not found. Please sign in again.',
                'code' => 'account_not_found',
            ], 404);
        }
        if ($user->blocked) {
            return response()->json([
                'error' => 'This account has been blocked. Contact support for help.',
                'code' => 'account_blocked',
            ], 403);
        }

        $data = $request->validate([
            'token' => ['nullable', 'string'],
            'deviceToken' => ['nullable', 'string'],
        ]);

        $deviceToken = $data['token'] ?? $data['deviceToken'] ?? null;
        if (blank($deviceToken)) {
            return response()->json([
                'error' => 'Device notification token is required.',
                'code' => 'device_token_required',
            ], 422);
        }

        $user->update(['device_token' => $deviceToken]);
        Technician::where('user_id', $user->id)
            ->orWhere('email', $user->email)
            ->update(['device_token' => $deviceToken]);

        return response()->json(['ok' => true, 'user' => $this->userDto($user->fresh())]);
    }

    public function updateTechnicianLocation(Request $request, string $technician): JsonResponse
    {
        $technician = Technician::with('user')->find($technician);
        if (! $technician) {
            return response()->json([
                'error' => 'Technician account not found. Please sign in again.',
                'code' => 'account_not_found',
            ], 404);
        }
        if ($technician->user?->blocked) {
            return response()->json([
                'error' => 'This account has been blocked. Contact support for help.',
                'code' => 'account_blocked',
            ], 403);
        }
        if (! $this->technicianRegistrationApproved($technician)) {
            return response()->json([
                'error' => 'Technician registration is still under admin review.',
                'code' => 'admin_review_pending',
                'registrationReview' => $this->registrationReviewDto($technician),
            ], 403);
        }

        $data = $request->validate([
            'lat' => ['required', 'numeric'],
            'lon' => ['required', 'numeric'],
            'available' => ['nullable', 'boolean'],
            'token' => ['nullable', 'string'],
            'deviceToken' => ['nullable', 'string'],
        ]);

        $available = array_key_exists('available', $data) ? (bool) $data['available'] : $technician->available;
        if ($this->technicianClientRequestsBlocked($technician)) {
            $available = false;
        }

        $technician->update([
            'latitude' => (float) $data['lat'],
            'longitude' => (float) $data['lon'],
            'available' => $available,
            'device_token' => $data['token'] ?? $data['deviceToken'] ?? $technician->device_token,
            'last_seen_at' => now(),
        ]);

        if ($technician->user) {
            $technician->user->update([
                'device_token' => $technician->device_token,
                'last_location' => [
                    'latitude' => $technician->latitude,
                    'longitude' => $technician->longitude,
                    'updatedAt' => now()->toISOString(),
                ],
            ]);
        }

        return response()->json(['ok' => true, 'technician' => $this->technicianDto($technician->fresh())]);
    }

    public function updateAvailability(Request $request, Technician $technician): JsonResponse
    {
        if (! $this->technicianRegistrationApproved($technician)) {
            return response()->json([
                'error' => 'Technician registration is still under admin review.',
                'code' => 'admin_review_pending',
                'registrationReview' => $this->registrationReviewDto($technician),
            ], 403);
        }

        $data = $request->validate(['available' => ['required', 'boolean']]);
        if ((bool) $data['available'] && $this->technicianClientRequestsBlocked($technician)) {
            return response()->json([
                'error' => 'Admin has blocked this technician from receiving client requests.',
                'code' => 'technician_requests_blocked',
                'technician' => $this->technicianDto($technician),
            ], 403);
        }

        $technician->update(['available' => (bool) $data['available'], 'last_seen_at' => now()]);
        $this->audit($request, 'technician.availability_updated', [
            'actor_role' => 'technician',
            'actor_user_id' => $technician->user_id,
            'actor_technician_id' => $technician->id,
            'entity_type' => 'technician',
            'entity_id' => $technician->id,
            'metadata' => ['available' => (bool) $data['available']],
        ]);

        return response()->json(['ok' => true, 'technician' => $this->technicianDto($technician->fresh())]);
    }

    public function createRequest(Request $request): JsonResponse
    {
        $data = $request->validate([
            'clientId' => ['required', 'integer', 'exists:users,id'],
            'technicianId' => ['nullable', 'integer', 'exists:technicians,id'],
            'skill' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'lat' => ['required', 'numeric'],
            'lon' => ['required', 'numeric'],
        ]);

        $client = User::findOrFail($data['clientId']);
        if ($client->blocked) {
            return response()->json([
                'error' => 'This account has been blocked. Contact support for help.',
                'code' => 'account_blocked',
            ], 403);
        }
        $clientLat = (float) $data['lat'];
        $clientLon = (float) $data['lon'];
        $technician = isset($data['technicianId'])
            ? Technician::findOrFail($data['technicianId'])
            : $this->nearestTechnician($data['skill'], $clientLat, $clientLon);

        if (!$technician) {
            return response()->json(['error' => 'No matching technician found'], 404);
        }
        if ($technician->user?->blocked) {
            return response()->json(['error' => 'Selected technician is not available.'], 422);
        }
        if (! $this->technicianRegistrationApproved($technician)) {
            return response()->json([
                'error' => 'Selected technician is not available.',
                'code' => 'admin_review_pending',
            ], 422);
        }
        if ($this->technicianClientRequestsBlocked($technician)) {
            return response()->json([
                'error' => 'Selected technician is not available for client requests.',
                'code' => 'technician_requests_blocked',
            ], 422);
        }

        $distance = $this->distanceKm($clientLat, $clientLon, $technician->latitude, $technician->longitude);
        $serviceRequest = ServiceRequest::create([
            'client_id' => $client->id,
            'technician_id' => $technician->id,
            'skill' => $data['skill'],
            'description' => $data['description'] ?? '',
            'status' => 'pending',
            'client_latitude' => $clientLat,
            'client_longitude' => $clientLon,
            'technician_latitude_at_request' => $technician->latitude,
            'technician_longitude_at_request' => $technician->longitude,
            'distance_km' => $distance === null ? null : round($distance, 2),
        ]);

        $skillLabel = $this->notificationSkill($serviceRequest->skill);
        $distanceLabel = $serviceRequest->distance_km === null
            ? ''
            : ' about '.number_format((float) $serviceRequest->distance_km, 1).' km away';
        $technicianTitle = "New {$skillLabel} request";
        $technicianBody = "{$client->name} needs {$skillLabel}{$distanceLabel}. Open the app to accept or reject.";
        if (filled($serviceRequest->description)) {
            $technicianBody .= ' Details: '.Str::limit((string) $serviceRequest->description, 90);
        }
        $technicianData = [
            'requestId' => $serviceRequest->id,
            'type' => 'tech_request',
            'clientId' => $client->id,
            'clientName' => $client->name,
            'technicianId' => $technician->id,
            'skill' => $serviceRequest->skill,
            'distanceKm' => $serviceRequest->distance_km,
            'title' => $technicianTitle,
            'body' => $technicianBody,
            'actions' => 'accept,reject',
        ];

        $technicianToken = $technician->device_token;
        $pushed = $this->push->send($technicianToken, [
            'title' => $technicianTitle,
            'body' => $technicianBody,
        ], $technicianData);

        if (! $pushed) {
            Log::warning('New request notification was not delivered to the technician.', [
                'request_id' => $serviceRequest->id,
                'client_id' => $client->id,
                'technician_id' => $technician->id,
                'has_technician_token' => filled($technicianToken),
            ]);
        }
        $this->audit($request, 'service_request.created', [
            'actor_role' => 'client',
            'actor_user_id' => $client->id,
            'entity_type' => 'service_request',
            'entity_id' => $serviceRequest->id,
            'metadata' => [
                'technicianId' => $technician->id,
                'skill' => $serviceRequest->skill,
                'distanceKm' => $serviceRequest->distance_km,
                'notificationRecipient' => 'technician',
                'notificationTitle' => $technicianTitle,
                'notificationBody' => $technicianBody,
                'notificationSent' => $pushed,
                'hasRecipientToken' => filled($technicianToken),
            ],
        ]);

        return response()->json([
            'ok' => true,
            'pushed' => $pushed,
            'technicianNotification' => [
                'required' => true,
                'sent' => $pushed,
                'hasToken' => filled($technicianToken),
            ],
            'request' => $this->requestDto($serviceRequest->load(['client', 'technician'])),
        ], 201);
    }

    public function respondToRequest(Request $request, ServiceRequest $serviceRequest): JsonResponse
    {
        $data = $request->validate([
            'technicianId' => ['nullable', 'integer', 'exists:technicians,id'],
            'status' => ['required', Rule::in(['accepted', 'declined', 'rejected', 'completed', 'cancelled'])],
            'message' => ['nullable', 'string'],
        ]);

        if (isset($data['technicianId']) && (int) $data['technicianId'] !== (int) $serviceRequest->technician_id) {
            return response()->json(['error' => 'Request is assigned to another technician'], 403);
        }
        $serviceRequest->loadMissing(['client', 'technician.user']);
        if ($serviceRequest->client?->blocked || $serviceRequest->technician?->user?->blocked) {
            return response()->json([
                'error' => 'This request cannot be updated because one account is blocked.',
                'code' => 'account_blocked',
            ], 403);
        }
        if ($serviceRequest->technician && ! $this->technicianRegistrationApproved($serviceRequest->technician)) {
            return response()->json([
                'error' => 'Technician registration is still under admin review.',
                'code' => 'admin_review_pending',
            ], 403);
        }

        $status = $data['status'] === 'declined' ? 'rejected' : $data['status'];
        $serviceRequest->update([
            'status' => $status,
            'response_message' => $data['message'] ?? '',
            'responded_at' => now(),
            'completed_at' => $status === 'completed' ? now() : $serviceRequest->completed_at,
        ]);

        $serviceRequest->load(['client', 'technician']);
        $technicianName = $serviceRequest->technician?->name ?? 'Technician';
        $skillLabel = $this->notificationSkill($serviceRequest->skill);
        $clientNotificationRequired = in_array($status, ['accepted', 'rejected', 'completed', 'cancelled'], true);
        $notificationTitle = match ($status) {
            'accepted' => 'Technician is on the way',
            'rejected' => 'Request declined',
            'completed' => 'Service completed',
            'cancelled' => 'Service request cancelled',
            default => 'Request updated',
        };
        $notificationBody = match ($status) {
            'accepted' => "{$technicianName} accepted your {$skillLabel} request. Open the app to track live location.",
            'rejected' => "{$technicianName} is not available for your {$skillLabel} request. Please choose another technician.",
            'completed' => "Your {$skillLabel} service was marked completed.",
            'cancelled' => "Your {$skillLabel} service request was cancelled.",
            default => "Your {$skillLabel} request was updated.",
        };
        $notificationData = [
            'requestId' => $serviceRequest->id,
            'type' => 'request_response',
            'status' => $status,
            'technicianId' => $serviceRequest->technician_id,
            'technicianName' => $technicianName,
            'skill' => $serviceRequest->skill,
            'title' => $notificationTitle,
            'body' => $notificationBody,
        ];

        $clientToken = $serviceRequest->client?->device_token;
        $pushed = $clientNotificationRequired
            ? $this->push->send($clientToken, [
                'title' => $notificationTitle,
                'body' => $notificationBody,
            ], $notificationData)
            : false;

        if ($clientNotificationRequired && ! $pushed) {
            Log::warning('Technician response notification was not delivered to the client.', [
                'request_id' => $serviceRequest->id,
                'client_id' => $serviceRequest->client_id,
                'technician_id' => $serviceRequest->technician_id,
                'status' => $status,
                'has_client_token' => filled($clientToken),
            ]);
        }
        $this->audit($request, 'service_request.responded', [
            'actor_role' => 'technician',
            'actor_user_id' => $serviceRequest->technician?->user_id,
            'actor_technician_id' => $serviceRequest->technician_id,
            'entity_type' => 'service_request',
            'entity_id' => $serviceRequest->id,
            'metadata' => [
                'clientId' => $serviceRequest->client_id,
                'status' => $status,
                'notificationRecipient' => 'client',
                'notificationRequired' => $clientNotificationRequired,
                'notificationTitle' => $notificationTitle,
                'notificationBody' => $notificationBody,
                'notificationSent' => $pushed,
                'hasRecipientToken' => filled($clientToken),
            ],
        ]);

        return response()->json([
            'ok' => true,
            'pushed' => $pushed,
            'clientNotification' => [
                'required' => $clientNotificationRequired,
                'sent' => $pushed,
                'hasToken' => filled($clientToken),
            ],
            'request' => $this->requestDto($serviceRequest->fresh(['client', 'technician'])),
        ]);
    }

    public function completeRequest(Request $request, ServiceRequest $serviceRequest): JsonResponse
    {
        $data = $request->validate([
            'clientId' => ['required', 'integer', 'exists:users,id'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'report' => ['nullable', 'string', 'max:2000'],
        ]);

        if ((int) $data['clientId'] !== (int) $serviceRequest->client_id) {
            return response()->json(['error' => 'Request belongs to another client'], 403);
        }

        if ($serviceRequest->status !== 'accepted') {
            return response()->json([
                'error' => 'Only accepted requests can be ended.',
                'code' => 'request_not_active',
            ], 422);
        }

        $serviceRequest->update([
            'status' => 'completed',
            'client_rating' => (int) $data['rating'],
            'client_report' => $data['report'] ?? null,
            'completed_at' => now(),
        ]);

        $technician = $serviceRequest->technician;
        if ($technician) {
            $technician->update([
                'rating' => round((((float) $technician->rating) + (int) $data['rating']) / 2, 2),
            ]);
        }
        $this->audit($request, 'service_request.completed', [
            'actor_role' => 'client',
            'actor_user_id' => $serviceRequest->client_id,
            'actor_technician_id' => $serviceRequest->technician_id,
            'entity_type' => 'service_request',
            'entity_id' => $serviceRequest->id,
            'metadata' => [
                'rating' => (int) $data['rating'],
                'hasReport' => filled($data['report'] ?? null),
            ],
        ]);

        return response()->json([
            'ok' => true,
            'request' => $this->requestDto($serviceRequest->fresh(['client', 'technician'])),
        ]);
    }

    public function reportRequest(Request $request, ServiceRequest $serviceRequest): JsonResponse
    {
        $data = $request->validate([
            'reporterRole' => ['required', Rule::in(['client', 'technician'])],
            'clientId' => ['nullable', 'integer', 'exists:users,id'],
            'technicianId' => ['nullable', 'integer', 'exists:technicians,id'],
            'reason' => ['nullable', 'string', 'max:120'],
            'details' => ['required', 'string', 'max:2000'],
        ]);

        $reporterRole = $data['reporterRole'];
        if ($reporterRole === 'client') {
            if ((int) ($data['clientId'] ?? 0) !== (int) $serviceRequest->client_id) {
                return response()->json(['error' => 'Request belongs to another client'], 403);
            }

            $report = [
                'reporter_role' => 'client',
                'reporter_user_id' => $serviceRequest->client_id,
                'reporter_technician_id' => null,
                'reported_role' => 'technician',
                'reported_user_id' => null,
                'reported_technician_id' => $serviceRequest->technician_id,
            ];
        } else {
            if ((int) ($data['technicianId'] ?? 0) !== (int) $serviceRequest->technician_id) {
                return response()->json(['error' => 'Request is assigned to another technician'], 403);
            }

            $report = [
                'reporter_role' => 'technician',
                'reporter_user_id' => null,
                'reporter_technician_id' => $serviceRequest->technician_id,
                'reported_role' => 'client',
                'reported_user_id' => $serviceRequest->client_id,
                'reported_technician_id' => null,
            ];
        }

        $reportId = DB::table('abuse_reports')->insertGetId([
            'service_request_id' => $serviceRequest->id,
            ...$report,
            'reason' => $data['reason'] ?? 'abuse_or_misconduct',
            'details' => $data['details'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Log::warning('Abuse or misconduct report submitted.', [
            'report_id' => $reportId,
            'request_id' => $serviceRequest->id,
            'reporter_role' => $reporterRole,
            'reported_role' => $report['reported_role'],
        ]);
        $this->audit($request, 'abuse_report.submitted', [
            'actor_role' => $reporterRole,
            'actor_user_id' => $report['reporter_user_id'],
            'actor_technician_id' => $report['reporter_technician_id'],
            'entity_type' => 'abuse_report',
            'entity_id' => $reportId,
            'metadata' => [
                'serviceRequestId' => $serviceRequest->id,
                'reportedRole' => $report['reported_role'],
                'reportedUserId' => $report['reported_user_id'],
                'reportedTechnicianId' => $report['reported_technician_id'],
                'reason' => $data['reason'] ?? 'abuse_or_misconduct',
            ],
        ]);

        return response()->json([
            'ok' => true,
            'reportId' => (string) $reportId,
        ], 201);
    }

    public function requestHistory(Request $request): JsonResponse
    {
        $data = $request->validate([
            'clientId' => ['nullable', 'integer'],
            'technicianId' => ['nullable', 'integer'],
            'status' => ['nullable', 'string'],
        ]);

        $query = ServiceRequest::with(['client', 'technician'])->latest();
        if (isset($data['clientId'])) {
            $query->where('client_id', $data['clientId']);
        }
        if (isset($data['technicianId'])) {
            $technician = Technician::find($data['technicianId']);
            if (! $technician || ! $this->technicianRegistrationApproved($technician)) {
                return response()->json([]);
            }
            $query->where('technician_id', $data['technicianId']);
        }
        if (isset($data['status'])) {
            $query->where('status', $data['status']);
        }

        return response()->json($query->limit(100)->get()->map(fn (ServiceRequest $row) => $this->requestDto($row)));
    }

    public function legacyTechnicianLogin(Request $request): JsonResponse
    {
        return $this->login($request);
    }

    public function legacyNearestTechnician(Request $request): JsonResponse
    {
        $request->merge(['available' => $request->input('available', true)]);
        $results = $this->searchTechnicians($request)->getData(true);

        return response()->json($results[0] ?? null);
    }

    private function nearestTechnician(string $skill, float $lat, float $lon): ?Technician
    {
        $query = Technician::where('available', true);
        if (Schema::hasColumn('technicians', 'registration_review_status')) {
            $query->where('registration_review_status', 'approved');
        }
        if (Schema::hasColumn('technicians', 'client_requests_blocked')) {
            $query->where('client_requests_blocked', false);
        }

        return $query
            ->get()
            ->filter(fn (Technician $technician) => $this->skillMatches($technician, $skill))
            ->sortBy(fn (Technician $technician) => $this->distanceKm($lat, $lon, $technician->latitude, $technician->longitude) ?? PHP_FLOAT_MAX)
            ->first();
    }

    private function skillMatches(Technician $technician, string $skill): bool
    {
        $needle = strtolower(trim($skill));
        if ($needle === '') {
            return true;
        }

        return collect($technician->skills ?? [])
            ->contains(fn ($value) => str_contains(strtolower((string) $value), $needle));
    }

    private function normalizeSkills(mixed $skills): array
    {
        if (is_string($skills)) {
            $skills = explode(',', $skills);
        }

        return collect(is_array($skills) ? $skills : [])
            ->map(fn ($skill) => trim((string) $skill))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function distanceKm(mixed $lat1, mixed $lon1, mixed $lat2, mixed $lon2): ?float
    {
        if (!is_numeric($lat1) || !is_numeric($lon1) || !is_numeric($lat2) || !is_numeric($lon2)) {
            return null;
        }

        $toRad = fn (float $value): float => ($value * pi()) / 180;
        $lat1 = (float) $lat1;
        $lon1 = (float) $lon1;
        $lat2 = (float) $lat2;
        $lon2 = (float) $lon2;
        $deltaLat = $toRad($lat2 - $lat1);
        $deltaLon = $toRad($lon2 - $lon1);
        $a = sin($deltaLat / 2) ** 2 + cos($toRad($lat1)) * cos($toRad($lat2)) * sin($deltaLon / 2) ** 2;

        return 6371 * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    private function userDto(User $user): array
    {
        return [
            'id' => (string) $user->id,
            'role' => $user->role,
            'name' => $user->name,
            'nida' => $user->nida ?? '',
            'nidaFormatted' => $this->formatNida($user->nida ?? ''),
            'email' => $user->email,
            'phone' => $user->phone ?? '',
            'emailVerified' => (bool) $user->email_verified_at,
            'phoneVerified' => (bool) $user->phone_verified_at,
            'blocked' => (bool) $user->blocked,
            'lastLocation' => $user->last_location ?? (object) [],
        ];
    }

    private function technicianDto(Technician $technician, ?float $distance = null): array
    {
        return [
            'id' => (string) $technician->id,
            'userId' => $technician->user_id ? (string) $technician->user_id : '',
            'name' => $technician->name,
            'nida' => $technician->nida ?? '',
            'nidaFormatted' => $this->formatNida($technician->nida ?? ''),
            'phone' => $technician->phone ?? '',
            'email' => $technician->email,
            'image' => $technician->image ?? '',
            'skills' => $technician->skills ?? [],
            'available' => (bool) $technician->available,
            'clientRequestsBlocked' => $this->technicianClientRequestsBlocked($technician),
            'clientRequestsBlockedReason' => $technician->client_requests_blocked_reason ?? '',
            'rating' => (float) $technician->rating,
            'location' => ['latitude' => $technician->latitude, 'longitude' => $technician->longitude],
            'distance' => $distance === null ? null : round($distance, 2),
            'registrationReview' => $this->registrationReviewDto($technician),
            'registrationPayment' => $this->registrationPaymentDto($technician),
            'lastSeenAt' => $this->dateString($technician->last_seen_at),
        ];
    }

    private function registrationReviewDto(Technician $technician): array
    {
        return [
            'status' => $technician->registration_review_status ?? 'approved',
            'note' => $technician->registration_review_note ?? '',
            'reviewedAt' => $this->dateString($technician->registration_reviewed_at),
            'hasNidaIdImage' => filled($technician->nida_id_image),
            'hasFaceImage' => filled($technician->face_image),
        ];
    }

    private function technicianRegistrationApproved(Technician $technician): bool
    {
        if (! Schema::hasColumn('technicians', 'registration_review_status')) {
            return true;
        }

        return ($technician->registration_review_status ?? 'approved') === 'approved';
    }

    private function technicianClientRequestsBlocked(Technician $technician): bool
    {
        if (! Schema::hasColumn('technicians', 'client_requests_blocked')) {
            return false;
        }

        return (bool) $technician->client_requests_blocked;
    }

    private function requestTechnicianRegistrationPayment(Technician $technician): void
    {
        if ($this->registrationPaymentIsActive($technician->registration_payment_status)) {
            return;
        }

        $payerPhone = $this->normalizeTanzaniaPhone((string) $technician->phone);
        if ($this->isMpesaPhoneNumber($payerPhone)) {
            $amount = $this->payments->technicianRegistrationFee();
            $currency = config('services.clickpesa.currency', 'TZS');
            $this->createPaymentAction($technician, [
                'operator' => 'Auto',
                'payer_phone' => $payerPhone,
                'amount' => $amount,
                'currency' => $currency,
                'status' => 'unsupported_payment_operator',
                'error' => 'M-Pesa is not supported for automatic registration fee payment.',
                'requested_at' => now(),
            ]);
            $technician->update([
                'registration_fee_amount' => $amount,
                'registration_fee_currency' => $currency,
                'registration_payment_status' => 'unsupported_payment_operator',
                'registration_payment_response' => [
                    'message' => 'Use Yas, Airtel, or Halopesa for the registration fee payment.',
                    'payerPhone' => $payerPhone,
                ],
                'registration_payment_requested_at' => now(),
            ]);

            return;
        }

        if (! $this->payments->configured()) {
            $technician->update([
                'registration_fee_amount' => $this->payments->technicianRegistrationFee(),
                'registration_fee_currency' => config('services.clickpesa.currency', 'TZS'),
                'registration_payment_status' => 'not_configured',
            ]);

            return;
        }

        $orderReference = $this->payments->newOrderReference($technician->id);
        $actionId = $this->createPaymentAction($technician, [
            'operator' => 'Auto',
            'payer_phone' => $payerPhone,
            'amount' => $this->payments->technicianRegistrationFee(),
            'currency' => config('services.clickpesa.currency', 'TZS'),
            'status' => 'initiated',
            'order_reference' => $orderReference,
            'requested_at' => now(),
        ]);

        try {
            $response = $this->payments->initiateTechnicianRegistrationPayment(
                $payerPhone,
                $orderReference,
            );
            $status = strtolower((string) ($response['status'] ?? 'processing'));

            $this->updatePaymentAction($actionId, [
                'status' => $status,
                'payment_id' => $response['id'] ?? null,
                'response' => $response,
            ]);

            $technician->update([
                'registration_fee_amount' => $this->payments->technicianRegistrationFee(),
                'registration_fee_currency' => config('services.clickpesa.currency', 'TZS'),
                'registration_payment_status' => $status,
                'registration_payment_order_reference' => $response['orderReference'] ?? $orderReference,
                'registration_payment_id' => $response['id'] ?? null,
                'registration_payment_response' => $response,
                'registration_payment_requested_at' => now(),
            ]);
        } catch (Throwable $exception) {
            Log::error('Unable to initiate technician registration payment.', [
                'technician_id' => $technician->id,
                'message' => $exception->getMessage(),
            ]);

            $this->updatePaymentAction($actionId, [
                'status' => 'request_failed',
                'error' => $exception->getMessage(),
            ]);
            $technician->update([
                'registration_fee_amount' => $this->payments->technicianRegistrationFee(),
                'registration_fee_currency' => config('services.clickpesa.currency', 'TZS'),
                'registration_payment_status' => 'request_failed',
                'registration_payment_order_reference' => $orderReference,
                'registration_payment_response' => ['message' => $exception->getMessage()],
                'registration_payment_requested_at' => now(),
            ]);
        }
    }

    private function registrationPaymentIsActive(?string $status): bool
    {
        return in_array($status, ['processing', 'pending', 'success', 'settled'], true);
    }

    private function registrationPaymentIsPaid(?string $status): bool
    {
        return in_array($status, ['success', 'settled'], true);
    }

    private function registrationPaymentCanBeRequested(?string $status): bool
    {
        return in_array($status, [null, '', 'not_requested', 'not_configured', 'request_failed', 'failed'], true);
    }

    private function registrationPaymentDto(Technician $technician, bool $includeActions = false): array
    {
        $actions = $includeActions ? $this->paymentActionsForTechnician($technician) : [];

        return [
            'amount' => (int) ($technician->registration_fee_amount ?? $this->payments->technicianRegistrationFee()),
            'currency' => $technician->registration_fee_currency ?? config('services.clickpesa.currency', 'TZS'),
            'status' => $technician->registration_payment_status ?? 'not_requested',
            'orderReference' => $technician->registration_payment_order_reference ?? '',
            'paymentId' => $technician->registration_payment_id ?? '',
            'supportedOperators' => $this->registrationFeeSupportedOperators(),
            'unsupportedOperatorMessage' => 'Use Yas, Airtel, or Halopesa for the registration fee. M-Pesa is coming soon.',
            'requestedAt' => $this->dateString($technician->registration_payment_requested_at),
            'lastAction' => $actions[0] ?? null,
            'actions' => $actions,
        ];
    }

    private function createPaymentAction(Technician $technician, array $data): ?int
    {
        if (! $this->paymentActionTrackingEnabled()) {
            return null;
        }

        return (int) DB::table('technician_payment_actions')->insertGetId($this->preparePaymentActionData([
            ...$data,
            'technician_id' => $technician->id,
            'user_id' => $technician->user_id,
            'created_at' => now(),
            'updated_at' => now(),
        ]));
    }

    private function updatePaymentAction(?int $actionId, array $data): void
    {
        if ($actionId === null || ! $this->paymentActionTrackingEnabled()) {
            return;
        }

        DB::table('technician_payment_actions')
            ->where('id', $actionId)
            ->update($this->preparePaymentActionData([
                ...$data,
                'updated_at' => now(),
            ]));
    }

    private function preparePaymentActionData(array $data): array
    {
        if (isset($data['response']) && is_array($data['response'])) {
            $data['response'] = json_encode($data['response']);
        }

        return $data;
    }

    private function paymentActionsForTechnician(Technician $technician): array
    {
        if (! $this->paymentActionTrackingEnabled()) {
            return [];
        }

        return DB::table('technician_payment_actions')
            ->where('technician_id', $technician->id)
            ->latest('id')
            ->limit(5)
            ->get()
            ->map(fn ($row) => [
                'id' => (string) $row->id,
                'operator' => $row->operator ?? '',
                'payerPhone' => $row->payer_phone ?? '',
                'amount' => (int) $row->amount,
                'currency' => $row->currency ?? 'TZS',
                'status' => $row->status ?? '',
                'orderReference' => $row->order_reference ?? '',
                'paymentId' => $row->payment_id ?? '',
                'error' => $row->error ?? '',
                'requestedAt' => $this->dateTimeString($row->requested_at),
            ])
            ->all();
    }

    private function paymentActionTrackingEnabled(): bool
    {
        return Schema::hasTable('technician_payment_actions');
    }

    private function audit(Request $request, string $event, array $data = []): void
    {
        $metadata = $data['metadata'] ?? [];
        $payload = [
            'event' => $event,
            'actor_role' => $data['actor_role'] ?? null,
            'actor_user_id' => $data['actor_user_id'] ?? null,
            'actor_technician_id' => $data['actor_technician_id'] ?? null,
            'entity_type' => $data['entity_type'] ?? null,
            'entity_id' => isset($data['entity_id']) ? (string) $data['entity_id'] : null,
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 1000, ''),
            'metadata' => is_array($metadata) ? json_encode($metadata) : null,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if (! Schema::hasTable('audit_logs')) {
            Log::info('Audit event recorded before audit_logs migration.', [
                'event' => $event,
                'entity_type' => $payload['entity_type'],
                'entity_id' => $payload['entity_id'],
                'metadata' => $metadata,
            ]);

            return;
        }

        try {
            DB::table('audit_logs')->insert($payload);
        } catch (Throwable $exception) {
            Log::warning('Unable to write audit log.', [
                'event' => $event,
                'entity_type' => $payload['entity_type'],
                'entity_id' => $payload['entity_id'],
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function notificationSkill(?string $skill): string
    {
        $skill = trim((string) $skill);

        return $skill === '' ? 'service' : $skill;
    }

    private function phoneHint(?string $phone): string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';
        if (strlen($digits) <= 4) {
            return $digits;
        }

        return str_repeat('*', max(strlen($digits) - 4, 0)).substr($digits, -4);
    }

    private function nidaHint(?string $nida): string
    {
        $digits = $this->normalizeNida((string) $nida);
        if (strlen($digits) <= 4) {
            return $digits;
        }

        return str_repeat('*', max(strlen($digits) - 4, 0)).substr($digits, -4);
    }

    private function formatNida(?string $nida): string
    {
        $digits = $this->normalizeNida((string) $nida);
        if (strlen($digits) !== 20) {
            return $digits;
        }

        return substr($digits, 0, 8).'-'
            .substr($digits, 8, 5).'-'
            .substr($digits, 13, 5).'-'
            .substr($digits, 18, 2);
    }

    private function registrationFeeSupportedOperators(): array
    {
        return ['Yas', 'Airtel', 'Halopesa'];
    }

    private function normalizeNida(string $nida): string
    {
        return preg_replace('/\D+/', '', $nida) ?? '';
    }

    private function isValidNida(string $nida): bool
    {
        return (bool) preg_match('/^[0-9]{20}$/', $this->normalizeNida($nida));
    }

    private function validatedImageDataUri(string $value, string $field): string
    {
        $value = trim($value);
        if (! preg_match('/^data:image\/(jpeg|jpg|png);base64,([A-Za-z0-9+\/=\r\n]+)$/', $value, $matches)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                $field => ['Capture a valid image before submitting.'],
            ]);
        }

        $payload = preg_replace('/\s+/', '', $matches[2]) ?? '';
        $bytes = base64_decode($payload, true);
        if ($bytes === false || strlen($bytes) > 1_800_000) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                $field => ['The captured image is too large. Retake it closer and try again.'],
            ]);
        }

        $mime = strtolower($matches[1]) === 'png' ? 'png' : 'jpeg';

        return "data:image/{$mime};base64,".base64_encode($bytes);
    }

    private function normalizeTanzaniaPhone(string $phone): string
    {
        $phone = preg_replace('/\D+/', '', $phone) ?? '';

        if (str_starts_with($phone, '255')) {
            return $phone;
        }

        if (str_starts_with($phone, '0')) {
            return '255'.substr($phone, 1);
        }

        if (strlen($phone) === 9) {
            return '255'.$phone;
        }

        return $phone;
    }

    private function isMpesaPhoneNumber(string $phone): bool
    {
        $phone = $this->normalizeTanzaniaPhone($phone);
        $prefix = substr($phone, 3, 2);

        return in_array($prefix, ['74', '75', '76'], true);
    }

    private function isValidTanzaniaPhone(string $phone): bool
    {
        return (bool) preg_match('/^255[67][0-9]{8}$/', $this->normalizeTanzaniaPhone($phone));
    }

    private function requestDto(ServiceRequest $serviceRequest): array
    {
        return [
            'id' => (string) $serviceRequest->id,
            'client' => $serviceRequest->client ? [
                'id' => (string) $serviceRequest->client->id,
                'name' => $serviceRequest->client->name,
                'phone' => $serviceRequest->client->phone ?? '',
                'email' => $serviceRequest->client->email ?? '',
            ] : '',
            'technician' => $serviceRequest->technician ? $this->technicianDto($serviceRequest->technician, $serviceRequest->distance_km) : '',
            'skill' => $serviceRequest->skill ?? '',
            'description' => $serviceRequest->description ?? '',
            'status' => $serviceRequest->status,
            'clientLocation' => ['latitude' => $serviceRequest->client_latitude, 'longitude' => $serviceRequest->client_longitude],
            'technicianLocationAtRequest' => [
                'latitude' => $serviceRequest->technician_latitude_at_request,
                'longitude' => $serviceRequest->technician_longitude_at_request,
            ],
            'distanceKm' => $serviceRequest->distance_km,
            'responseMessage' => $serviceRequest->response_message ?? '',
            'clientRating' => $serviceRequest->client_rating,
            'clientReport' => $serviceRequest->client_report ?? '',
            'createdAt' => $this->dateString($serviceRequest->created_at),
            'respondedAt' => $this->dateString($serviceRequest->responded_at),
            'completedAt' => $this->dateString($serviceRequest->completed_at),
        ];
    }

    private function dateString(mixed $value): ?string
    {
        return $value instanceof Carbon ? $value->toISOString() : null;
    }

    private function dateTimeString(mixed $value): ?string
    {
        if ($value instanceof Carbon) {
            return $value->toISOString();
        }

        if (blank($value)) {
            return null;
        }

        return Carbon::parse($value)->toISOString();
    }
}
