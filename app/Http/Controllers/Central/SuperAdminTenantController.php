<?php

declare(strict_types=1);

namespace App\Http\Controllers\Central;

use App\Jobs\SendCentralChannelMessageJob;
use App\Http\Controllers\Controller;
use App\Models\CentralSetting;
use App\Models\DemoRequest;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Central\BillingNotificationService;
use App\Services\Central\CentralAuditLogger;
use App\Services\Central\ManualTransferService;
use App\Services\Central\MessageTemplateRenderer;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class SuperAdminTenantController extends Controller
{
    private const WHATSAPP_GRAPH_BASE_URL = 'https://graph.facebook.com/v23.0';

    public function __construct(
        protected CentralAuditLogger $auditLogger,
        protected BillingNotificationService $billingNotificationService,
        protected ManualTransferService $manualTransferService,
        protected MessageTemplateRenderer $templateRenderer,
    ) {
    }

    public function index(Request $request): View
    {
        $platformSaasType = CentralSetting::platformSaasType();
        $search = trim((string) $request->string('q'));
        $filterSaasType = (string) $request->string('saas_type');

        $tenantQuery = Tenant::query();

        if ($search !== '') {
            $tenantQuery->where(function ($query) use ($search): void {
                $query->where('id', 'like', '%' . $search . '%')
                    ->orWhere('name', 'like', '%' . $search . '%');
            });
        }

        if (in_array($filterSaasType, $this->availableSaasTypes(), true)) {
            $tenantQuery->where(function ($query) use ($filterSaasType): void {
                if ($filterSaasType === 'universal') {
                    $query->where('saas_type', $filterSaasType)
                        ->orWhereNull('saas_type');

                    return;
                }

                $query->where('saas_type', $filterSaasType);
            });
        }

        $allTenants = Tenant::query()
            ->orderBy('name')
            ->orderBy('id')
            ->get();
        $allTenantBillingSummaries = $allTenants->mapWithKeys(function (Tenant $tenant) use ($platformSaasType): array {
            $tenantPackageCode = $this->tenantPackageCode($tenant, $platformSaasType);
            $tenantPackage = CentralSetting::findPackage($tenantPackageCode, $platformSaasType);

            return [
                $tenant->id => $this->tenantBillingSummary($tenant, $tenantPackage),
            ];
        })->all();

        $alignedTenants = $allTenants->filter(
            fn (Tenant $tenant): bool => $this->normalizedTenantSaasType($tenant) === $platformSaasType
        )->count();
        $suspendedTenants = $allTenants->filter(
            fn (Tenant $tenant): bool => $tenant->isSuspended()
        )->count();
        $packageCatalog = CentralSetting::packageCatalog($platformSaasType);
        $tenants = $tenantQuery
            ->orderBy('name')
            ->orderBy('id')
            ->get();
        $tenantBillingSummaries = $tenants->mapWithKeys(fn (Tenant $tenant): array => [
            $tenant->id => $allTenantBillingSummaries[$tenant->id] ?? $this->tenantBillingSummary($tenant, $packageCatalog[$this->tenantPackageCode($tenant, $platformSaasType)] ?? null),
        ])->all();
        $billingDashboard = $this->billingDashboardMetrics($allTenants, $allTenantBillingSummaries);

        return view('central.tenants', [
            'tenants' => $tenants,
            'availableSaasTypes' => $this->availableSaasTypes(),
            'packageCatalog' => $packageCatalog,
            'defaultPackageCode' => CentralSetting::defaultPackageCode(),
            'platformSaasType' => $platformSaasType,
            'filters' => [
                'q' => $search,
                'saas_type' => $filterSaasType,
            ],
            'tenantTotals' => [
                'all' => $allTenants->count(),
                'aligned' => $alignedTenants,
                'mismatch' => max($allTenants->count() - $alignedTenants, 0),
                'active' => max($allTenants->count() - $suspendedTenants, 0),
                'suspended' => $suspendedTenants,
            ],
            'tenantBillingSummaries' => $tenantBillingSummaries,
            'billingDashboard' => $billingDashboard,
            'centralAccent' => '#38bdf8',
        ]);
    }

    public function billingDashboard(): View
    {
        $platformSaasType = CentralSetting::platformSaasType();
        $tenants = Tenant::query()
            ->orderBy('name')
            ->orderBy('id')
            ->get();
        $billingSummaries = $tenants->mapWithKeys(function (Tenant $tenant) use ($platformSaasType): array {
            $tenantPackageCode = $this->tenantPackageCode($tenant, $platformSaasType);
            $tenantPackage = CentralSetting::findPackage($tenantPackageCode, $platformSaasType);

            return [
                $tenant->id => $this->tenantBillingSummary($tenant, $tenantPackage),
            ];
        })->all();
        $billingDashboard = $this->billingDashboardMetrics($tenants, $billingSummaries);
        $tenantRows = $tenants->map(function (Tenant $tenant) use ($billingSummaries): array {
            $summary = $billingSummaries[$tenant->id] ?? [];
            $latestInvoice = data_get($summary, 'latest_invoice');
            $accessBlock = data_get($summary, 'access_block', []);

            return [
                'id' => $tenant->id,
                'name' => (string) ($tenant->name ?? $tenant->id),
                'package_code' => $tenant->packageCode(),
                'subscription_status' => (string) data_get($summary, 'status', 'active'),
                'invoice_number' => (string) data_get($latestInvoice, 'invoice_number', ''),
                'invoice_total' => (int) data_get($latestInvoice, 'invoice_total', 0),
                'invoice_status' => (string) data_get($latestInvoice, 'status', 'issued'),
                'expires_at' => data_get($summary, 'expires_at'),
                'grace_ends_at' => data_get($accessBlock, 'grace_ends_at'),
                'access_label' => (string) data_get($accessBlock, 'label', 'Active'),
                'access_reason' => (string) data_get($accessBlock, 'message', ''),
                'detail_url' => route('central.super-admin.tenants.show', $tenant->id),
            ];
        })->values();
        $recentInvoices = $tenants->flatMap(function (Tenant $tenant) use ($billingSummaries) {
            $summary = $billingSummaries[$tenant->id] ?? [];

            return collect(data_get($summary, 'invoices', []))
                ->map(function (array $invoice) use ($tenant): array {
                    return [
                        'tenant_id' => $tenant->id,
                        'tenant_name' => (string) ($tenant->name ?? $tenant->id),
                        'invoice_number' => (string) ($invoice['invoice_number'] ?? ''),
                        'period_label' => (string) ($invoice['period_label'] ?? ''),
                        'status' => (string) ($invoice['status'] ?? 'issued'),
                        'invoice_total' => (int) ($invoice['invoice_total'] ?? 0),
                        'due_at' => $invoice['due_at'] ?? null,
                        'issued_at' => $invoice['issued_at'] ?? null,
                        'detail_url' => route('central.super-admin.tenants.show', $tenant->id),
                    ];
                });
        })->sortByDesc(fn (array $invoice) => $invoice['issued_at']?->getTimestamp() ?? 0)
            ->take(12)
            ->values();
        $overdueTenants = $tenantRows
            ->filter(fn (array $row): bool => $row['invoice_status'] === 'overdue')
            ->sortBy(fn (array $row) => $row['grace_ends_at']?->getTimestamp() ?? PHP_INT_MAX)
            ->values()
            ->take(8);
        $expiringSoonTenants = $tenantRows
            ->filter(function (array $row): bool {
                $expiresAt = $row['expires_at'] ?? null;

                return $expiresAt instanceof CarbonImmutable
                    && $expiresAt->isFuture()
                    && $expiresAt->diffInDays(CarbonImmutable::now()) <= 7;
            })
            ->sortBy(fn (array $row) => $row['expires_at']?->getTimestamp() ?? PHP_INT_MAX)
            ->values()
            ->take(8);
        $billingAutomation = $this->billingAutomationState();

        return view('central.billing-dashboard', [
            'platformSaasType' => $platformSaasType,
            'billingDashboard' => $billingDashboard,
            'billingAutomation' => $billingAutomation,
            'tenantRows' => $tenantRows,
            'recentInvoices' => $recentInvoices,
            'overdueTenants' => $overdueTenants,
            'expiringSoonTenants' => $expiringSoonTenants,
            'centralAccent' => '#38bdf8',
        ]);
    }

    public function generateDueInvoices(): RedirectResponse
    {
        $result = $this->generateDueInvoicesBatch();

        if ($result['generated_count'] === 0) {
            return back()->with('status', 'Belum ada invoice baru yang jatuh tempo sesuai subscription tenant.');
        }

        return back()->with('status', sprintf(
            '%d invoice berhasil digenerate untuk %d tenant sesuai subscription aktif.',
            $result['generated_count'],
            $result['tenant_count']
        ));
    }

    public function generateDueInvoicesBatch(?int $limitPerTenant = null, string $source = 'manual'): array
    {
        $platformSaasType = CentralSetting::platformSaasType();
        $tenants = Tenant::query()
            ->orderBy('name')
            ->orderBy('id')
            ->get();
        $ranAt = CarbonImmutable::now();
        $generatedCount = 0;
        $tenantCount = 0;
        $generatedInvoices = [];

        foreach ($tenants as $tenant) {
            $packageCode = $this->tenantPackageCode($tenant, $platformSaasType);
            $tenantPackage = CentralSetting::findPackage($packageCode, $platformSaasType);

            if (! is_array($tenantPackage)) {
                continue;
            }

            $records = $this->generateDueInvoiceRecords($tenant, $tenantPackage, $limitPerTenant);

            if ($records === []) {
                continue;
            }

            $generatedCount += count($records);
            $tenantCount++;
            $generatedInvoices[$tenant->id] = array_map(
                fn (array $record): string => (string) ($record['invoice_number'] ?? ''),
                $records
            );
        }

        $result = [
            'ran_at' => $ranAt->toIso8601String(),
            'source' => $source,
            'generated_count' => $generatedCount,
            'tenant_count' => $tenantCount,
            'generated_invoices' => $generatedInvoices,
        ];

        CentralSetting::setJsonSetting(CentralSetting::BILLING_AUTO_GENERATE_STATE_KEY, $result);

        return $result;
    }

    public function scanBillingReminders(string $source = 'manual'): array
    {
        $platformSaasType = CentralSetting::platformSaasType();
        $automationSettings = CentralSetting::automationSettings();
        $reminderDays = max((int) data_get($automationSettings, 'subscription_reminder_days', 7), 1);
        $tenants = Tenant::query()
            ->orderBy('name')
            ->orderBy('id')
            ->get();
        $ranAt = CarbonImmutable::now();
        $overdueTenants = [];
        $expiringSoonTenants = [];

        foreach ($tenants as $tenant) {
            $tenantPackageCode = $this->tenantPackageCode($tenant, $platformSaasType);
            $tenantPackage = CentralSetting::findPackage($tenantPackageCode, $platformSaasType);
            $summary = $this->tenantBillingSummary($tenant, $tenantPackage);
            $collectibleInvoice = data_get($summary, 'collectible_invoice');
            $collectibleStatus = is_array($collectibleInvoice) ? (string) ($collectibleInvoice['status'] ?? '') : '';
            $expiresAt = data_get($summary, 'expires_at');

            if ($collectibleStatus === 'overdue') {
                $overdueTenants[] = [
                    'tenant_id' => $tenant->id,
                    'tenant_name' => (string) ($tenant->name ?? $tenant->id),
                    'invoice_number' => (string) data_get($collectibleInvoice, 'invoice_number', ''),
                    'invoice_total' => (int) data_get($collectibleInvoice, 'invoice_total', 0),
                    'due_at' => data_get($collectibleInvoice, 'due_at') instanceof CarbonImmutable
                        ? data_get($collectibleInvoice, 'due_at')->toIso8601String()
                        : null,
                    'detail_url' => route('central.super-admin.tenants.show', $tenant->id),
                ];
            }

            if (
                $expiresAt instanceof CarbonImmutable
                && $expiresAt->isFuture()
                && $expiresAt->diffInDays($ranAt) <= $reminderDays
            ) {
                $expiringSoonTenants[] = [
                    'tenant_id' => $tenant->id,
                    'tenant_name' => (string) ($tenant->name ?? $tenant->id),
                    'subscription_status' => (string) data_get($summary, 'status', 'active'),
                    'expires_at' => $expiresAt->toIso8601String(),
                    'detail_url' => route('central.super-admin.tenants.show', $tenant->id),
                ];
            }
        }

        $result = [
            'ran_at' => $ranAt->toIso8601String(),
            'source' => $source,
            'reminder_days' => $reminderDays,
            'overdue_count' => count($overdueTenants),
            'expiring_soon_count' => count($expiringSoonTenants),
            'overdue_tenants' => array_slice($overdueTenants, 0, 20),
            'expiring_soon_tenants' => array_slice($expiringSoonTenants, 0, 20),
        ];

        $result['notification_delivery'] = $this->deliverBillingReminderNotifications($result);

        CentralSetting::setJsonSetting(CentralSetting::BILLING_REMINDER_STATE_KEY, $result);

        return $result;
    }

    public function show(string $id): View
    {
        $tenant = Tenant::query()
            ->with('domains')
            ->findOrFail($id);

        $platformSaasType = CentralSetting::platformSaasType();
        $tenantSaasType = $this->normalizedTenantSaasType($tenant);
        $tenantDomains = $this->tenantDomainHosts($tenant);
        $tenantPrimaryUrl = filled($tenantDomains[0] ?? null)
            ? 'https://' . $tenantDomains[0]
            : null;
        $workspaceSnapshot = $this->tenantWorkspaceSnapshot($tenant);
        $tenantPackageCode = $this->tenantPackageCode($tenant, $platformSaasType);
        $packageCatalog = CentralSetting::packageCatalog($platformSaasType);
        $tenantPackage = $packageCatalog[$tenantPackageCode] ?? null;
        $billingSummary = $this->tenantBillingSummary($tenant, $tenantPackage);
        $tenantUserWorkspace = $this->tenantUserWorkspace($tenant);

        return view('central.tenant-detail', [
            'tenant' => $tenant,
            'platformSaasType' => $platformSaasType,
            'tenantSaasType' => $tenantSaasType,
            'tenantStatus' => $tenant->normalizedStatus(),
            'availableSaasTypes' => $this->availableSaasTypes(),
            'tenantDomains' => $tenantDomains,
            'tenantPrimaryUrl' => $tenantPrimaryUrl,
            'tenantDatabaseName' => $this->tenantDatabaseName($tenant),
            'tenantWorkspaceSnapshot' => $workspaceSnapshot,
            'tenantRuntimeModules' => array_values(array_unique(array_merge(
                ['BaseFeature'],
                CentralSetting::runtimeEnabledModules($tenantSaasType)
            ))),
            'tenantBlueprint' => CentralSetting::platformBlueprint($tenantSaasType),
            'packageCatalog' => $packageCatalog,
            'tenantPackageCode' => $tenantPackageCode,
            'tenantPackage' => $tenantPackage,
            'tenantBillingSummary' => $billingSummary,
            'tenantUserWorkspace' => $tenantUserWorkspace,
            'tenantCanDelete' => ! is_array(data_get($billingSummary, 'collectible_invoice')),
            'isAlignedWithPlatform' => $tenantSaasType === $platformSaasType,
            'centralAccent' => (string) ($workspaceSnapshot['theme_color'] ?? '#38bdf8'),
        ]);
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $tenant = Tenant::query()
            ->with('domains')
            ->findOrFail($id);

        $validated = $request->validate([
            'confirm_tenant_id' => ['required', 'string'],
        ]);

        if ((string) $validated['confirm_tenant_id'] !== $tenant->id) {
            throw ValidationException::withMessages([
                'confirm_tenant_id' => 'Ketik tenant ID dengan tepat untuk menghapus tenant ini.',
            ]);
        }

        $this->ensureTenantCanBeDeleted($tenant);

        $tenantId = $tenant->id;
        $tenantName = (string) ($tenant->name ?? $tenantId);
        $tenant->delete();

        DemoRequest::query()
            ->where('converted_tenant_id', $tenantId)
            ->update([
                'converted_tenant_id' => null,
            ]);

        $this->auditLogger->warning(
            'tenant.deleted',
            sprintf('Tenant %s (%s) dihapus permanen.', $tenantName, $tenantId),
            [
                'target_type' => 'tenant',
                'target_id' => (string) $tenantId,
                'meta' => [
                    'tenant_name' => $tenantName,
                    'domains' => $tenant->domains->pluck('domain')->all(),
                ],
            ]
        );

        return redirect()
            ->route('central.super-admin.tenants.index')
            ->with('status', sprintf('Tenant %s (%s) berhasil dihapus permanen.', $tenantName, $tenantId));
    }

    public function storeTenantUser(Request $request, string $id): RedirectResponse
    {
        $tenant = Tenant::query()->findOrFail($id);

        $result = $this->inTenantContext($tenant, function () use ($request): array {
            $this->ensureTenantUserSchemaReadyForWrite();
            $this->ensureTenantUserRoleRecords();

            $roles = $this->tenantUserRoles();
            $validated = $request->validate([
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
                'role_id' => ['required', Rule::in($roles->pluck('id')->all())],
                'is_active' => ['nullable', 'boolean'],
            ]);

            $role = $roles->firstWhere('id', $validated['role_id']);
            $password = Str::password(12);
            $user = User::query()->create([
                'name' => $validated['name'],
                'email' => Str::lower($validated['email']),
                'password' => $password,
                'role_id' => $validated['role_id'],
                'is_active' => (bool) $request->boolean('is_active', true),
            ]);

            return [
                'status' => 'Pengguna tenant berhasil ditambahkan dari panel pusat.',
                'generated_password' => [
                    'action' => 'created',
                    'user_name' => $user->name,
                    'user_email' => $user->email,
                    'role_name' => $role?->name ?? 'User',
                    'password' => $password,
                ],
            ];
        });

        return redirect()
            ->route('central.super-admin.tenants.show', $tenant->id)
            ->with('status', $result['status'])
            ->with('tenant_user_generated_password', $result['generated_password']);
    }

    public function updateTenantUser(Request $request, string $id, string $userId): RedirectResponse
    {
        $tenant = Tenant::query()->findOrFail($id);

        $result = $this->inTenantContext($tenant, function () use ($request, $userId): array {
            $this->ensureTenantUserSchemaReadyForWrite();
            $this->ensureTenantUserRoleRecords();

            $user = $this->tenantUserFind($userId);
            $roles = $this->tenantUserRoles();

            $validated = $request->validate([
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->getKey(), $user->getKeyName())],
                'role_id' => ['required', Rule::in($roles->pluck('id')->all())],
                'is_active' => ['required', 'boolean'],
            ]);

            $role = $roles->firstWhere('id', $validated['role_id']);
            $nextRoleSlug = $role?->slug;
            $nextIsActive = (bool) $validated['is_active'];

            $this->guardTenantUserCriticalChange($user, $nextRoleSlug, $nextIsActive);

            $user->fill([
                'name' => $validated['name'],
                'email' => Str::lower($validated['email']),
                'role_id' => $validated['role_id'],
                'is_active' => $nextIsActive,
            ])->save();

            return [
                'status' => 'Data pengguna tenant berhasil diperbarui dari panel pusat.',
            ];
        });

        return redirect()
            ->route('central.super-admin.tenants.show', $tenant->id)
            ->with('status', $result['status']);
    }

    public function toggleTenantUserActive(string $id, string $userId): RedirectResponse
    {
        $tenant = Tenant::query()->findOrFail($id);

        $result = $this->inTenantContext($tenant, function () use ($userId): array {
            $this->ensureTenantUserSchemaReadyForWrite();

            $user = $this->tenantUserFind($userId);
            $nextIsActive = ! $user->isActiveUser();

            $this->guardTenantUserCriticalChange($user, $user->roleSlug(), $nextIsActive);

            $user->forceFill([
                'is_active' => $nextIsActive,
            ])->save();

            return [
                'status' => $nextIsActive
                    ? 'Pengguna tenant berhasil diaktifkan dari panel pusat.'
                    : 'Pengguna tenant berhasil dinonaktifkan dari panel pusat.',
            ];
        });

        return redirect()
            ->route('central.super-admin.tenants.show', $tenant->id)
            ->with('status', $result['status']);
    }

    public function resetTenantUserPassword(string $id, string $userId): RedirectResponse
    {
        $tenant = Tenant::query()->findOrFail($id);

        $result = $this->inTenantContext($tenant, function () use ($userId): array {
            $user = $this->tenantUserFind($userId);
            $password = Str::password(12);

            $user->forceFill([
                'password' => $password,
            ])->save();

            return [
                'status' => 'Password pengguna tenant berhasil direset dari panel pusat.',
                'generated_password' => [
                    'action' => 'reset',
                    'user_name' => $user->name,
                    'user_email' => $user->email,
                    'role_name' => $user->role?->name ?? 'User',
                    'password' => $password,
                ],
            ];
        });

        return redirect()
            ->route('central.super-admin.tenants.show', $tenant->id)
            ->with('status', $result['status'])
            ->with('tenant_user_generated_password', $result['generated_password']);
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

        $this->auditLogger->info('tenant.saas_switched', 'SaaS type tenant diperbarui.', [
            'target_type' => 'tenant',
            'target_id' => (string) $tenant->id,
            'meta' => ['saas_type' => $validated['saas_type']],
        ]);

        return back()->with('status', 'SaaS type tenant berhasil diperbarui.');
    }

    public function assignPackage(Request $request, string $id): RedirectResponse
    {
        $validated = $request->validate([
            'package_code' => ['required', 'string', Rule::in(array_keys(CentralSetting::packageCatalog()))],
        ]);

        $tenant = Tenant::query()->findOrFail($id);
        $tenant->forceFill([
            'package_code' => $validated['package_code'],
            'package_assigned_at' => now()->toIso8601String(),
            'subscription_status' => data_get($tenant, 'subscription_status', 'active') ?: 'active',
            'subscription_starts_at' => data_get($tenant, 'subscription_starts_at') ?: now()->startOfDay()->toIso8601String(),
            'subscription_expires_at' => data_get($tenant, 'subscription_expires_at')
                ?: $this->subscriptionExpiryForPackage($validated['package_code'])->toIso8601String(),
        ])->save();

        $this->auditLogger->info('tenant.package_assigned', 'Package tenant diperbarui.', [
            'target_type' => 'tenant',
            'target_id' => (string) $tenant->id,
            'meta' => ['package_code' => $validated['package_code']],
        ]);

        return back()->with('status', sprintf('Package tenant %s berhasil diperbarui.', $tenant->id));
    }

    public function updateBilling(Request $request, string $id): RedirectResponse
    {
        $validated = $request->validate([
            'subscription_status' => ['required', 'string', Rule::in(['trial', 'active', 'grace', 'expired'])],
            'subscription_starts_at' => ['nullable', 'date'],
            'subscription_expires_at' => ['nullable', 'date'],
            'subscription_grace_until' => ['nullable', 'date'],
            'billing_usage.customers' => ['nullable', 'integer', 'min:0'],
            'billing_usage.successful_transactions' => ['nullable', 'integer', 'min:0'],
            'billing_usage.checkouts' => ['nullable', 'integer', 'min:0'],
            'billing_usage.transaction_amount' => ['nullable', 'integer', 'min:0'],
        ]);

        $tenant = Tenant::query()->findOrFail($id);
        $tenant->forceFill([
            'subscription_status' => $validated['subscription_status'],
            'subscription_starts_at' => filled($validated['subscription_starts_at'] ?? null)
                ? CarbonImmutable::parse($validated['subscription_starts_at'])->toIso8601String()
                : null,
            'subscription_expires_at' => filled($validated['subscription_expires_at'] ?? null)
                ? CarbonImmutable::parse($validated['subscription_expires_at'])->toIso8601String()
                : null,
            'subscription_grace_until' => filled($validated['subscription_grace_until'] ?? null)
                ? CarbonImmutable::parse($validated['subscription_grace_until'])->toIso8601String()
                : null,
            'billing_usage' => [
                'customers' => max((int) data_get($validated, 'billing_usage.customers', 0), 0),
                'successful_transactions' => max((int) data_get($validated, 'billing_usage.successful_transactions', 0), 0),
                'checkouts' => max((int) data_get($validated, 'billing_usage.checkouts', 0), 0),
                'transaction_amount' => max((int) data_get($validated, 'billing_usage.transaction_amount', 0), 0),
            ],
            'billing_synced_at' => now()->toIso8601String(),
        ])->save();

        $this->auditLogger->info('tenant.billing_updated', 'Billing tenant diperbarui.', [
            'target_type' => 'tenant',
            'target_id' => (string) $tenant->id,
            'meta' => ['subscription_status' => $validated['subscription_status']],
        ]);

        return back()->with('status', sprintf('Billing tenant %s berhasil diperbarui.', $tenant->id));
    }

    public function generateInvoice(string $id): RedirectResponse
    {
        $tenant = Tenant::query()->findOrFail($id);
        $platformSaasType = CentralSetting::platformSaasType();
        $packageCode = $this->tenantPackageCode($tenant, $platformSaasType);
        $tenantPackage = CentralSetting::findPackage($packageCode, $platformSaasType);

        if (! $tenantPackage) {
            return back()->withErrors([
                'invoice' => 'Package tenant tidak ditemukan, invoice belum bisa dibuat.',
            ]);
        }

        $records = $this->generateDueInvoiceRecords($tenant, $tenantPackage, 1);

        if ($records === []) {
            return back()->withErrors([
                'invoice' => sprintf('Belum ada periode tagihan yang jatuh tempo untuk tenant %s sesuai subscription aktif.', $tenant->id),
            ]);
        }

        $this->auditLogger->info('tenant.invoice_generated', 'Invoice tenant berhasil dibuat manual.', [
            'target_type' => 'tenant',
            'target_id' => (string) $tenant->id,
            'meta' => ['invoice_number' => $records[0]['invoice_number']],
        ]);

        return back()->with('status', sprintf(
            'Invoice %s berhasil dibuat untuk tenant %s sesuai periode subscription.',
            $records[0]['invoice_number'],
            $tenant->id
        ));
    }

    public function createInvoiceQris(Request $request, string $id): RedirectResponse
    {
        $validated = $request->validate([
            'invoice_number' => ['required', 'string'],
        ]);

        $tenant = Tenant::query()->findOrFail($id);
        $invoice = collect($tenant->billingInvoices())
            ->firstWhere('invoice_number', $validated['invoice_number']);

        if (! is_array($invoice) || ($invoice['invoice_number'] ?? '') === '') {
            return back()->withErrors([
                'invoice' => 'Invoice tenant tidak ditemukan untuk generate QRIS.',
            ]);
        }

        if (in_array((string) ($invoice['status'] ?? 'issued'), ['paid', 'void'], true)) {
            return back()->withErrors([
                'invoice' => 'Invoice ini tidak bisa dibuatkan QRIS karena statusnya sudah final.',
            ]);
        }

        $amount = (int) ($invoice['invoice_total'] ?? 0);
        if ($amount < 100) {
            return back()->withErrors([
                'invoice' => 'Nominal invoice terlalu kecil untuk QRIS. Minimal Rp100.',
            ]);
        }

        $qrisConfig = $this->qrisConfig();
        if (! $qrisConfig['ready']) {
            return back()->withErrors([
                'invoice' => 'Credential QRIS belum diatur. Isi env INTERACTIVE_QRIS_APIKEY dan INTERACTIVE_QRIS_MID dulu.',
            ]);
        }

        $existingQrisExpiresAt = data_get($invoice, 'payment.qris.expires_at');
        if (
            (string) data_get($invoice, 'payment.method', '') === 'qris'
            && in_array((string) data_get($invoice, 'payment.status', ''), ['pending', 'unpaid'], true)
            && $existingQrisExpiresAt instanceof CarbonImmutable
            && $existingQrisExpiresAt->isFuture()
        ) {
            return back()->with('status', sprintf(
                'QRIS untuk invoice %s masih aktif sampai %s.',
                $invoice['invoice_number'],
                $existingQrisExpiresAt->format('d M Y H:i')
            ));
        }

        $response = Http::timeout(20)
            ->acceptJson()
            ->get($qrisConfig['base_url'] . '/show_qris.php', [
                'do' => 'create-invoice',
                'apikey' => $qrisConfig['apikey'],
                'mID' => $qrisConfig['merchant_id'],
                'cliTrxNumber' => $invoice['invoice_number'],
                'cliTrxAmount' => $amount,
                'useTip' => $qrisConfig['use_tip'],
            ]);

        if (! $response->ok()) {
            return back()->withErrors([
                'invoice' => 'QRIS provider gagal dihubungi. Coba lagi beberapa saat lagi.',
            ]);
        }

        $payload = $response->json();
        if (($payload['status'] ?? 'failed') !== 'success') {
            return back()->withErrors([
                'invoice' => (string) data_get($payload, 'data.qris_status', 'Gagal generate QRIS untuk invoice ini.'),
            ]);
        }

        $requestDate = $this->parseIsoTimestamp((string) data_get($payload, 'data.qris_request_date'))
            ?? CarbonImmutable::now();
        $expiresAt = $requestDate->addMinutes(30);
        $storedInvoice = $this->mutateTenantInvoice($tenant, (string) $invoice['invoice_number'], function (array $record) use ($payload, $requestDate, $expiresAt): array {
            $record['payment'] = [
                'method' => 'qris',
                'status' => 'pending',
                'reference' => (string) data_get($payload, 'data.qris_invoiceid', ''),
                'notes' => 'QRIS dinamis berhasil digenerate dari panel pusat.',
                'paid_via' => '',
                'customer_name' => '',
                'manual_transfer' => data_get($record, 'payment.manual_transfer', []),
                'qris' => [
                    'invoice_id' => (string) data_get($payload, 'data.qris_invoiceid', ''),
                    'content' => (string) data_get($payload, 'data.qris_content', ''),
                    'nmid' => (string) data_get($payload, 'data.qris_nmid', ''),
                    'request_date' => $requestDate,
                    'expires_at' => $expiresAt,
                    'last_checked_at' => null,
                    'raw_status' => 'pending',
                ],
            ];

            return $record;
        });

        if (! is_array($storedInvoice)) {
            return back()->withErrors([
                'invoice' => 'Invoice tenant tidak ditemukan saat menyimpan QRIS.',
            ]);
        }

        $this->auditLogger->info('tenant.qris_created', 'QRIS invoice tenant berhasil dibuat.', [
            'target_type' => 'tenant',
            'target_id' => (string) $tenant->id,
            'meta' => ['invoice_number' => $storedInvoice['invoice_number']],
        ]);

        return back()->with('status', sprintf(
            'QRIS untuk invoice %s berhasil dibuat dan aktif sampai %s.',
            $storedInvoice['invoice_number'],
            $expiresAt->format('d M Y H:i')
        ));
    }

    public function checkInvoiceQrisStatus(Request $request, string $id): RedirectResponse
    {
        $validated = $request->validate([
            'invoice_number' => ['required', 'string'],
        ]);

        $tenant = Tenant::query()->findOrFail($id);
        $invoice = collect($tenant->billingInvoices())
            ->firstWhere('invoice_number', $validated['invoice_number']);

        if (! is_array($invoice)) {
            return back()->withErrors([
                'invoice' => 'Invoice tenant tidak ditemukan.',
            ]);
        }

        $qrisInvoiceId = (string) data_get($invoice, 'payment.qris.invoice_id', '');
        $qrisRequestDate = data_get($invoice, 'payment.qris.request_date');
        $amount = (int) ($invoice['invoice_total'] ?? 0);

        if ($qrisInvoiceId === '' || ! $qrisRequestDate instanceof CarbonImmutable) {
            return back()->withErrors([
                'invoice' => 'QRIS untuk invoice ini belum pernah digenerate.',
            ]);
        }

        $qrisConfig = $this->qrisConfig();
        if (! $qrisConfig['ready']) {
            return back()->withErrors([
                'invoice' => 'Credential QRIS belum diatur. Isi env INTERACTIVE_QRIS_APIKEY dan INTERACTIVE_QRIS_MID dulu.',
            ]);
        }

        $response = Http::timeout(20)
            ->acceptJson()
            ->get($qrisConfig['base_url'] . '/checkpaid_qris.php', [
                'do' => 'checkStatus',
                'apikey' => $qrisConfig['apikey'],
                'mID' => $qrisConfig['merchant_id'],
                'invid' => $qrisInvoiceId,
                'trxvalue' => $amount,
                'trxdate' => $qrisRequestDate->format('Y-m-d'),
            ]);

        if (! $response->ok()) {
            return back()->withErrors([
                'invoice' => 'Gagal cek status QRIS ke provider. Coba lagi beberapa saat.',
            ]);
        }

        $payload = $response->json();
        $qrisStatus = strtolower((string) data_get($payload, 'data.qris_status', 'unpaid'));
        $checkedAt = CarbonImmutable::now();
        $expiresAt = data_get($invoice, 'payment.qris.expires_at');
        $paymentStatus = $qrisStatus === 'paid'
            ? 'paid'
            : (($expiresAt instanceof CarbonImmutable && $expiresAt->isPast()) ? 'expired' : 'pending');

        $storedInvoice = $this->mutateTenantInvoice($tenant, $validated['invoice_number'], function (array $record) use ($payload, $checkedAt, $paymentStatus, $qrisStatus): array {
            $record['payment'] = array_merge(
                $this->normalizeInvoicePaymentForStorage((array) data_get($record, 'payment', [])),
                [
                    'method' => 'qris',
                    'status' => $paymentStatus,
                    'paid_via' => (string) data_get($payload, 'data.qris_payment_methodby', data_get($record, 'payment.paid_via', '')),
                    'customer_name' => (string) data_get($payload, 'data.qris_payment_customername', data_get($record, 'payment.customer_name', '')),
                    'qris' => array_merge(
                        (array) data_get($record, 'payment.qris', []),
                        [
                            'last_checked_at' => $checkedAt,
                            'raw_status' => $qrisStatus,
                        ]
                    ),
                ]
            );

            if ($qrisStatus === 'paid') {
                $record['status'] = 'paid';
                $record['paid_at'] = $checkedAt;
            }

            return $record;
        });

        if (! is_array($storedInvoice)) {
            return back()->withErrors([
                'invoice' => 'Invoice tenant tidak ditemukan saat update status QRIS.',
            ]);
        }

        if ($qrisStatus === 'paid') {
            $this->dispatchPaymentSuccessNotifications($tenant, $storedInvoice);
            $this->auditLogger->info('tenant.qris_paid', 'Pembayaran QRIS tenant terkonfirmasi paid.', [
                'target_type' => 'tenant',
                'target_id' => (string) $tenant->id,
                'meta' => ['invoice_number' => $storedInvoice['invoice_number']],
            ]);

            return back()->with('status', sprintf(
                'Pembayaran QRIS invoice %s sudah terkonfirmasi paid.',
                $storedInvoice['invoice_number']
            ));
        }

        return back()->with('status', sprintf(
            'Status QRIS invoice %s masih %s.',
            $storedInvoice['invoice_number'],
            strtoupper($paymentStatus)
        ));
    }

    public function markInvoiceManualTransferPaid(Request $request, string $id): RedirectResponse
    {
        $validated = $request->validate([
            'invoice_number' => ['required', 'string'],
        ]);

        $tenant = Tenant::query()->findOrFail($id);
        $transferConfig = $this->manualTransferConfig();
        $updatedAt = CarbonImmutable::now();
        $storedInvoice = $this->mutateTenantInvoice($tenant, $validated['invoice_number'], function (array $record) use ($transferConfig, $updatedAt): array {
            $manualTransfer = array_merge(
                $this->manualTransferPaymentPayload(
                    (int) data_get($record, 'payment.manual_transfer.base_amount', data_get($record, 'invoice_total', 0))
                ),
                (array) data_get($record, 'payment.manual_transfer', [])
            );

            $manualTransfer['bank_name'] = (string) ($transferConfig['bank_name'] ?? '');
            $manualTransfer['account_name'] = (string) ($transferConfig['account_name'] ?? '');
            $manualTransfer['account_number'] = (string) ($transferConfig['account_number'] ?? '');
            $manualTransfer['matched_by'] = 'manual_admin_confirm';
            $manualTransfer['matched_at'] = $updatedAt;

            $record['status'] = 'paid';
            $record['paid_at'] = $updatedAt;
            $record['payment'] = [
                'method' => 'manual_transfer',
                'status' => 'paid',
                'reference' => (string) $record['invoice_number'],
                'notes' => 'Pembayaran transfer manual dikonfirmasi dari panel pusat.',
                'paid_via' => trim((string) ($transferConfig['bank_name'] ?? '')),
                'customer_name' => '',
                'manual_transfer' => $manualTransfer,
                'qris' => data_get($record, 'payment.qris', []),
            ];

            return $record;
        });

        if (! is_array($storedInvoice)) {
            return back()->withErrors([
                'invoice' => 'Invoice tenant tidak ditemukan.',
            ]);
        }

        $this->dispatchPaymentSuccessNotifications($tenant, $storedInvoice);
        $this->auditLogger->info('tenant.manual_transfer_paid', 'Invoice tenant ditandai paid via transfer manual.', [
            'target_type' => 'tenant',
            'target_id' => (string) $tenant->id,
            'meta' => ['invoice_number' => $storedInvoice['invoice_number']],
        ]);

        return back()->with('status', sprintf(
            'Invoice %s berhasil ditandai paid via transfer manual.',
            $storedInvoice['invoice_number']
        ));
    }

    public function updateInvoiceStatus(Request $request, string $id): RedirectResponse
    {
        $validated = $request->validate([
            'invoice_number' => ['required', 'string'],
            'status' => ['required', 'string', Rule::in(['issued', 'paid', 'void'])],
        ]);

        $tenant = Tenant::query()->findOrFail($id);
        $updated = false;
        $updatedAt = CarbonImmutable::now();

        $billingInvoices = collect($tenant->billingInvoices())
            ->map(function (array $invoice) use ($validated, $updatedAt, &$updated): array {
                if ($invoice['invoice_number'] !== $validated['invoice_number']) {
                    return $this->normalizeInvoiceRecordForStorage($invoice);
                }

                $updated = true;
                $invoice['status'] = $validated['status'];
                $invoice['paid_at'] = $validated['status'] === 'paid' ? $updatedAt : null;

                if ($validated['status'] !== 'paid') {
                    $invoice['payment']['status'] = $validated['status'] === 'void' ? 'void' : (string) data_get($invoice, 'payment.status', '');
                }

                return $this->normalizeInvoiceRecordForStorage($invoice);
            })
            ->all();

        if (! $updated) {
            return back()->withErrors([
                'invoice' => 'Invoice tenant tidak ditemukan.',
            ]);
        }

        $tenant->forceFill([
            'billing_invoices' => $billingInvoices,
            'last_invoice_status_updated_at' => $updatedAt->toIso8601String(),
        ])->save();

        if ($validated['status'] === 'paid') {
            $invoice = collect($tenant->billingInvoices())
                ->firstWhere('invoice_number', $validated['invoice_number']);

            if (is_array($invoice)) {
                $this->dispatchPaymentSuccessNotifications($tenant, $invoice);
            }
        }

        $this->auditLogger->info('tenant.invoice_status_updated', 'Status invoice tenant diperbarui.', [
            'target_type' => 'tenant',
            'target_id' => (string) $tenant->id,
            'meta' => [
                'invoice_number' => $validated['invoice_number'],
                'status' => $validated['status'],
            ],
        ]);

        return back()->with('status', sprintf('Status invoice %s berhasil diubah menjadi %s.', $validated['invoice_number'], strtoupper($validated['status'])));
    }

    public function suspend(string $id): RedirectResponse
    {
        $tenant = Tenant::query()->findOrFail($id);

        if ($tenant->isSuspended()) {
            return back()->with('status', sprintf('Tenant %s sudah dalam status suspend.', $tenant->id));
        }

        $tenant->forceFill([
            'status' => 'suspended',
            'suspended_at' => now()->toIso8601String(),
        ])->save();

        $this->auditLogger->warning('tenant.suspended', 'Tenant disuspend manual dari panel pusat.', [
            'target_type' => 'tenant',
            'target_id' => (string) $tenant->id,
        ]);

        return back()->with('status', sprintf('Tenant %s berhasil disuspend.', $tenant->id));
    }

    public function activate(string $id): RedirectResponse
    {
        $tenant = Tenant::query()->findOrFail($id);

        if (! $tenant->isSuspended()) {
            return back()->with('status', sprintf('Tenant %s sudah aktif.', $tenant->id));
        }

        $tenant->forceFill([
            'status' => 'active',
            'suspended_at' => null,
        ])->save();

        $this->auditLogger->info('tenant.activated', 'Tenant diaktifkan kembali dari panel pusat.', [
            'target_type' => 'tenant',
            'target_id' => (string) $tenant->id,
        ]);

        return back()->with('status', sprintf('Tenant %s berhasil diaktifkan kembali.', $tenant->id));
    }

    public function syncPlatform(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'sync_branding' => ['nullable', 'boolean'],
        ]);

        $platformSaasType = CentralSetting::platformSaasType();
        $blueprint = CentralSetting::platformBlueprint($platformSaasType);
        $shouldSyncBranding = (bool) ($validated['sync_branding'] ?? false);
        $updatedCount = 0;
        $brandingCount = 0;

        $tenants = Tenant::query()->orderBy('id')->get();

        foreach ($tenants as $tenant) {
            if ($this->normalizedTenantSaasType($tenant) !== $platformSaasType) {
                $tenant->forceFill([
                    'saas_type' => $platformSaasType,
                ])->save();

                $updatedCount++;
            }

            if ($shouldSyncBranding && $this->syncTenantBranding($tenant, $blueprint)) {
                $brandingCount++;
            }
        }

        $message = $updatedCount > 0
            ? sprintf('%d tenant lama berhasil disinkronkan ke mode %s.', $updatedCount, ucfirst($platformSaasType))
            : sprintf('Semua tenant sudah mengikuti mode %s.', ucfirst($platformSaasType));

        if ($shouldSyncBranding) {
            $message .= sprintf(' Branding dasar diperbarui pada %d tenant.', $brandingCount);
        }

        return back()->with('status', $message);
    }

    protected function availableSaasTypes(): array
    {
        return CentralSetting::availablePlatformTypes();
    }

    protected function normalizedTenantSaasType(Tenant $tenant): string
    {
        $saasType = (string) data_get($tenant, 'saas_type', 'universal');

        return in_array($saasType, $this->availableSaasTypes(), true)
            ? $saasType
            : 'universal';
    }

    protected function tenantPackageCode(Tenant $tenant, ?string $platformType = null): string
    {
        $platformType ??= CentralSetting::platformSaasType();
        $packageCatalog = CentralSetting::packageCatalog($platformType);
        $packageCode = $tenant->packageCode();

        return is_string($packageCode) && isset($packageCatalog[$packageCode])
            ? $packageCode
            : CentralSetting::defaultPackageCode($platformType);
    }

    protected function tenantBillingSummary(Tenant $tenant, ?array $tenantPackage = null): array
    {
        $usage = $tenant->billingUsageSnapshot();
        $invoices = $tenant->billingInvoices();
        $invoice = $tenantPackage
            ? CentralSetting::packageBillingInvoice(
                $tenantPackage,
                $usage,
                ! filled(data_get($tenant, 'first_invoice_issued_at'))
            )
            : [
                'usage' => $usage,
                'lines' => [],
                'setup_fee' => 0,
                'monthly_total' => 0,
                'invoice_total' => 0,
            ];

        return [
            'status' => $tenant->subscriptionStatus(),
            'starts_at' => $tenant->subscriptionStartsAt(),
            'expires_at' => $tenant->subscriptionExpiresAt(),
            'grace_until' => $tenant->subscriptionGraceUntil(),
            'access_block' => $tenant->accessBlockMeta(),
            'usage' => $usage,
            'invoice' => $invoice,
            'invoices' => $invoices,
            'latest_invoice' => $invoices[0] ?? null,
            'collectible_invoice' => $tenant->oldestCollectibleInvoice(),
            'last_synced_at' => $this->parseIsoTimestamp(data_get($tenant, 'billing_synced_at')),
        ];
    }

    protected function ensureTenantCanBeDeleted(Tenant $tenant): void
    {
        $collectibleInvoice = $tenant->oldestCollectibleInvoice();

        if (is_array($collectibleInvoice)) {
            throw ValidationException::withMessages([
                'tenant' => sprintf(
                    'Tenant %s masih punya invoice collectible %s dengan status %s. Void atau selesaikan invoice tersebut dulu sebelum hapus tenant.',
                    $tenant->id,
                    (string) ($collectibleInvoice['invoice_number'] ?? '-'),
                    strtoupper((string) ($collectibleInvoice['status'] ?? 'issued'))
                ),
            ]);
        }

        $paidInvoice = collect($tenant->billingInvoices())
            ->first(fn (array $invoice): bool => (string) ($invoice['status'] ?? '') === 'paid');

        if (is_array($paidInvoice)) {
            throw ValidationException::withMessages([
                'tenant' => sprintf(
                    'Tenant %s punya histori invoice paid %s. Demi keamanan audit, tenant berinvoice paid tidak bisa dihapus permanen langsung.',
                    $tenant->id,
                    (string) ($paidInvoice['invoice_number'] ?? '-')
                ),
            ]);
        }
    }

    protected function generateDueInvoiceRecords(Tenant $tenant, array $tenantPackage, ?int $limit = null): array
    {
        $periodStarts = $this->dueInvoicePeriods($tenant, $tenantPackage);

        if ($periodStarts === []) {
            return [];
        }

        if (is_int($limit) && $limit > 0) {
            $periodStarts = array_slice($periodStarts, 0, $limit);
        }

        $existingInvoices = $tenant->billingInvoices();
        $sequence = $tenant->invoiceSequence();
        $records = [];
        $issuedAt = CarbonImmutable::now();
        $shouldIncludeSetupFee = ! filled(data_get($tenant, 'first_invoice_issued_at'));
        $reservedExpectedAmounts = [];

        foreach ($periodStarts as $periodStart) {
            $sequence++;
            $record = $this->makeInvoiceRecord(
                $tenant,
                $tenantPackage,
                $periodStart,
                $issuedAt,
                $sequence,
                $shouldIncludeSetupFee,
                $reservedExpectedAmounts
            );
            $records[] = $record;
            array_unshift($existingInvoices, $this->normalizeInvoiceRecordForStorage($record));
            $expectedAmount = (int) data_get($record, 'payment.manual_transfer.expected_amount', 0);
            if ($expectedAmount > 0) {
                $reservedExpectedAmounts[] = $expectedAmount;
            }
            $shouldIncludeSetupFee = false;
        }

        $tenant->forceFill([
            'invoice_sequence' => $sequence,
            'billing_invoices' => array_values(array_map(
                fn (array $invoice): array => $this->normalizeInvoiceRecordForStorage($invoice),
                $existingInvoices
            )),
            'last_invoice_generated_at' => $issuedAt->toIso8601String(),
            'first_invoice_issued_at' => data_get($tenant, 'first_invoice_issued_at') ?: $issuedAt->toIso8601String(),
        ])->save();

        return $records;
    }

    protected function dueInvoicePeriods(Tenant $tenant, array $tenantPackage, ?CarbonImmutable $asOf = null): array
    {
        $asOf ??= CarbonImmutable::now();

        if ($tenant->isSuspended()) {
            return [];
        }

        // Use computed status so expiry/grace logic on the tenant model is respected.
        $subscriptionStatus = $tenant->subscriptionStatus();

        if (! in_array($subscriptionStatus, ['trial', 'active', 'grace'], true)) {
            return [];
        }

        $subscriptionStartsAt = $tenant->subscriptionStartsAt();

        if (! $subscriptionStartsAt instanceof CarbonImmutable) {
            return [];
        }

        $subscriptionExpiresAt = $tenant->subscriptionExpiresAt();
        $windowEnd = $subscriptionExpiresAt instanceof CarbonImmutable && $subscriptionExpiresAt->lessThan($asOf)
            ? $subscriptionExpiresAt
            : $asOf;

        if ($subscriptionStartsAt->greaterThan($windowEnd)) {
            return [];
        }

        $cycleMonths = $this->billingCycleMonths($tenantPackage);
        $periodStarts = [];
        $cursor = $subscriptionStartsAt->startOfDay();
        $existingPeriodKeys = collect($tenant->billingInvoices())
            ->filter(fn (array $invoice): bool => in_array((string) ($invoice['status'] ?? 'issued'), ['draft', 'issued', 'paid', 'overdue'], true))
            ->pluck('period_key')
            ->filter(fn ($periodKey): bool => is_string($periodKey) && $periodKey !== '')
            ->all();

        while ($cursor->lessThanOrEqualTo($windowEnd)) {
            $periodKey = $this->invoicePeriodKey($cursor, $cycleMonths);

            if (! in_array($periodKey, $existingPeriodKeys, true)) {
                $periodStarts[] = $cursor;
            }

            $cursor = $cursor->addMonthsNoOverflow($cycleMonths);
        }

        return $periodStarts;
    }

    protected function makeInvoiceRecord(
        Tenant $tenant,
        array $tenantPackage,
        CarbonImmutable $periodStart,
        CarbonImmutable $issuedAt,
        int $sequence,
        bool $includeSetupFee,
        array $reservedExpectedAmounts = []
    ): array {
        $cycleMonths = $this->billingCycleMonths($tenantPackage);
        $periodEnd = $periodStart->addMonthsNoOverflow($cycleMonths)->subSecond();
        $invoice = CentralSetting::packageBillingInvoice(
            $tenantPackage,
            $tenant->billingUsageSnapshot(),
            $includeSetupFee
        );
        $baseAmount = (int) data_get($invoice, 'invoice_total', 0);

        return [
            'invoice_number' => $this->tenantInvoiceNumber($tenant, $sequence, $issuedAt),
            'period_key' => $this->invoicePeriodKey($periodStart, $cycleMonths),
            'period_label' => $this->invoicePeriodLabel($periodStart, $periodEnd, $cycleMonths),
            'package_code' => $this->tenantPackageCode($tenant),
            'status' => 'issued',
            'currency' => 'IDR',
            'invoice_total' => (int) data_get($invoice, 'invoice_total', 0),
            'monthly_total' => (int) data_get($invoice, 'monthly_total', 0),
            'setup_fee' => (int) data_get($invoice, 'setup_fee', 0),
            'usage' => data_get($invoice, 'usage', []),
            'lines' => data_get($invoice, 'lines', []),
            'issued_at' => $issuedAt,
            'due_at' => $issuedAt->addDays(7)->endOfDay(),
            'paid_at' => null,
            'created_at' => $issuedAt,
            'payment' => [
                'method' => '',
                'status' => '',
                'reference' => '',
                'notes' => '',
                'paid_via' => '',
                'customer_name' => '',
                'manual_transfer' => $this->manualTransferPaymentPayload($baseAmount, $reservedExpectedAmounts),
                'qris' => [],
            ],
        ];
    }

    protected function normalizeInvoiceRecordForStorage(array $invoice): array
    {
        return [
            'invoice_number' => (string) ($invoice['invoice_number'] ?? ''),
            'period_key' => (string) ($invoice['period_key'] ?? ''),
            'period_label' => (string) ($invoice['period_label'] ?? ''),
            'package_code' => (string) ($invoice['package_code'] ?? ''),
            'status' => (string) ($invoice['status'] ?? 'issued'),
            'currency' => (string) ($invoice['currency'] ?? 'IDR'),
            'invoice_total' => (int) ($invoice['invoice_total'] ?? 0),
            'monthly_total' => (int) ($invoice['monthly_total'] ?? 0),
            'setup_fee' => (int) ($invoice['setup_fee'] ?? 0),
            'usage' => is_array($invoice['usage'] ?? null) ? $invoice['usage'] : [],
            'lines' => is_array($invoice['lines'] ?? null) ? $invoice['lines'] : [],
            'issued_at' => $invoice['issued_at'] instanceof CarbonImmutable ? $invoice['issued_at']->toIso8601String() : $invoice['issued_at'],
            'due_at' => $invoice['due_at'] instanceof CarbonImmutable ? $invoice['due_at']->toIso8601String() : $invoice['due_at'],
            'paid_at' => $invoice['paid_at'] instanceof CarbonImmutable ? $invoice['paid_at']->toIso8601String() : $invoice['paid_at'],
            'created_at' => $invoice['created_at'] instanceof CarbonImmutable ? $invoice['created_at']->toIso8601String() : $invoice['created_at'],
            'payment' => $this->normalizeInvoicePaymentForStorage(is_array($invoice['payment'] ?? null) ? $invoice['payment'] : []),
        ];
    }

    protected function billingDashboardMetrics(iterable $tenants, array $billingSummaries): array
    {
        $blockedCount = 0;
        $subscriptionBlockedCount = 0;
        $invoiceBlockedCount = 0;
        $outstandingTotal = 0;
        $projectedMonthlyTotal = 0;
        $latestPaidTotal = 0;
        $invoiceIssuedCount = 0;
        $invoicePaidCount = 0;
        $invoiceOverdueCount = 0;
        $expiringSoonCount = 0;
        $watchlist = [];
        $now = CarbonImmutable::now();

        foreach ($tenants as $tenant) {
            $summary = $billingSummaries[$tenant->id] ?? null;

            if (! is_array($summary)) {
                continue;
            }

            $latestInvoice = $summary['latest_invoice'] ?? null;
            $accessBlock = $summary['access_block'] ?? [];
            $projectedMonthlyTotal += (int) data_get($summary, 'invoice.monthly_total', 0);

            if ($tenant->hasAccessBlock()) {
                $blockedCount++;

                if (($accessBlock['reason'] ?? null) === 'subscription_expired') {
                    $subscriptionBlockedCount++;
                }

                if (($accessBlock['reason'] ?? null) === 'invoice_overdue') {
                    $invoiceBlockedCount++;
                }
            }

            if (is_array($latestInvoice)) {
                $latestStatus = (string) ($latestInvoice['status'] ?? 'issued');

                if (in_array($latestStatus, ['issued', 'overdue'], true)) {
                    $outstandingTotal += (int) ($latestInvoice['invoice_total'] ?? 0);
                }

                if ($latestStatus === 'paid') {
                    $latestPaidTotal += (int) ($latestInvoice['invoice_total'] ?? 0);
                }
            }

            foreach (($summary['invoices'] ?? []) as $invoice) {
                $status = (string) ($invoice['status'] ?? 'issued');

                if (in_array($status, ['issued', 'overdue'], true)) {
                    $invoiceIssuedCount++;
                }

                if ($status === 'paid') {
                    $invoicePaidCount++;
                }

                if ($status === 'overdue') {
                    $invoiceOverdueCount++;
                }
            }

            $expiresAt = $summary['expires_at'] ?? null;
            if ($expiresAt instanceof CarbonImmutable && $expiresAt->isFuture() && $expiresAt->diffInDays($now) <= 7) {
                $expiringSoonCount++;
            }

            if ($tenant->hasAccessBlock() || (($latestInvoice['status'] ?? null) === 'overdue')) {
                $watchlist[] = [
                    'tenant_id' => $tenant->id,
                    'tenant_name' => (string) ($tenant->name ?? $tenant->id),
                    'block_label' => (string) ($accessBlock['label'] ?? 'Active'),
                    'block_reason' => (string) ($accessBlock['message'] ?? ''),
                    'invoice_number' => (string) data_get($latestInvoice, 'invoice_number', data_get($summary, 'collectible_invoice.invoice_number', '')),
                    'invoice_total' => (int) data_get($latestInvoice, 'invoice_total', 0),
                    'invoice_status' => (string) data_get($latestInvoice, 'status', 'issued'),
                    'grace_ends_at' => data_get($accessBlock, 'grace_ends_at'),
                    'detail_url' => route('central.super-admin.tenants.show', $tenant->id),
                ];
            }
        }

        usort($watchlist, static function (array $left, array $right): int {
            $leftGrace = $left['grace_ends_at'] instanceof CarbonImmutable ? $left['grace_ends_at']->getTimestamp() : PHP_INT_MAX;
            $rightGrace = $right['grace_ends_at'] instanceof CarbonImmutable ? $right['grace_ends_at']->getTimestamp() : PHP_INT_MAX;

            return $leftGrace <=> $rightGrace;
        });

        return [
            'projected_monthly_total' => $projectedMonthlyTotal,
            'outstanding_total' => $outstandingTotal,
            'latest_paid_total' => $latestPaidTotal,
            'blocked_count' => $blockedCount,
            'subscription_blocked_count' => $subscriptionBlockedCount,
            'invoice_blocked_count' => $invoiceBlockedCount,
            'invoice_issued_count' => $invoiceIssuedCount,
            'invoice_paid_count' => $invoicePaidCount,
            'invoice_overdue_count' => $invoiceOverdueCount,
            'expiring_soon_count' => $expiringSoonCount,
            'watchlist' => array_slice($watchlist, 0, 5),
        ];
    }

    protected function subscriptionExpiryForPackage(string $packageCode): CarbonImmutable
    {
        $package = CentralSetting::findPackage($packageCode);
        $start = CarbonImmutable::now()->startOfDay();
        $cycleMonths = $this->billingCycleMonths(is_array($package) ? $package : []);

        return $start->addMonthsNoOverflow($cycleMonths)->subSecond();
    }

    protected function billingCycleMonths(array $package): int
    {
        return match ((string) data_get($package, 'billing_cycle', 'monthly')) {
            'yearly' => 12,
            'quarterly' => 3,
            default => 1,
        };
    }

    protected function invoicePeriodKey(CarbonImmutable $periodStart, int $cycleMonths): string
    {
        return sprintf('%s-%sm', $periodStart->format('Y-m-d'), $cycleMonths);
    }

    protected function invoicePeriodLabel(CarbonImmutable $periodStart, CarbonImmutable $periodEnd, int $cycleMonths): string
    {
        if ($cycleMonths === 1) {
            return $periodStart->translatedFormat('F Y');
        }

        return sprintf(
            '%s - %s',
            $periodStart->translatedFormat('d M Y'),
            $periodEnd->translatedFormat('d M Y')
        );
    }

    protected function mutateTenantInvoice(Tenant $tenant, string $invoiceNumber, callable $mutator): ?array
    {
        $updated = null;
        $billingInvoices = collect($tenant->billingInvoices())
            ->map(function (array $invoice) use ($invoiceNumber, $mutator, &$updated): array {
                if ($invoice['invoice_number'] !== $invoiceNumber) {
                    return $this->normalizeInvoiceRecordForStorage($invoice);
                }

                $mutated = $mutator($invoice);
                $updated = $mutated;

                return $this->normalizeInvoiceRecordForStorage($mutated);
            })
            ->all();

        if (! is_array($updated)) {
            return null;
        }

        $tenant->forceFill([
            'billing_invoices' => $billingInvoices,
            'last_invoice_status_updated_at' => CarbonImmutable::now()->toIso8601String(),
        ])->save();

        return $updated;
    }

    protected function normalizeInvoicePaymentForStorage(array $payment): array
    {
        return [
            'method' => (string) ($payment['method'] ?? ''),
            'status' => (string) ($payment['status'] ?? ''),
            'reference' => (string) ($payment['reference'] ?? ''),
            'notes' => (string) ($payment['notes'] ?? ''),
            'paid_via' => (string) ($payment['paid_via'] ?? ''),
            'customer_name' => (string) ($payment['customer_name'] ?? ''),
            'manual_transfer' => [
                'bank_name' => (string) data_get($payment, 'manual_transfer.bank_name', ''),
                'account_name' => (string) data_get($payment, 'manual_transfer.account_name', ''),
                'account_number' => (string) data_get($payment, 'manual_transfer.account_number', ''),
                'base_amount' => max((int) data_get($payment, 'manual_transfer.base_amount', 0), 0),
                'unique_code' => max((int) data_get($payment, 'manual_transfer.unique_code', 0), 0),
                'expected_amount' => max((int) data_get($payment, 'manual_transfer.expected_amount', 0), 0),
                'matched_by' => (string) data_get($payment, 'manual_transfer.matched_by', ''),
                'matched_at' => data_get($payment, 'manual_transfer.matched_at') instanceof CarbonImmutable
                    ? data_get($payment, 'manual_transfer.matched_at')->toIso8601String()
                    : data_get($payment, 'manual_transfer.matched_at'),
                'source_adapter' => (string) data_get($payment, 'manual_transfer.source_adapter', ''),
                'evidence' => [
                    'message_id' => (string) data_get($payment, 'manual_transfer.evidence.message_id', ''),
                    'ws_ref' => (string) data_get($payment, 'manual_transfer.evidence.ws_ref', ''),
                    'sender_name' => (string) data_get($payment, 'manual_transfer.evidence.sender_name', ''),
                    'account_number' => (string) data_get($payment, 'manual_transfer.evidence.account_number', ''),
                    'credit_amount' => max((int) data_get($payment, 'manual_transfer.evidence.credit_amount', 0), 0),
                    'transaction_at' => (string) data_get($payment, 'manual_transfer.evidence.transaction_at', ''),
                    'from_address' => (string) data_get($payment, 'manual_transfer.evidence.from_address', ''),
                    'raw_payload' => is_array(data_get($payment, 'manual_transfer.evidence.raw_payload'))
                        ? data_get($payment, 'manual_transfer.evidence.raw_payload')
                        : [],
                ],
            ],
            'qris' => [
                'invoice_id' => (string) data_get($payment, 'qris.invoice_id', ''),
                'content' => (string) data_get($payment, 'qris.content', ''),
                'nmid' => (string) data_get($payment, 'qris.nmid', ''),
                'request_date' => data_get($payment, 'qris.request_date') instanceof CarbonImmutable
                    ? data_get($payment, 'qris.request_date')->toIso8601String()
                    : data_get($payment, 'qris.request_date'),
                'expires_at' => data_get($payment, 'qris.expires_at') instanceof CarbonImmutable
                    ? data_get($payment, 'qris.expires_at')->toIso8601String()
                    : data_get($payment, 'qris.expires_at'),
                'last_checked_at' => data_get($payment, 'qris.last_checked_at') instanceof CarbonImmutable
                    ? data_get($payment, 'qris.last_checked_at')->toIso8601String()
                    : data_get($payment, 'qris.last_checked_at'),
                'raw_status' => (string) data_get($payment, 'qris.raw_status', ''),
            ],
        ];
    }

    protected function qrisConfig(): array
    {
        $settings = CentralSetting::paymentMethodSettings();
        $apikey = trim((string) data_get($settings, 'qris.api_key', ''));
        $merchantId = trim((string) data_get($settings, 'qris.merchant_id', ''));
        $baseUrl = trim((string) data_get($settings, 'qris.base_url', ''));
        $useTip = (string) data_get($settings, 'qris.use_tip', 'no');

        if ($apikey === '') {
            $apikey = trim((string) config('services.interactive_qris.apikey'));
        }

        if ($merchantId === '') {
            $merchantId = trim((string) config('services.interactive_qris.merchant_id'));
        }

        if ($baseUrl === '') {
            $baseUrl = rtrim((string) config('services.interactive_qris.base_url', ''), '/');
        }

        if ($useTip === '') {
            $useTip = (string) config('services.interactive_qris.use_tip', 'no');
        }

        return [
            'ready' => $apikey !== '' && $merchantId !== '',
            'base_url' => rtrim($baseUrl, '/'),
            'apikey' => $apikey,
            'merchant_id' => $merchantId,
            'use_tip' => $useTip,
        ];
    }

    protected function manualTransferConfig(): array
    {
        $settings = CentralSetting::paymentMethodSettings();
        $bankName = trim((string) data_get($settings, 'manual_transfer.bank_name', ''));
        $accountName = trim((string) data_get($settings, 'manual_transfer.account_name', ''));
        $accountNumber = trim((string) data_get($settings, 'manual_transfer.account_number', ''));
        $notes = trim((string) data_get($settings, 'manual_transfer.notes', ''));

        return [
            'bank_name' => $bankName !== '' ? $bankName : trim((string) config('services.billing_payment.manual_transfer.bank_name', '')),
            'account_name' => $accountName !== '' ? $accountName : trim((string) config('services.billing_payment.manual_transfer.account_name', '')),
            'account_number' => $accountNumber !== '' ? $accountNumber : trim((string) config('services.billing_payment.manual_transfer.account_number', '')),
            'notes' => $notes !== '' ? $notes : trim((string) config('services.billing_payment.manual_transfer.notes', '')),
        ];
    }

    protected function manualTransferPaymentPayload(int $baseAmount, array $reservedExpectedAmounts = []): array
    {
        $transferConfig = $this->manualTransferConfig();
        $allocation = $this->manualTransferService->allocateUniqueCode($baseAmount, $reservedExpectedAmounts);

        return [
            'bank_name' => (string) ($transferConfig['bank_name'] ?? ''),
            'account_name' => (string) ($transferConfig['account_name'] ?? ''),
            'account_number' => (string) ($transferConfig['account_number'] ?? ''),
            'base_amount' => (int) ($allocation['base_amount'] ?? $baseAmount),
            'unique_code' => (int) ($allocation['unique_code'] ?? 0),
            'expected_amount' => (int) ($allocation['expected_amount'] ?? $baseAmount),
            'matched_by' => '',
            'matched_at' => null,
            'source_adapter' => '',
            'evidence' => [],
        ];
    }

    protected function billingAutomationState(): array
    {
        return [
            'auto_generate' => $this->normalizeBillingRunState(
                CentralSetting::jsonSetting(CentralSetting::BILLING_AUTO_GENERATE_STATE_KEY)
            ),
            'reminders' => $this->normalizeBillingRunState(
                CentralSetting::jsonSetting(CentralSetting::BILLING_REMINDER_STATE_KEY)
            ),
        ];
    }

    protected function normalizeBillingRunState(array $state): array
    {
        return [
            'ran_at' => $this->parseIsoTimestamp($state['ran_at'] ?? null),
            'source' => (string) ($state['source'] ?? ''),
            'reminder_days' => max((int) ($state['reminder_days'] ?? 0), 0),
            'generated_count' => (int) ($state['generated_count'] ?? 0),
            'tenant_count' => (int) ($state['tenant_count'] ?? 0),
            'generated_invoices' => is_array($state['generated_invoices'] ?? null) ? $state['generated_invoices'] : [],
            'overdue_count' => (int) ($state['overdue_count'] ?? 0),
            'expiring_soon_count' => (int) ($state['expiring_soon_count'] ?? 0),
            'overdue_tenants' => is_array($state['overdue_tenants'] ?? null) ? $state['overdue_tenants'] : [],
            'expiring_soon_tenants' => is_array($state['expiring_soon_tenants'] ?? null) ? $state['expiring_soon_tenants'] : [],
            'notification_delivery' => is_array($state['notification_delivery'] ?? null) ? $state['notification_delivery'] : [],
        ];
    }

    protected function deliverBillingReminderNotifications(array $result): array
    {
        $settings = CentralSetting::notificationChannelSettings();
        $shouldSendOverdue = (bool) data_get($settings, 'events.billing_due_reminder', true)
            && (int) ($result['overdue_count'] ?? 0) > 0;
        $shouldSendExpiring = (bool) data_get($settings, 'events.subscription_expiry_reminder', true)
            && (int) ($result['expiring_soon_count'] ?? 0) > 0;

        if (! $shouldSendOverdue && ! $shouldSendExpiring) {
            return [
                'telegram' => ['status' => 'skipped', 'message' => 'Tidak ada event reminder aktif yang perlu dikirim.'],
                'whatsapp' => ['status' => 'skipped', 'message' => 'Tidak ada event reminder aktif yang perlu dikirim.'],
            ];
        }

        $message = $this->buildBillingReminderMessage($result);

        return $this->dispatchChannelNotifications(
            $settings,
            $message,
            'billing.reminder_dispatched',
            [
                'target_type' => 'billing',
                'target_id' => 'reminder-scan',
                'meta' => [
                    'overdue_count' => (int) ($result['overdue_count'] ?? 0),
                    'expiring_soon_count' => (int) ($result['expiring_soon_count'] ?? 0),
                ],
            ]
        );
    }

    protected function buildBillingReminderMessage(array $result): string
    {
        $settings = CentralSetting::notificationChannelSettings();
        $template = (string) data_get($settings, 'templates.billing_reminder', '');

        return $this->templateRenderer->render($template, [
            'scan_time' => CarbonImmutable::now()->format('d M Y H:i'),
            'overdue_count' => (int) ($result['overdue_count'] ?? 0),
            'expiring_count' => (int) ($result['expiring_soon_count'] ?? 0),
        ]);
    }

    protected function dispatchPaymentSuccessNotifications(Tenant $tenant, array $invoice): void
    {
        $this->billingNotificationService->dispatchPaymentSuccess($tenant, $invoice);
    }

    protected function dispatchChannelNotifications(array $settings, string $message, string $eventKey, array $context = []): array
    {
        $result = [
            'telegram' => ['status' => 'skipped', 'message' => 'Channel Telegram tidak aktif.'],
            'whatsapp' => ['status' => 'skipped', 'message' => 'Channel WhatsApp tidak aktif.'],
        ];

        if ((bool) data_get($settings, 'default_channels.telegram', false) && (bool) data_get($settings, 'telegram.enabled', false)) {
            SendCentralChannelMessageJob::dispatch(
                'telegram',
                (array) data_get($settings, 'telegram', []),
                $message,
                $eventKey,
                $context
            );
            $result['telegram'] = ['status' => 'queued', 'message' => 'Telegram notification queued.'];
        }

        if ((bool) data_get($settings, 'default_channels.whatsapp', false) && (bool) data_get($settings, 'whatsapp_cloud.enabled', false)) {
            $whatsAppConfig = (array) data_get($settings, 'whatsapp_cloud', []);
            $whatsAppConfig['default_recipient_phone'] = data_get($settings, 'whatsapp_cloud.default_recipient_phone', '');

            SendCentralChannelMessageJob::dispatch(
                'whatsapp',
                $whatsAppConfig,
                $message,
                $eventKey,
                $context
            );
            $result['whatsapp'] = ['status' => 'queued', 'message' => 'WhatsApp notification queued.'];
        }

        return $result;
    }

    protected function sendBillingReminderTelegram(string $message, array $settings): array
    {
        if (! (bool) data_get($settings, 'default_channels.telegram', false)) {
            return ['status' => 'skipped', 'message' => 'Channel Telegram tidak diaktifkan sebagai default.'];
        }

        if (! (bool) data_get($settings, 'telegram.enabled', false)) {
            return ['status' => 'skipped', 'message' => 'Telegram bot belum diaktifkan.'];
        }

        $botToken = trim((string) data_get($settings, 'telegram.bot_token', ''));
        $chatId = trim((string) data_get($settings, 'telegram.default_chat_id', ''));

        if ($botToken === '' || $chatId === '') {
            return ['status' => 'skipped', 'message' => 'Bot token atau default chat ID Telegram belum lengkap.'];
        }

        try {
            $response = Http::timeout(20)
                ->acceptJson()
                ->post(sprintf('https://api.telegram.org/bot%s/sendMessage', $botToken), [
                    'chat_id' => $chatId,
                    'text' => $message,
                ]);
        } catch (Throwable $throwable) {
            return ['status' => 'failed', 'message' => $throwable->getMessage()];
        }

        $payload = $response->json();

        if (! $response->ok() || ! (bool) ($payload['ok'] ?? false)) {
            return ['status' => 'failed', 'message' => (string) ($payload['description'] ?? 'Telegram menolak request reminder.')];
        }

        return [
            'status' => 'sent',
            'message' => 'Reminder Telegram berhasil dikirim.',
            'chat_id' => $chatId,
            'message_id' => (string) data_get($payload, 'result.message_id', '-'),
        ];
    }

    protected function sendBillingReminderWhatsApp(string $message, array $settings): array
    {
        if (! (bool) data_get($settings, 'default_channels.whatsapp', false)) {
            return ['status' => 'skipped', 'message' => 'Channel WhatsApp tidak diaktifkan sebagai default.'];
        }

        if (! (bool) data_get($settings, 'whatsapp_cloud.enabled', false)) {
            return ['status' => 'skipped', 'message' => 'WhatsApp Cloud API belum diaktifkan.'];
        }

        $accessToken = trim((string) data_get($settings, 'whatsapp_cloud.access_token', ''));
        $phoneNumberId = trim((string) data_get($settings, 'whatsapp_cloud.phone_number_id', ''));
        $recipientPhone = preg_replace('/\D+/', '', (string) data_get($settings, 'whatsapp_cloud.default_recipient_phone', '')) ?? '';

        if ($accessToken === '' || $phoneNumberId === '' || $recipientPhone === '') {
            return ['status' => 'skipped', 'message' => 'Access token, phone number ID, atau nomor admin WhatsApp belum lengkap.'];
        }

        try {
            $response = Http::timeout(20)
                ->withToken($accessToken)
                ->acceptJson()
                ->post(self::WHATSAPP_GRAPH_BASE_URL . '/' . $phoneNumberId . '/messages', [
                    'messaging_product' => 'whatsapp',
                    'to' => $recipientPhone,
                    'type' => 'text',
                    'text' => [
                        'preview_url' => false,
                        'body' => $message,
                    ],
                ]);
        } catch (Throwable $throwable) {
            return ['status' => 'failed', 'message' => $throwable->getMessage()];
        }

        $payload = $response->json();

        if (! $response->ok()) {
            return ['status' => 'failed', 'message' => (string) data_get($payload, 'error.message', 'WhatsApp menolak request reminder.')];
        }

        return [
            'status' => 'sent',
            'message' => 'Reminder WhatsApp berhasil dikirim.',
            'recipient' => $recipientPhone,
            'message_id' => (string) data_get($payload, 'messages.0.id', '-'),
        ];
    }

    protected function parseIsoTimestamp(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (Throwable) {
            return null;
        }
    }

    protected function tenantInvoiceNumber(Tenant $tenant, int $sequence, CarbonImmutable $issuedAt): string
    {
        $tenantCode = strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $tenant->id));
        $tenantCode = $tenantCode !== '' ? substr($tenantCode, 0, 8) : 'TENANT';

        return sprintf('INV-%s-%s-%04d', $tenantCode, $issuedAt->format('Ym'), $sequence);
    }

    protected function tenantDomainHosts(Tenant $tenant): array
    {
        return $tenant->domains
            ->map(fn ($domain): ?string => $this->normalizeTenantDomainHost((string) data_get($domain, 'domain')))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    protected function normalizeTenantDomainHost(string $domain): ?string
    {
        $normalizedDomain = strtolower(trim($domain));

        if ($normalizedDomain === '') {
            return null;
        }

        if (str_contains($normalizedDomain, '.')) {
            return $normalizedDomain;
        }

        $centralDomain = (string) config('tenancy.central_domains.0');

        return $centralDomain !== ''
            ? $normalizedDomain . '.' . strtolower($centralDomain)
            : $normalizedDomain;
    }

    protected function tenantDatabaseName(Tenant $tenant): ?string
    {
        try {
            return $tenant->database()->getName();
        } catch (Throwable) {
            return null;
        }
    }

    protected function tenantWorkspaceSnapshot(Tenant $tenant): array
    {
        $tenantSaasType = $this->normalizedTenantSaasType($tenant);
        $blueprint = CentralSetting::platformBlueprint($tenantSaasType);
        $snapshot = [
            'brand_name' => (string) (data_get($tenant, 'name') ?? data_get($tenant, 'id')),
            'description' => (string) $blueprint['tenant_description'],
            'theme_color' => (string) $blueprint['theme_color'],
            'source' => 'blueprint',
        ];

        if (! class_exists(\Modules\BaseFeature\Models\TenantSetting::class)) {
            return $snapshot;
        }

        try {
            if (tenancy()->initialized) {
                tenancy()->end();
            }

            tenancy()->initialize($tenant);

            if (! Schema::connection('tenant')->hasTable('tenant_settings')) {
                return $snapshot;
            }

            $tenantSetting = \Modules\BaseFeature\Models\TenantSetting::query()->first();

            if (! $tenantSetting) {
                return $snapshot;
            }

            return [
                'brand_name' => (string) ($tenantSetting->brand_name ?: data_get($tenant, 'name') ?: data_get($tenant, 'id')),
                'description' => (string) ($tenantSetting->description ?: $blueprint['tenant_description']),
                'theme_color' => (string) ($tenantSetting->theme_color ?: $blueprint['theme_color']),
                'source' => 'tenant_settings',
            ];
        } catch (Throwable) {
            return $snapshot;
        } finally {
            if (tenancy()->initialized) {
                tenancy()->end();
            }
        }
    }

    protected function syncTenantBranding(Tenant $tenant, array $blueprint): bool
    {
        if (! class_exists(\Modules\BaseFeature\Models\TenantSetting::class)) {
            return false;
        }

        try {
            if (tenancy()->initialized) {
                tenancy()->end();
            }

            tenancy()->initialize($tenant);

            if (! Schema::connection('tenant')->hasTable('tenant_settings')) {
                return false;
            }

            $tenantSetting = \Modules\BaseFeature\Models\TenantSetting::query()->first()
                ?? new \Modules\BaseFeature\Models\TenantSetting();

            $tenantSetting->fill([
                'brand_name' => $tenantSetting->brand_name ?: (string) (data_get($tenant, 'name') ?? data_get($tenant, 'id')),
                'description' => $blueprint['tenant_description'],
                'theme_color' => $blueprint['theme_color'],
            ])->save();

            return true;
        } catch (Throwable) {
            return false;
        } finally {
            if (tenancy()->initialized) {
                tenancy()->end();
            }
        }
    }

    protected function tenantUserWorkspace(Tenant $tenant): array
    {
        return $this->inTenantContext($tenant, function (): array {
            $schemaReady = $this->tenantUserSchemaReady();

            if ($schemaReady) {
                $this->ensureTenantUserRoleRecords();
            }

            $roles = $schemaReady
                ? $this->tenantUserRoles()->map(fn (Role $role): array => [
                    'id' => (string) $role->getKey(),
                    'name' => (string) $role->name,
                    'slug' => (string) $role->slug,
                ])->values()->all()
                : [];

            $users = $schemaReady
                ? User::query()
                    ->with('role')
                    ->orderByDesc('is_active')
                    ->orderBy('name')
                    ->get()
                    ->map(fn (User $user): array => [
                        'id' => (string) $user->getKey(),
                        'name' => (string) $user->name,
                        'email' => (string) $user->email,
                        'role_id' => (string) ($user->role_id ?? ''),
                        'role_name' => (string) ($user->role?->name ?? 'User'),
                        'role_slug' => (string) ($user->roleSlug() ?? 'staff'),
                        'is_active' => $user->isActiveUser(),
                    ])
                    ->values()
                    ->all()
                : [];

            return [
                'schema_ready' => $schemaReady,
                'roles' => $roles,
                'users' => $users,
                'stats' => [
                    'total' => count($users),
                    'active' => count(array_filter($users, fn (array $user): bool => (bool) ($user['is_active'] ?? false))),
                    'inactive' => count(array_filter($users, fn (array $user): bool => ! (bool) ($user['is_active'] ?? false))),
                    'owners' => count(array_filter($users, fn (array $user): bool => (string) ($user['role_slug'] ?? '') === 'owner' && (bool) ($user['is_active'] ?? false))),
                ],
            ];
        }, [
            'schema_ready' => false,
            'roles' => [],
            'users' => [],
            'stats' => [
                'total' => 0,
                'active' => 0,
                'inactive' => 0,
                'owners' => 0,
            ],
        ]);
    }

    protected function tenantUserRoles()
    {
        return Role::query()
            ->whereIn('slug', ['owner', 'admin', 'staff'])
            ->orderByRaw("case slug when 'owner' then 1 when 'admin' then 2 else 3 end")
            ->get();
    }

    protected function tenantUserFind(string $userId): User
    {
        return User::query()->with('role')->findOrFail($userId);
    }

    protected function guardTenantUserCriticalChange(User $user, ?string $nextRoleSlug, bool $nextIsActive): void
    {
        $currentRoleSlug = $user->roleSlug();

        if ($currentRoleSlug !== 'owner') {
            return;
        }

        if ($nextRoleSlug === 'owner' && $nextIsActive) {
            return;
        }

        $activeOwnerIds = Role::query()
            ->where('slug', 'owner')
            ->pluck('id');

        $activeOwnersCount = User::query()
            ->whereIn('role_id', $activeOwnerIds)
            ->where('is_active', true)
            ->count();

        if ($activeOwnersCount <= 1) {
            throw ValidationException::withMessages([
                'role_id' => 'Tenant harus punya minimal satu owner aktif.',
            ]);
        }
    }

    protected function ensureTenantUserRoleRecords(): void
    {
        foreach ([
            ['name' => 'Owner', 'slug' => 'owner'],
            ['name' => 'Admin', 'slug' => 'admin'],
            ['name' => 'Staff', 'slug' => 'staff'],
        ] as $role) {
            Role::query()->firstOrCreate(
                ['slug' => $role['slug']],
                ['name' => $role['name']]
            );
        }
    }

    protected function tenantUserSchemaReady(): bool
    {
        return Schema::connection('tenant')->hasTable('users')
            && Schema::connection('tenant')->hasTable('roles')
            && Schema::connection('tenant')->hasColumn('users', 'role_id')
            && Schema::connection('tenant')->hasColumn('users', 'is_active');
    }

    protected function ensureTenantUserSchemaReadyForWrite(): void
    {
        if ($this->tenantUserSchemaReady()) {
            return;
        }

        throw ValidationException::withMessages([
            'users' => 'Fondasi role/status user tenant belum siap. Jalankan migrasi tenant terbaru dulu.',
        ]);
    }

    protected function inTenantContext(Tenant $tenant, Closure $callback, mixed $fallback = null): mixed
    {
        try {
            if (tenancy()->initialized) {
                tenancy()->end();
            }

            tenancy()->initialize($tenant);

            return $callback();
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable) {
            return $fallback;
        } finally {
            if (tenancy()->initialized) {
                tenancy()->end();
            }
        }
    }
}
