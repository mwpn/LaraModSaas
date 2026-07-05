<?php

declare(strict_types=1);

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Models\CentralSetting;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class SuperAdminProfileController extends Controller
{
    public function edit(): View
    {
        $user = $this->currentUser()->loadMissing('role');
        $platformType = CentralSetting::platformSaasType();
        $experience = CentralSetting::platformExperience($platformType);

        return view('central.profile', [
            'platformType' => $platformType,
            'centralAccent' => CentralSetting::platformBlueprint($platformType)['theme_color'],
            'experience' => $experience,
            'user' => $user,
            'profileSchemaReady' => $this->profileSchemaReady(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $this->currentUser();
        $profileSchemaReady = $this->profileSchemaReady();

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->getKey(), $user->getKeyName()),
            ],
        ];

        if ($profileSchemaReady) {
            $rules['phone_number'] = ['nullable', 'string', 'max:32', 'regex:/^[0-9+\-\s()]{8,32}$/'];
            $rules['avatar'] = ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'];
            $rules['remove_avatar'] = ['nullable', 'boolean'];
        }

        $validated = $request->validate($rules);

        if (! $profileSchemaReady && ($request->hasFile('avatar') || filled($request->input('phone_number')) || $request->boolean('remove_avatar'))) {
            return redirect()
                ->route('central.super-admin.profile.edit')
                ->withErrors([
                    'profile' => 'Kolom profil central belum siap. Jalankan migrasi terbaru dulu sebelum menyimpan nomor HP atau avatar.',
                ])
                ->withInput();
        }

        $payload = [
            'name' => trim((string) $validated['name']),
            'email' => Str::lower(trim((string) $validated['email'])),
        ];

        if ($profileSchemaReady) {
            $normalizedPhone = $this->normalizePhone(data_get($validated, 'phone_number'));
            $avatarPath = $user->avatar_path;
            $shouldRemoveAvatar = $request->boolean('remove_avatar');

            if ($shouldRemoveAvatar && filled($avatarPath)) {
                Storage::disk('public')->delete((string) $avatarPath);
                $avatarPath = null;
            }

            if ($request->hasFile('avatar')) {
                $newAvatarPath = $request->file('avatar')->store('central/avatars', 'public');

                if (filled($avatarPath) && $avatarPath !== $newAvatarPath) {
                    Storage::disk('public')->delete((string) $avatarPath);
                }

                $avatarPath = $newAvatarPath;
            }

            $payload['phone_number'] = $normalizedPhone;
            $payload['avatar_path'] = $avatarPath;
        }

        $user->fill($payload)->save();

        return redirect()
            ->route('central.super-admin.profile.edit')
            ->with('status', 'Profil superadmin berhasil diperbarui.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $user = $this->currentUser();

        $validated = $request->validate([
            'current_password' => ['required', 'current_password:central'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->mixedCase()->numbers()],
        ]);

        $user->forceFill([
            'password' => $validated['password'],
        ])->save();

        return redirect()
            ->route('central.super-admin.profile.edit')
            ->with('password_status', 'Password berhasil diperbarui.');
    }

    protected function currentUser(): User
    {
        /** @var User|null $user */
        $user = Auth::guard('central')->user();

        abort_unless($user instanceof User, 403);

        return $user;
    }

    protected function profileSchemaReady(): bool
    {
        return Schema::hasTable('users')
            && Schema::hasColumn('users', 'phone_number')
            && Schema::hasColumn('users', 'avatar_path');
    }

    protected function normalizePhone(mixed $phoneNumber): ?string
    {
        $normalized = preg_replace('/\s+/', ' ', trim((string) $phoneNumber));

        return filled($normalized) ? $normalized : null;
    }
}
