<?php

declare(strict_types=1);

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Validation\Rule;

class SuperAdminTenantController extends Controller
{
    public function index(): View
    {
        return view('central.tenants', [
            'tenants' => Tenant::query()
                ->orderBy('name')
                ->orderBy('id')
                ->get(),
            'availableSaasTypes' => $this->availableSaasTypes(),
        ]);
    }

    public function switchSaas(Request $request, string $id): RedirectResponse
    {
        $validated = $request->validate([
            'saas_type' => ['required', 'string', Rule::in($this->availableSaasTypes())],
        ]);

        $tenant = Tenant::query()->findOrFail($id);
        $tenant->forceFill([
            'saas_type' => $validated['saas_type'],
        ])->save();

        return back()->with('status', 'SaaS type tenant berhasil diperbarui.');
    }

    protected function availableSaasTypes(): array
    {
        return [
            'universal',
            'resto',
            'hotel',
            'tirta',
            'netbilling',
        ];
    }
}
