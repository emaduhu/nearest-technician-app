<?php

namespace App\Http\Controllers;

use App\Models\ServiceRequest;
use App\Models\Technician;
use App\Models\User;
use App\Services\PasswordResetService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PortalController extends Controller
{
    public function home(): RedirectResponse
    {
        return redirect()->route(Auth::check() ? $this->landingRouteFor(Auth::user()) : 'login');
    }

    public function loginForm(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route($this->landingRouteFor(Auth::user()));
        }

        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $remember = $request->boolean('remember');
        if (! Auth::attempt(['email' => strtolower(trim($credentials['email'])), 'password' => $credentials['password']], $remember)) {
            return back()
                ->withErrors(['email' => 'The email or password is incorrect.'])
                ->onlyInput('email');
        }

        if (! in_array(Auth::user()?->role, ['admin', 'dispatcher', 'technician'], true)) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()
                ->withErrors(['email' => 'This account does not have portal access.'])
                ->onlyInput('email');
        }

        $request->session()->regenerate();

        return redirect()->route($this->landingRouteFor(Auth::user()));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    public function dispatch(): View
    {
        $this->ensurePortalAccess();

        $totalTechnicians = Technician::count();
        $availableTechnicians = Technician::where('available', true)->count();

        return view('portal.index', [
            'stats' => [
                'clients' => User::where('role', 'client')->count(),
                'technicians' => $totalTechnicians,
                'available' => $availableTechnicians,
                'unavailable' => max($totalTechnicians - $availableTechnicians, 0),
                'pending' => ServiceRequest::where('status', 'pending')->count(),
                'completed' => ServiceRequest::where('status', 'completed')->count(),
            ],
            'requests' => ServiceRequest::with(['client', 'technician'])
                ->latest()
                ->limit(12)
                ->get(),
            'technicians' => Technician::query()
                ->orderByDesc('last_seen_at')
                ->limit(12)
                ->get(),
            'topSkills' => ServiceRequest::query()
                ->select('skill', DB::raw('count(*) as count'))
                ->where('skill', '!=', '')
                ->groupBy('skill')
                ->orderByDesc('count')
                ->limit(8)
                ->get(),
        ]);
    }

    public function updateTechnicianAvailability(Request $request, Technician $technician): RedirectResponse
    {
        $this->ensurePortalAccess();

        $data = $request->validate([
            'available' => ['required', 'boolean'],
        ]);

        $technician->update([
            'available' => (bool) $data['available'],
            'last_seen_at' => now(),
        ]);

        return redirect()->route('dispatch')->with('status', 'Technician availability updated.');
    }

    public function technicianDashboard(Request $request): View
    {
        $technician = $this->currentTechnician();
        abort_unless($technician, 403);

        return view('portal.technician', [
            'technician' => $technician,
            'stats' => [
                'pending' => ServiceRequest::where('technician_id', $technician->id)->where('status', 'pending')->count(),
                'accepted' => ServiceRequest::where('technician_id', $technician->id)->where('status', 'accepted')->count(),
                'completed' => ServiceRequest::where('technician_id', $technician->id)->where('status', 'completed')->count(),
            ],
            'requests' => ServiceRequest::with('client')
                ->where('technician_id', $technician->id)
                ->latest()
                ->limit(20)
                ->get(),
        ]);
    }

    public function updateOwnAvailability(Request $request): RedirectResponse
    {
        $technician = $this->currentTechnician();
        abort_unless($technician, 403);

        $data = $request->validate([
            'available' => ['required', 'boolean'],
        ]);

        $technician->update([
            'available' => (bool) $data['available'],
            'last_seen_at' => now(),
        ]);

        return redirect()->route('technician.dashboard')->with('status', 'Availability updated.');
    }

    public function users(): View
    {
        $this->ensureAdmin();

        return view('portal.users', [
            'users' => User::with('technician')
                ->orderBy('role')
                ->orderBy('name')
                ->paginate(25),
            'roles' => $this->roles(),
        ]);
    }

    public function storeUser(Request $request): RedirectResponse
    {
        $this->ensureAdmin();

        $data = $request->validate([
            'role' => ['required', Rule::in($this->roles())],
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:80'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6'],
        ]);

        $user = User::create([
            'role' => $data['role'],
            'name' => $data['name'],
            'phone' => $data['phone'] ?? null,
            'email' => strtolower(trim($data['email'])),
            'password' => $data['password'],
        ]);

        $this->syncTechnicianProfile($user, $data['password']);

        return redirect()->route('users.index')->with('status', 'User created.');
    }

    public function updateUser(Request $request, User $user): RedirectResponse
    {
        $this->ensureAdmin();

        $data = $request->validate([
            'role' => ['required', Rule::in($this->roles())],
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:80'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:6'],
        ]);

        $updates = [
            'role' => $data['role'],
            'name' => $data['name'],
            'phone' => $data['phone'] ?? null,
            'email' => strtolower(trim($data['email'])),
        ];

        if (! empty($data['password'])) {
            $updates['password'] = $data['password'];
        }

        $user->update($updates);
        $this->syncTechnicianProfile($user->fresh(), $data['password'] ?? null);

        return redirect()->route('users.index')->with('status', 'User updated.');
    }

    public function destroyUser(Request $request, User $user): RedirectResponse
    {
        $this->ensureAdmin();

        if ((int) $request->user()->id === (int) $user->id) {
            return redirect()->route('users.index')->withErrors(['user' => 'You cannot delete your own account while signed in.']);
        }

        $user->delete();

        return redirect()->route('users.index')->with('status', 'User deleted.');
    }

    public function resetPasswordForm(Request $request): View
    {
        return view('auth.reset-password', [
            'email' => $request->query('email', ''),
            'token' => $request->query('token', ''),
        ]);
    }

    public function resetPassword(Request $request, PasswordResetService $passwordReset): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'token' => ['required', 'string'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ]);

        $passwordReset->reset($data['email'], $data['token'], $data['password']);

        return back()->with('status', 'Your password has been reset. You can sign in from the app.');
    }

    /**
     * @return array<int, string>
     */
    private function roles(): array
    {
        return ['admin', 'dispatcher', 'client', 'technician'];
    }

    private function ensurePortalAccess(): void
    {
        abort_unless(in_array(Auth::user()?->role, ['admin', 'dispatcher'], true), 403);
    }

    private function ensureAdmin(): void
    {
        abort_unless(Auth::user()?->role === 'admin', 403);
    }

    private function landingRouteFor(?User $user): string
    {
        return $user?->role === 'technician' ? 'technician.dashboard' : 'dispatch';
    }

    private function currentTechnician(): ?Technician
    {
        $user = Auth::user();
        if (! $user || $user->role !== 'technician') {
            return null;
        }

        return Technician::where('user_id', $user->id)
            ->orWhere('email', $user->email)
            ->first();
    }

    private function syncTechnicianProfile(User $user, ?string $plainPassword = null): void
    {
        if ($user->role !== 'technician') {
            Technician::where('user_id', $user->id)->update(['user_id' => null]);

            return;
        }

        $attributes = [
            'user_id' => $user->id,
            'name' => $user->name,
            'phone' => $user->phone,
            'email' => $user->email,
            'available' => true,
            'last_seen_at' => now(),
        ];

        if ($plainPassword) {
            $attributes['password'] = Hash::make($plainPassword);
        }

        $technician = Technician::where('user_id', $user->id)
            ->orWhere('email', $user->email)
            ->first();

        if ($technician) {
            $technician->update($attributes);

            return;
        }

        Technician::create($attributes);
    }
}
