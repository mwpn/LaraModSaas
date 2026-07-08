<?php

declare(strict_types=1);

namespace App\Providers;

use App\Http\Middleware\EnsureTenantIsActive;
use App\Models\CentralSetting;
use App\Services\Central\TenantEntitlementResolver;
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
            EnsureTenantIsActive::class,
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

        RateLimiter::for('central-login', function (Request $request) {
            $email = Str::lower((string) $request->input('email', 'guest'));

            return Limit::perMinute(5)->by('central-login:' . $email . '|' . $request->ip());
        });

        RateLimiter::for('tenant-login', function (Request $request) {
            $email = Str::lower((string) $request->input('email', 'guest'));

            return Limit::perMinute(5)->by('tenant-login:' . Str::lower($request->getHost()) . '|' . $email . '|' . $request->ip());
        });

        RateLimiter::for('manual-transfer-check', function (Request $request) {
            return Limit::perMinute(5)->by(
                'manual-transfer-check:' . $request->ip()
                . '|' . (string) $request->route('tenant')
                . '|' . (string) $request->route('invoice')
            );
        });

        RateLimiter::for('manual-transfer-evidence', function (Request $request) {
            return Limit::perMinute(20)->by(
                'manual-transfer-evidence:' . $request->ip()
            );
        });

        Event::listen(TenancyBootstrapped::class, function (TenancyBootstrapped $event): void {
            $tenant = $event->tenancy->tenant;
            $saasType = Str::lower((string) data_get($tenant, 'saas_type', 'universal'));
            $blueprint = CentralSetting::platformBlueprint($saasType);

            $modules = app('modules')->all();
            $moduleNames = array_values(array_map(
                static fn ($module) => $module->getName(),
                $modules
            ));

            /** @var TenantEntitlementResolver $resolver */
            $resolver = app(TenantEntitlementResolver::class);
            $enabled = array_values(array_unique(array_merge(
                ['BaseFeature'],
                $tenant instanceof \App\Models\Tenant
                    ? $resolver->enabledModuleNames($tenant)
                    : CentralSetting::runtimeEnabledModules($saasType)
            )));

            $statuses = [];
            foreach ($moduleNames as $moduleName) {
                $statuses[$moduleName] = in_array($moduleName, $enabled, true);
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
                'theme_color' => $blueprint['theme_color'],
                'description' => $blueprint['tenant_description'],
            ]));
        });
    }
}
