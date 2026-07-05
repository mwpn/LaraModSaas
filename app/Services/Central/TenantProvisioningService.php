<?php

declare(strict_types=1);

namespace App\Services\Central;

use App\Models\CentralSetting;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class TenantProvisioningService
{
    public function provision(string $businessName, string $subdomain, ?string $saasType = null): Tenant
    {
        $businessName = trim($businessName);
        $subdomain = strtolower(trim($subdomain));
        $saasType = $saasType ?: CentralSetting::platformSaasType();
        $defaultPackageCode = CentralSetting::defaultPackageCode($saasType);

        $centralConnection = DB::connection(
            config('tenancy.database.central_connection', config('database.default'))
        );

        $tenant = new Tenant([
            'id' => $subdomain,
            'name' => $businessName,
            'saas_type' => $saasType,
            'package_code' => $defaultPackageCode,
            'package_assigned_at' => now()->toIso8601String(),
        ]);

        try {
            $centralConnection->beginTransaction();

            $tenant->save();

            $tenant->domains()->create([
                'domain' => $subdomain,
            ]);

            $centralConnection->commit();

            return $tenant;
        } catch (Throwable $exception) {
            if ($centralConnection->transactionLevel() > 0) {
                $centralConnection->rollBack();
            }

            $this->compensateFailedProvisioning($tenant);

            throw $exception;
        }
    }

    public function tenantLoginUrl(Tenant $tenant): string
    {
        return sprintf('http://%s.%s/login', $tenant->id, config('tenancy.central_domains.0'));
    }

    public function provisionWithOwner(
        string $businessName,
        string $subdomain,
        string $ownerName,
        string $ownerEmail,
        ?string $saasType = null,
    ): array {
        $tenant = $this->provision($businessName, $subdomain, $saasType);
        $plainPassword = Str::password(14);

        try {
            if (tenancy()->initialized) {
                tenancy()->end();
            }

            tenancy()->initialize($tenant);

            if (! Schema::connection('tenant')->hasTable('users') || ! Schema::connection('tenant')->hasTable('roles')) {
                throw new \RuntimeException('Skema user tenant belum siap untuk auto-create owner.');
            }

            $ownerRole = Role::query()->where('slug', 'owner')->first();

            if (! $ownerRole) {
                throw new \RuntimeException('Role owner tenant tidak ditemukan.');
            }

            $owner = User::query()->updateOrCreate(
                ['email' => trim(strtolower($ownerEmail))],
                [
                    'name' => trim($ownerName),
                    'password' => Hash::make($plainPassword),
                    'role_id' => $ownerRole->getKey(),
                    'is_active' => true,
                ]
            );

            return [
                'tenant' => $tenant,
                'owner_user_id' => (string) $owner->getKey(),
                'owner_email' => (string) $owner->email,
                'owner_password' => $plainPassword,
            ];
        } catch (Throwable $exception) {
            $this->compensateFailedProvisioning($tenant);

            throw $exception;
        } finally {
            if (tenancy()->initialized) {
                tenancy()->end();
            }
        }
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
