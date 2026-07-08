<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Modules\BaseFeature\Models\TenantSetting;

class TenantDashboardController extends Controller
{
    public function index(): View|RedirectResponse
    {
        if ((string) (tenant('saas_type') ?? '') === 'tirta') {
            /** @var User|null $user */
            $user = Auth::guard('tenant')->user();

            if ($user instanceof User && $user->isMeterReader()) {
                return redirect()->route('tenant.tirta.meter-readings');
            }

            return redirect()->route('tenant.tirta.workspace');
        }

        return view('basefeature::index', [
            'setting' => $this->tenantSetting(),
        ]);
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
