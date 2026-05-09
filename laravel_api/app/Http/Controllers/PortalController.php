<?php

namespace App\Http\Controllers;

use App\Models\ServiceRequest;
use App\Models\Technician;
use App\Models\User;
use App\Services\PasswordResetService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PortalController extends Controller
{
    public function index(): View
    {
        $totalTechnicians = Technician::count();
        $availableTechnicians = Technician::where('available', true)->count();

        return view('portal.index', [
            'stats' => [
                'clients' => User::where('role', 'client')->count(),
                'technicians' => $totalTechnicians,
                'available' => $availableTechnicians,
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
}
