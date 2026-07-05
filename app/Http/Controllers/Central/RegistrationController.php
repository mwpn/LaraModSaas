<?php

declare(strict_types=1);

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Models\CentralSetting;
use App\Models\DemoRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RegistrationController extends Controller
{
    public function create(): View
    {
        $platformType = CentralSetting::platformSaasType();

        return view('central.auth.register', [
            'platformType' => $platformType,
        ]);
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email:rfc,dns', 'max:255'],
            'phone_number' => ['required', 'string', 'min:8', 'max:32'],
        ]);

        $saasType = CentralSetting::platformSaasType();
        $demoRequest = DemoRequest::create([
            'name' => trim((string) $validated['name']),
            'email' => strtolower(trim((string) $validated['email'])),
            'phone_number' => trim((string) $validated['phone_number']),
            'platform_type' => $saasType,
            'status' => 'new',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Request demo berhasil dikirim.',
                'request_id' => $demoRequest->getKey(),
            ], 201);
        }

        return back()->with('status', 'Request demo berhasil dikirim. Tim kami akan menghubungi Anda secepatnya.');
    }
}
