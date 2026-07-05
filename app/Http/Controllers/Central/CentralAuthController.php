<?php

declare(strict_types=1);

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Models\CentralSetting;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CentralAuthController extends Controller
{
    public function create(): View
    {
        $platformType = CentralSetting::platformSaasType();

        return view('central.auth.login', [
            'platformType' => $platformType,
        ]);
    }

    /**
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $remember = (bool) $request->boolean('remember');

        if (! Auth::guard('central')->attempt($credentials, $remember)) {
            throw ValidationException::withMessages([
                'email' => 'Email atau password salah.',
            ]);
        }

        /** @var User|null $user */
        $user = Auth::guard('central')->user();

        if (! $user?->isActiveUser()) {
            Auth::guard('central')->logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages([
                'email' => 'Akun superadmin ini sedang dinonaktifkan.',
            ]);
        }

        $request->session()->regenerate();

        return redirect()->to('/super-admin/tenants');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('central')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->to('/');
    }
}
