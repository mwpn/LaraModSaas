<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Fluent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\View;
use Stancl\Tenancy\Events\TenancyBootstrapped;
use Stancl\Tenancy\Middleware\InitializeTenancyBySubdomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    public static function tenantRouteMiddleware(): array
    {
        return [
            'web',
            InitializeTenancyBySubdomain::class,
            PreventAccessFromCentralDomains::class,
            'throttle:tenant',
        ];
    }

    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->loadMigrationsFrom(database_path('migrations/central'));

        RateLimiter::for('tenant', function (Request $request) {
            $host = Str::lower($request->getHost());
            $centralDomains = config('tenancy.central_domains', []);

            $tenantFragment = collect($centralDomains)
                ->map(function (string $centralDomain) use ($host): ?string {
                    $centralDomain = Str::lower($centralDomain);

                    if ($host === $centralDomain || ! Str::endsWith($host, '.' . $centralDomain)) {
                        return null;
                    }

                    return explode('.', $host)[0] ?: null;
                })
                ->first(fn (?string $fragment) => filled($fragment));

            return Limit::perMinute(60)->by($tenantFragment ? 'tenant:' . $tenantFragment : 'tenant:central:' . $host);
        });

        Event::listen(TenancyBootstrapped::class, function (TenancyBootstrapped $event): void {
            $tenant = $event->tenancy->tenant;
            $saasType = Str::lower((string) data_get($tenant, 'saas_type', 'universal'));

            $typeToEnabledModules = [
                'resto' => ['RestoPOS'],
                'hotel' => ['HospitalityHub'],
                'tirta' => ['TirtaBilling'],
                'netbilling' => ['NetBilling'],
            ];

            $modules = app('modules')->all();
            $moduleNames = array_values(array_map(
                static fn ($module) => $module->getName(),
                $modules
            ));

            $alwaysEnabled = ['BaseFeature'];
            $enabledForType = $typeToEnabledModules[$saasType] ?? [];
            $enabled = array_values(array_unique(array_merge($alwaysEnabled, $enabledForType)));

            $statuses = [];
            foreach ($moduleNames as $moduleName) {
                $statuses[$moduleName] = $saasType === 'universal' || in_array($moduleName, $enabled, true);
            }

            config(['modules.statuses' => $statuses]);

            $tenantSetting = null;

            if (class_exists(\Modules\BaseFeature\Models\TenantSetting::class)) {
                try {
                    if (\Illuminate\Support\Facades\Schema::connection('tenant')->hasTable('tenant_settings')) {
                        $tenantSetting = \Modules\BaseFeature\Models\TenantSetting::query()->first();
                    }
                } catch (Throwable) {
                }
            }

            View::share('tenantSetting', $tenantSetting ?: new Fluent([
                'brand_name' => data_get($tenant, 'name') ?? data_get($tenant, 'id'),
                'theme_color' => '#000000',
                'description' => null,
            ]));
        });
    }
}
