<?php

declare(strict_types=1);

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Models\CentralSetting;
use App\Models\DemoRequest;
use App\Services\Central\CentralAuditLogger;
use App\Services\Central\TenantProvisioningService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class SuperAdminLeadController extends Controller
{
    public function __construct(
        protected TenantProvisioningService $tenantProvisioningService,
        protected CentralAuditLogger $auditLogger,
    ) {
    }

    public function index(Request $request): View
    {
        $platformType = CentralSetting::platformSaasType();
        $search = trim((string) $request->string('q'));
        $status = trim((string) $request->string('status'));

        $leadQuery = DemoRequest::query();

        if ($search !== '') {
            $leadQuery->where(function ($query) use ($search): void {
                $query->where('name', 'like', '%' . $search . '%')
                    ->orWhere('email', 'like', '%' . $search . '%')
                    ->orWhere('phone_number', 'like', '%' . $search . '%');
            });
        }

        if (in_array($status, DemoRequest::availableStatuses(), true)) {
            $leadQuery->where('status', $status);
        }

        $leads = $leadQuery
            ->latest()
            ->get();

        $allLeads = DemoRequest::query()->get();

        return view('central.demo-requests', [
            'platformType' => $platformType,
            'centralAccent' => CentralSetting::platformBlueprint($platformType)['theme_color'],
            'leads' => $leads,
            'availableStatuses' => DemoRequest::availableStatuses(),
            'filters' => [
                'q' => $search,
                'status' => $status,
            ],
            'stats' => [
                'total' => $allLeads->count(),
                'new' => $allLeads->filter(fn (DemoRequest $lead): bool => $lead->normalizedStatus() === DemoRequest::STATUS_NEW)->count(),
                'contacted' => $allLeads->filter(fn (DemoRequest $lead): bool => $lead->normalizedStatus() === DemoRequest::STATUS_CONTACTED)->count(),
                'qualified' => $allLeads->filter(fn (DemoRequest $lead): bool => $lead->normalizedStatus() === DemoRequest::STATUS_QUALIFIED)->count(),
                'converted' => $allLeads->filter(fn (DemoRequest $lead): bool => $lead->normalizedStatus() === DemoRequest::STATUS_CONVERTED)->count(),
            ],
        ]);
    }

    public function updateStatus(Request $request, int $id): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(DemoRequest::availableStatuses())],
        ]);

        $lead = DemoRequest::query()->findOrFail($id);
        $nextStatus = (string) $validated['status'];
        $now = now();

        $attributes = [
            'status' => $nextStatus,
        ];

        if ($nextStatus === DemoRequest::STATUS_NEW) {
            $attributes['last_contacted_at'] = null;
            $attributes['converted_at'] = null;
        } elseif ($nextStatus === DemoRequest::STATUS_CONVERTED) {
            $attributes['last_contacted_at'] = $now;
            $attributes['converted_at'] = $now;
        } else {
            $attributes['last_contacted_at'] = $now;
            $attributes['converted_at'] = null;
        }

        $lead->fill($attributes)->save();

        $this->auditLogger->info(
            'lead.status_updated',
            sprintf('Status lead %s diubah ke %s.', $lead->name, $lead->statusLabel()),
            [
                'target_type' => 'demo_request',
                'target_id' => (string) $lead->getKey(),
                'meta' => ['status' => $lead->normalizedStatus()],
            ]
        );

        return redirect()
            ->route('central.super-admin.leads.index')
            ->with('status', sprintf('Status lead %s berhasil diubah ke %s.', $lead->name, $lead->statusLabel()));
    }

    public function convertToTenant(Request $request, int $id): RedirectResponse
    {
        $validated = $request->validate([
            'business_name' => ['required', 'string', 'max:255'],
            'subdomain' => [
                'required',
                'string',
                'max:63',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('domains', 'domain'),
            ],
        ]);

        $lead = DemoRequest::query()->findOrFail($id);

        if ($lead->isConverted()) {
            throw ValidationException::withMessages([
                'business_name' => 'Lead ini sudah pernah dikonversi menjadi tenant.',
            ]);
        }

        try {
            $provisioned = $this->tenantProvisioningService->provisionWithOwner(
                (string) $validated['business_name'],
                (string) $validated['subdomain'],
                (string) $lead->name,
                (string) $lead->email,
                (string) $lead->platform_type
            );
        } catch (Throwable $exception) {
            report($exception);
            $this->auditLogger->error(
                'lead.convert_failed',
                sprintf('Provisioning tenant dari lead %s gagal.', $lead->name),
                [
                    'target_type' => 'demo_request',
                    'target_id' => (string) $lead->getKey(),
                    'meta' => ['error' => $exception->getMessage()],
                ]
            );

            throw ValidationException::withMessages([
                'subdomain' => 'Provisioning tenant dari lead gagal. Silakan cek log lalu coba lagi.',
            ]);
        }

        $tenant = $provisioned['tenant'];

        $lead->fill([
            'status' => DemoRequest::STATUS_CONVERTED,
            'last_contacted_at' => now(),
            'converted_at' => now(),
            'converted_tenant_id' => $tenant->id,
        ])->save();

        $this->auditLogger->info(
            'lead.converted',
            sprintf('Lead %s berhasil dikonversi menjadi tenant %s.', $lead->name, $tenant->id),
            [
                'target_type' => 'tenant',
                'target_id' => (string) $tenant->id,
                'meta' => [
                    'lead_id' => (string) $lead->getKey(),
                    'owner_email' => $provisioned['owner_email'],
                ],
            ]
        );

        return redirect()
            ->route('central.super-admin.leads.index')
            ->with('status', sprintf(
                'Lead %s berhasil dikonversi menjadi tenant %s.',
                $lead->name,
                $tenant->id
            ))
            ->with('provisioned_owner', [
                'email' => $provisioned['owner_email'],
                'password' => $provisioned['owner_password'],
                'login_url' => $this->tenantProvisioningService->tenantLoginUrl($tenant),
            ]);
    }
}
