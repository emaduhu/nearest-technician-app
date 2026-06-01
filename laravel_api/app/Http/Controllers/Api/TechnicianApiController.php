<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ServiceRequest;
use App\Models\Technician;
use App\Models\User;
use App\Services\ClickPesaPaymentService;
use App\Services\EmailVerificationService;
use App\Services\FirebasePhoneAuthService;
use App\Services\PasswordResetService;
use App\Services\PushNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Throwable;

class TechnicianApiController extends Controller
{
    public function __construct(
        private readonly PushNotificationService $push,
        private readonly ClickPesaPaymentService $payments,
        private readonly EmailVerificationService $emailVerification,
        private readonly FirebasePhoneAuthService $phoneAuth,
    )
    {
    }

    public function health(): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'database' => config('database.default'),
            'firebasePush' => $this->push->configured(),
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
            'phone' => ['required', 'string', 'max:80'],
            'phoneVerificationIdToken' => ['required', 'string'],
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
        $phone = $this->normalizeTanzaniaPhone((string) $data['phone']);

        if (! $this->isValidTanzaniaPhone($phone)) {
            return response()->json([
                'error' => 'Enter a valid Tanzania mobile phone number.',
                'code' => 'invalid_phone',
            ], 422);
        }

        if ($data['role'] === 'technician' && $phone && $this->isMpesaPhoneNumber($phone)) {
            return response()->json([
                'error' => 'Registration fee supports Yas, Airtel, and Halopesa only. M-Pesa is not supported yet, so please register with a Yas, Airtel, or Halopesa number.',
                'code' => 'unsupported_payment_operator',
                'supportedOperators' => $this->registrationFeeSupportedOperators(),
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

        $firebasePhone = $this->phoneAuth->verifyIdToken((string) $data['phoneVerificationIdToken'], $phone);
        $this->emailVerification->assertVerified($email, (string) $data['emailVerificationCode']);

        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'role' => $data['role'],
                'name' => $data['name'],
                'phone' => $phone,
                'phone_verified_at' => $existingUser?->phone_verified_at ?? now(),
                'firebase_phone_uid' => $firebasePhone['uid'],
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
        if ($data['role'] === 'technician') {
            $technician = Technician::updateOrCreate(
                ['email' => $email],
                [
                    'user_id' => $user->id,
                    'name' => $data['name'],
                    'phone' => $phone,
                    'password' => $data['password'],
                    'device_token' => $deviceToken,
                    'skills' => $this->normalizeSkills($data['skills'] ?? []),
                    'image' => $data['image'] ?? null,
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'available' => true,
                    'last_seen_at' => now(),
                ],
            );
            $this->requestTechnicianRegistrationPayment($technician);

            $payload['technician'] = $this->technicianDto($technician->fresh());
            $payload['registrationPayment'] = $this->registrationPaymentDto($technician->fresh());
        }

        $this->emailVerification->forget($email);

        return response()->json($payload, 201);
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

        return response()->json([
            'ok' => true,
            'message' => 'Email verified successfully.',
        ]);
    }

    public function registrationPaymentStatus(Technician $technician): JsonResponse
    {
        if ($this->registrationPaymentCanBeRequested($technician->registration_payment_status)) {
            $this->requestTechnicianRegistrationPayment($technician);
            $technician = $technician->fresh();
        }

        if (blank($technician->registration_payment_order_reference)) {
            return response()->json([
                'ok' => true,
                'registrationPayment' => $this->registrationPaymentDto($technician),
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
            'registrationPayment' => $this->registrationPaymentDto($technician->fresh()),
        ]);
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
            return response()->json([
                'error' => 'Incorrect email or password',
                'code' => 'invalid_credentials',
            ], 401);
        }
        if ($user->blocked) {
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
        }

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

        $data = $request->validate([
            'lat' => ['required', 'numeric'],
            'lon' => ['required', 'numeric'],
            'available' => ['nullable', 'boolean'],
            'token' => ['nullable', 'string'],
            'deviceToken' => ['nullable', 'string'],
        ]);

        $technician->update([
            'latitude' => (float) $data['lat'],
            'longitude' => (float) $data['lon'],
            'available' => array_key_exists('available', $data) ? (bool) $data['available'] : $technician->available,
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
        $data = $request->validate(['available' => ['required', 'boolean']]);
        $technician->update(['available' => (bool) $data['available'], 'last_seen_at' => now()]);

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

        $technicianTitle = 'New service request';
        $technicianBody = "{$client->name} requested {$serviceRequest->skill}. Open the app to accept or reject.";
        $technicianData = [
            'requestId' => $serviceRequest->id,
            'type' => 'tech_request',
            'clientId' => $client->id,
            'clientName' => $client->name,
            'technicianId' => $technician->id,
            'skill' => $serviceRequest->skill,
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

        $status = $data['status'] === 'declined' ? 'rejected' : $data['status'];
        $serviceRequest->update([
            'status' => $status,
            'response_message' => $data['message'] ?? '',
            'responded_at' => now(),
            'completed_at' => $status === 'completed' ? now() : $serviceRequest->completed_at,
        ]);

        $serviceRequest->load(['client', 'technician']);
        $technicianName = $serviceRequest->technician?->name ?? 'Technician';
        $clientNotificationRequired = in_array($status, ['accepted', 'rejected'], true);
        $notificationTitle = match ($status) {
            'accepted' => 'Technician accepted your request',
            'rejected' => 'Technician rejected your request',
            default => 'Technician response',
        };
        $notificationBody = match ($status) {
            'accepted' => "{$technicianName} accepted your request and is on the way.",
            'rejected' => "{$technicianName} rejected your request. Please choose another technician.",
            default => "{$technicianName} {$status} your request",
        };
        $notificationData = [
            'requestId' => $serviceRequest->id,
            'type' => 'request_response',
            'status' => $status,
            'technicianId' => $serviceRequest->technician_id,
            'technicianName' => $technicianName,
            'title' => $notificationTitle,
            'body' => $notificationBody,
        ];

        $clientToken = $serviceRequest->client?->device_token;
        $pushed = $this->push->send($clientToken, [
            'title' => $notificationTitle,
            'body' => $notificationBody,
        ], $notificationData);

        if ($clientNotificationRequired && ! $pushed) {
            Log::warning('Technician response notification was not delivered to the client.', [
                'request_id' => $serviceRequest->id,
                'client_id' => $serviceRequest->client_id,
                'technician_id' => $serviceRequest->technician_id,
                'status' => $status,
                'has_client_token' => filled($clientToken),
            ]);
        }

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
        return Technician::where('available', true)
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
            'phone' => $technician->phone ?? '',
            'email' => $technician->email,
            'image' => $technician->image ?? '',
            'skills' => $technician->skills ?? [],
            'available' => (bool) $technician->available,
            'rating' => (float) $technician->rating,
            'location' => ['latitude' => $technician->latitude, 'longitude' => $technician->longitude],
            'distance' => $distance === null ? null : round($distance, 2),
            'registrationPayment' => $this->registrationPaymentDto($technician),
            'lastSeenAt' => $this->dateString($technician->last_seen_at),
        ];
    }

    private function requestTechnicianRegistrationPayment(Technician $technician): void
    {
        if ($this->registrationPaymentIsActive($technician->registration_payment_status)) {
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

        try {
            $response = $this->payments->initiateTechnicianRegistrationPayment(
                (string) $technician->phone,
                $orderReference,
            );

            $technician->update([
                'registration_fee_amount' => $this->payments->technicianRegistrationFee(),
                'registration_fee_currency' => config('services.clickpesa.currency', 'TZS'),
                'registration_payment_status' => strtolower((string) ($response['status'] ?? 'processing')),
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

    private function registrationPaymentCanBeRequested(?string $status): bool
    {
        return in_array($status, [null, '', 'not_requested', 'not_configured', 'request_failed', 'failed'], true);
    }

    private function registrationPaymentDto(Technician $technician): array
    {
        return [
            'amount' => (int) ($technician->registration_fee_amount ?? $this->payments->technicianRegistrationFee()),
            'currency' => $technician->registration_fee_currency ?? config('services.clickpesa.currency', 'TZS'),
            'status' => $technician->registration_payment_status ?? 'not_requested',
            'orderReference' => $technician->registration_payment_order_reference ?? '',
            'paymentId' => $technician->registration_payment_id ?? '',
            'supportedOperators' => $this->registrationFeeSupportedOperators(),
            'unsupportedOperatorMessage' => 'Use Yas, Airtel, or Halopesa for the registration fee. M-Pesa is coming soon.',
            'requestedAt' => $this->dateString($technician->registration_payment_requested_at),
        ];
    }

    private function registrationFeeSupportedOperators(): array
    {
        return ['Yas', 'Airtel', 'Halopesa'];
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
}
