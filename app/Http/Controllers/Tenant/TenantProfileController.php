<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\JobTitle;
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
use Modules\BaseFeature\Models\TenantSetting;

class TenantProfileController extends Controller
{
    public function edit(): View
    {
        $user = $this->currentUser()->loadMissing('role');
        $jobTitleCatalogEnabled = $this->jobTitleCatalogEnabled();

        return view('basefeature::profile', [
            'tenantSetting' => $this->tenantSetting(),
            'user' => $user,
            'profileSchemaReady' => $this->profileSchemaReady(),
            'jobTitleSchemaReady' => $this->jobTitleSchemaReady(),
            'jobTitleCatalogEnabled' => $jobTitleCatalogEnabled,
            'jobTitleCatalogOptions' => $jobTitleCatalogEnabled ? $this->jobTitleCatalogOptions() : collect(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $this->currentUser();
        $profileSchemaReady = $this->profileSchemaReady();
        $jobTitleSchemaReady = $this->jobTitleSchemaReady();

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->getKey(), $user->getKeyName()),
            ],
        ];

        if ($jobTitleSchemaReady) {
            $rules['job_title'] = ['nullable', 'string', 'max:120'];

            if ($this->jobTitleCatalogEnabled()) {
                $options = $this->jobTitleCatalogOptions()->all();

                if ($options !== []) {
                    $rules['job_title'][] = Rule::in($options);
                }
            }
        }

        if ($profileSchemaReady) {
            $rules['phone_number'] = ['nullable', 'string', 'max:32', 'regex:/^[0-9+\-\s()]{8,32}$/'];
            $rules['avatar'] = ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'];
            $rules['remove_avatar'] = ['nullable', 'boolean'];
        }

        $validated = $request->validate($rules);

        if (! $profileSchemaReady && ($request->hasFile('avatar') || filled($request->input('phone_number')) || $request->boolean('remove_avatar'))) {
            return redirect()
                ->route('tenant.profile.edit')
                ->withErrors([
                    'profile' => 'Kolom profil tenant belum siap. Jalankan migrasi tenant terbaru dulu sebelum menyimpan nomor HP atau avatar.',
                ])
                ->withInput();
        }

        $payload = [
            'name' => trim((string) $validated['name']),
            'email' => Str::lower(trim((string) $validated['email'])),
        ];

        if ($jobTitleSchemaReady) {
            $payload['job_title'] = filled($validated['job_title'] ?? null) ? trim((string) $validated['job_title']) : null;
        }

        if ($profileSchemaReady) {
            $normalizedPhone = $this->normalizePhone(data_get($validated, 'phone_number'));
            $avatarPath = $user->avatar_path;
            $shouldRemoveAvatar = $request->boolean('remove_avatar');

            if ($shouldRemoveAvatar && filled($avatarPath)) {
                Storage::disk('public')->delete((string) $avatarPath);
                $avatarPath = null;
            }

            if ($request->hasFile('avatar')) {
                $newAvatarPath = $request->file('avatar')->store(
                    'tenant/' . (string) (tenant('id') ?? 'workspace') . '/avatars',
                    'public'
                );

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
            ->route('tenant.profile.edit')
            ->with('status', 'Profil pengguna berhasil diperbarui.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $user = $this->currentUser();

        $validated = $request->validate([
            'current_password' => ['required', 'current_password:tenant'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->mixedCase()->numbers()],
        ]);

        $user->forceFill([
            'password' => $validated['password'],
        ])->save();

        return redirect()
            ->route('tenant.profile.edit')
            ->with('password_status', 'Password berhasil diperbarui.');
    }

    protected function currentUser(): User
    {
        /** @var User|null $user */
        $user = Auth::guard('tenant')->user();

        abort_unless($user instanceof User, 403);

        return $user;
    }

    protected function profileSchemaReady(): bool
    {
        return Schema::connection('tenant')->hasTable('users')
            && Schema::connection('tenant')->hasColumn('users', 'phone_number')
            && Schema::connection('tenant')->hasColumn('users', 'avatar_path');
    }

    protected function jobTitleSchemaReady(): bool
    {
        return Schema::connection('tenant')->hasTable('users')
            && Schema::connection('tenant')->hasColumn('users', 'job_title');
    }

    protected function userMetaSettingsSchemaReady(): bool
    {
        return Schema::connection('tenant')->hasTable('tenant_settings')
            && Schema::connection('tenant')->hasColumn('tenant_settings', 'use_job_title_master');
    }

    protected function jobTitleCatalogEnabled(): bool
    {
        if (! $this->userMetaSettingsSchemaReady() || ! Schema::connection('tenant')->hasTable('job_titles')) {
            return false;
        }

        return (bool) ($this->tenantSetting()->getAttribute('use_job_title_master') ?? false);
    }

    protected function jobTitleCatalogOptions(): \Illuminate\Support\Collection
    {
        if (! Schema::connection('tenant')->hasTable('job_titles')) {
            return collect();
        }

        return JobTitle::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->pluck('name')
            ->map(fn ($name) => trim((string) $name))
            ->filter(fn ($name) => $name !== '')
            ->values();
    }

    protected function normalizePhone(mixed $phoneNumber): ?string
    {
        $normalized = preg_replace('/\s+/', ' ', trim((string) $phoneNumber));

        return filled($normalized) ? $normalized : null;
    }

    protected function tenantSetting(): TenantSetting
    {
        return TenantSetting::query()->firstOrCreate(
            [],
            [
                'brand_name' => (string) (tenant('name') ?? tenant('id') ?? config('app.name')),
                'description' => 'Landing page tenant belum dikustomisasi.',
                'theme_color' => '#000000',
            ]
        );
    }
}
