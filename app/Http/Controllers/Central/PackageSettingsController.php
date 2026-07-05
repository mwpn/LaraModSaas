<?php

declare(strict_types=1);

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Models\CentralSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PackageSettingsController extends Controller
{
    public function index(): View
    {
        $platformType = CentralSetting::platformSaasType();

        return view('central.packages', $this->packagePageData($platformType));
    }

    public function create(): View
    {
        $platformType = CentralSetting::platformSaasType();

        return view('central.package-form', array_merge(
            $this->packagePageData($platformType),
            [
                'pageMode' => 'create',
                'package' => [
                    'code' => '',
                    'label' => '',
                    'description' => '',
                    'price_monthly' => 0,
                    'billing_cycle' => 'monthly',
                    'enabled' => true,
                    'highlight' => false,
                    'is_default' => count(CentralSetting::packageCatalog($platformType)) === 0,
                    'sort_order' => count(CentralSetting::packageCatalog($platformType)) + 1,
                    'limits' => [
                        'max_admin_users' => null,
                        'max_staff_users' => null,
                        'max_customers' => null,
                        'max_monthly_transactions' => null,
                    ],
                    'features' => [],
                    'modules' => ['BaseFeature'],
                ],
                'formAction' => route('central.super-admin.packages.store'),
                'showCodeField' => true,
                'submitLabel' => 'Simpan Package',
                'pageHeading' => 'Tambah Package',
                'pageDescription' => 'Buat package tenant baru dari halaman kerja khusus.',
                'billingEstimate' => CentralSetting::packageBillingEstimate([
                    'billing_components' => [],
                ]),
            ]
        ));
    }

    public function edit(string $packageCode): View
    {
        $platformType = CentralSetting::platformSaasType();
        $package = CentralSetting::findPackage($packageCode, $platformType);

        if (! $package) {
            abort(404);
        }

        $package['is_default'] = CentralSetting::defaultPackageCode($platformType) === $packageCode;

        return view('central.package-form', array_merge(
            $this->packagePageData($platformType),
            [
                'pageMode' => 'edit',
                'package' => $package,
                'formAction' => route('central.super-admin.packages.update', $packageCode),
                'showCodeField' => false,
                'submitLabel' => 'Update Package',
                'pageHeading' => 'Edit Package',
                'pageDescription' => 'Perbarui limit, fitur, modul, dan pricing package dari halaman khusus.',
                'billingEstimate' => CentralSetting::packageBillingEstimate($package),
            ]
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $platformType = CentralSetting::platformSaasType();
        $validated = $request->validate($this->packageRules());
        $packageCode = $this->normalizedPackageCode(
            (string) ($validated['code'] ?? ''),
            (string) $validated['label']
        );

        $packages = CentralSetting::packageCatalog($platformType);

        if (isset($packages[$packageCode])) {
            return back()->withErrors([
                'code' => 'Kode package sudah dipakai. Gunakan kode lain.',
            ])->withInput();
        }

        $packages[$packageCode] = $this->packagePayload($validated, $packageCode);

        CentralSetting::setPackageCatalog($packages, $platformType);

        if (($validated['make_default'] ?? false) || count($packages) === 1) {
            CentralSetting::setDefaultPackageCode($packageCode, $platformType);
        }

        return redirect()
            ->route('central.super-admin.packages.index')
            ->with('status', 'Package baru berhasil ditambahkan.');
    }

    public function update(Request $request, string $packageCode): RedirectResponse
    {
        $platformType = CentralSetting::platformSaasType();
        $packages = CentralSetting::packageCatalog($platformType);

        if (! isset($packages[$packageCode])) {
            abort(404);
        }

        $validated = $request->validate($this->packageRules(true));
        $packages[$packageCode] = $this->packagePayload($validated, $packageCode);

        CentralSetting::setPackageCatalog($packages, $platformType);

        if (($validated['make_default'] ?? false)) {
            CentralSetting::setDefaultPackageCode($packageCode, $platformType);
        }

        return redirect()
            ->route('central.super-admin.packages.index')
            ->with('status', sprintf('Package %s berhasil diperbarui.', $packages[$packageCode]['label']));
    }

    public function setDefault(string $packageCode): RedirectResponse
    {
        $platformType = CentralSetting::platformSaasType();

        if (! CentralSetting::hasPackage($packageCode, $platformType)) {
            abort(404);
        }

        CentralSetting::setDefaultPackageCode($packageCode, $platformType);

        return redirect()
            ->route('central.super-admin.packages.index')
            ->with('status', sprintf('Package %s sekarang jadi default tenant baru.', ucfirst($packageCode)));
    }

    public function destroy(string $packageCode): RedirectResponse
    {
        $platformType = CentralSetting::platformSaasType();
        $packages = CentralSetting::packageCatalog($platformType);

        if (! isset($packages[$packageCode])) {
            abort(404);
        }

        if (count($packages) <= 1) {
            return back()->withErrors([
                'packages' => 'Minimal harus ada satu package aktif di sistem.',
            ]);
        }

        $deletedLabel = $packages[$packageCode]['label'] ?? ucfirst($packageCode);
        unset($packages[$packageCode]);
        CentralSetting::setPackageCatalog($packages, $platformType);

        if (CentralSetting::defaultPackageCode($platformType) === $packageCode) {
            CentralSetting::setDefaultPackageCode(array_key_first($packages), $platformType);
        }

        return redirect()
            ->route('central.super-admin.packages.index')
            ->with('status', sprintf('Package %s berhasil dihapus.', $deletedLabel));
    }

    protected function packagePageData(string $platformType): array
    {
        return [
            'platformType' => $platformType,
            'packages' => CentralSetting::packageCatalogForView($platformType),
            'featureCatalog' => CentralSetting::packageFeatureCatalog(),
            'moduleCatalog' => CentralSetting::moduleCatalogForView($platformType),
            'billingComponentCatalog' => CentralSetting::packageBillingComponentCatalog($platformType),
            'defaultPackageCode' => CentralSetting::defaultPackageCode($platformType),
            'centralAccent' => CentralSetting::platformBlueprint($platformType)['theme_color'],
        ];
    }

    protected function nullableLimitValue(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return max((int) $value, 1);
    }

    protected function packageRules(bool $isUpdate = false): array
    {
        $featureKeys = array_keys(CentralSetting::packageFeatureCatalog());
        $moduleKeys = array_keys(CentralSetting::moduleCatalog());

        $rules = [
            'label' => ['required', 'string', 'max:60'],
            'description' => ['nullable', 'string', 'max:500'],
            'price_monthly' => ['required', 'integer', 'min:0'],
            'billing_cycle' => ['required', 'string', Rule::in(['monthly', 'quarterly', 'yearly'])],
            'enabled' => ['nullable', 'boolean'],
            'highlight' => ['nullable', 'boolean'],
            'make_default' => ['nullable', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:1', 'max:100'],
            'modules' => ['nullable', 'array'],
            'modules.*' => ['string', Rule::in($moduleKeys)],
        ];

        if (! $isUpdate) {
            $rules['code'] = ['nullable', 'string', 'max:40', 'regex:/^[a-z0-9\-_]+$/'];
        }

        foreach (['max_admin_users', 'max_staff_users', 'max_customers', 'max_monthly_transactions'] as $limitKey) {
            $rules["limits.$limitKey"] = ['nullable', 'integer', 'min:1'];
        }

        foreach ($featureKeys as $featureKey) {
            $rules["features.$featureKey"] = ['nullable', 'boolean'];
        }

        foreach (array_keys(CentralSetting::packageBillingComponentCatalog()) as $componentKey) {
            $rules["billing_components.$componentKey.enabled"] = ['nullable', 'boolean'];
            $rules["billing_components.$componentKey.amount"] = ['nullable', 'integer', 'min:0'];
            $rules["billing_components.$componentKey.sample_qty"] = ['nullable', 'integer', 'min:0'];
            $rules["billing_components.$componentKey.sample_amount"] = ['nullable', 'integer', 'min:0'];
            $rules["billing_components.$componentKey.rate"] = ['nullable', 'numeric', 'min:0'];
        }

        return $rules;
    }

    protected function packagePayload(array $validated, string $packageCode): array
    {
        $featureKeys = array_keys(CentralSetting::packageFeatureCatalog());
        $moduleKeys = array_keys(CentralSetting::moduleCatalog());

        return [
            'code' => $packageCode,
            'label' => trim((string) $validated['label']),
            'description' => trim((string) ($validated['description'] ?? '')),
            'price_monthly' => (int) $validated['price_monthly'],
            'billing_cycle' => $validated['billing_cycle'],
            'enabled' => (bool) ($validated['enabled'] ?? false),
            'highlight' => (bool) ($validated['highlight'] ?? false),
            'sort_order' => (int) $validated['sort_order'],
            'limits' => [
                'max_admin_users' => $this->nullableLimitValue(data_get($validated, 'limits.max_admin_users')),
                'max_staff_users' => $this->nullableLimitValue(data_get($validated, 'limits.max_staff_users')),
                'max_customers' => $this->nullableLimitValue(data_get($validated, 'limits.max_customers')),
                'max_monthly_transactions' => $this->nullableLimitValue(data_get($validated, 'limits.max_monthly_transactions')),
            ],
            'features' => collect($featureKeys)
                ->mapWithKeys(fn (string $featureKey): array => [
                    $featureKey => (bool) data_get($validated, 'features.' . $featureKey, false),
                ])
                ->all(),
            'modules' => array_values(array_unique(array_merge(
                ['BaseFeature'],
                array_values(array_filter(
                    $validated['modules'] ?? [],
                    fn ($module): bool => is_string($module) && in_array($module, $moduleKeys, true)
                ))
            ))),
            'billing_components' => collect(array_keys(CentralSetting::packageBillingComponentCatalog()))
                ->mapWithKeys(function (string $componentKey) use ($validated): array {
                    return [
                        $componentKey => [
                            'enabled' => (bool) data_get($validated, 'billing_components.' . $componentKey . '.enabled', false),
                            'amount' => max((int) data_get($validated, 'billing_components.' . $componentKey . '.amount', 0), 0),
                            'sample_qty' => max((int) data_get($validated, 'billing_components.' . $componentKey . '.sample_qty', 0), 0),
                            'sample_amount' => max((int) data_get($validated, 'billing_components.' . $componentKey . '.sample_amount', 0), 0),
                            'rate' => max((float) data_get($validated, 'billing_components.' . $componentKey . '.rate', 0), 0),
                        ],
                    ];
                })
                ->all(),
        ];
    }

    protected function normalizedPackageCode(string $code, string $label): string
    {
        $source = trim($code) !== '' ? $code : $label;

        return trim((string) Str::of($source)->slug('-')) ?: 'package-' . Str::lower(Str::random(6));
    }
}
