<?php

declare(strict_types=1);

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Throwable;

class RegistrationController extends Controller
{
    public function store(Request $request): JsonResponse|RedirectResponse
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

        $subdomain = strtolower($validated['subdomain']);
        $centralConnection = DB::connection(
            config('tenancy.database.central_connection', config('database.default'))
        );
        $tenant = new Tenant([
            'id' => $subdomain,
            'name' => $validated['business_name'],
        ]);

        try {
            $centralConnection->beginTransaction();

            $tenant->save();

            $tenant->domains()->create([
                'domain' => $subdomain,
            ]);

            $centralConnection->commit();
        } catch (Throwable $exception) {
            if ($centralConnection->transactionLevel() > 0) {
                $centralConnection->rollBack();
            }

            $this->compensateFailedProvisioning($tenant);
            report($exception);

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Provisioning tenant gagal. Rollback otomatis sudah dijalankan.',
                ], 500);
            }

            return back()
                ->withInput()
                ->withErrors([
                    'subdomain' => 'Provisioning tenant gagal. Silakan coba lagi.',
                ]);
        }

        $tenantUrl = sprintf('http://%s.%s/login', $subdomain, config('tenancy.central_domains.0'));

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Tenant berhasil dibuat.',
                'tenant_id' => $tenant->id,
                'tenant_url' => $tenantUrl,
            ], 201);
        }

        return redirect()->away($tenantUrl);
    }

    protected function compensateFailedProvisioning(Tenant $tenant): void
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        $databaseName = $tenant->database()->getName();
        $databaseManager = $tenant->database()->manager();

        if (filled($databaseName) && $databaseManager->databaseExists($databaseName)) {
            $databaseManager->deleteDatabase($tenant);
        }

        Tenant::query()
            ->whereKey($tenant->getKey())
            ->delete();
    }
}
