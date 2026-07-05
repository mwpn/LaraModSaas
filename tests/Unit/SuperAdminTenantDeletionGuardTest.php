<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Http\Controllers\Central\SuperAdminTenantController;
use App\Models\Tenant;
use App\Services\Central\CentralAuditLogger;
use App\Services\Central\MessageTemplateRenderer;
use Carbon\CarbonImmutable;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Validator;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Factory as ValidationFactory;
use PHPUnit\Framework\TestCase;

class SuperAdminTenantDeletionGuardTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $container = new Container();
        $translator = new Translator(new ArrayLoader(), 'en');
        $validatorFactory = new ValidationFactory($translator, $container);

        $container->instance('validator', $validatorFactory);
        $container->instance('translator', $translator);

        Facade::setFacadeApplication($container);
        Validator::swap($validatorFactory);
    }

    public function test_it_blocks_delete_when_collectible_invoice_exists(): void
    {
        $controller = $this->makeController();
        $tenant = new Tenant([
            'id' => 'tenant-overdue',
            'billing_invoices' => [
                [
                    'invoice_number' => 'INV-OVERDUE',
                    'status' => 'overdue',
                    'due_at' => CarbonImmutable::now()->subDays(5)->toIso8601String(),
                    'paid_at' => null,
                ],
            ],
        ]);

        try {
            $controller->guardDelete($tenant);
            self::fail('ValidationException was not thrown.');
        } catch (ValidationException $exception) {
            self::assertStringContainsString('masih punya invoice collectible', (string) $exception->getMessage());
            self::assertStringContainsString('INV-OVERDUE', (string) $exception->getMessage());
        }
    }

    public function test_it_blocks_delete_when_paid_invoice_history_exists(): void
    {
        $controller = $this->makeController();
        $tenant = new Tenant([
            'id' => 'tenant-paid',
            'billing_invoices' => [
                [
                    'invoice_number' => 'INV-PAID',
                    'status' => 'paid',
                    'due_at' => CarbonImmutable::now()->subDays(10)->toIso8601String(),
                    'paid_at' => CarbonImmutable::now()->subDays(9)->toIso8601String(),
                ],
            ],
        ]);

        try {
            $controller->guardDelete($tenant);
            self::fail('ValidationException was not thrown.');
        } catch (ValidationException $exception) {
            self::assertStringContainsString('histori invoice paid', (string) $exception->getMessage());
            self::assertStringContainsString('INV-PAID', (string) $exception->getMessage());
        }
    }

    public function test_it_allows_delete_when_no_collectible_or_paid_history_exists(): void
    {
        $controller = $this->makeController();
        $tenant = new Tenant([
            'id' => 'tenant-clean',
            'billing_invoices' => [
                [
                    'invoice_number' => 'INV-VOID',
                    'status' => 'void',
                    'due_at' => CarbonImmutable::now()->subDays(10)->toIso8601String(),
                    'paid_at' => null,
                ],
            ],
        ]);

        self::assertNull($controller->guardDelete($tenant));
    }

    protected function makeController(): object
    {
        return new class(new CentralAuditLogger(), new MessageTemplateRenderer()) extends SuperAdminTenantController
        {
            public function guardDelete(Tenant $tenant): void
            {
                $this->ensureTenantCanBeDeleted($tenant);
            }
        };
    }
}
