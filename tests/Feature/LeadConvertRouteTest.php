<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\DemoRequest;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Central\TenantProvisioningService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LeadConvertRouteTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('roles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->uuid('role_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('demo_requests', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('phone_number', 32);
            $table->string('platform_type')->default('universal');
            $table->string('status')->default('new');
            $table->timestamp('last_contacted_at')->nullable();
            $table->timestamp('converted_at')->nullable();
            $table->string('converted_tenant_id')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
        });

        Schema::create('domains', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('domain')->unique();
            $table->string('tenant_id')->nullable();
            $table->timestamps();
        });

        Schema::create('central_audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('actor_email')->nullable();
            $table->string('level', 16)->default('info');
            $table->string('event_key', 120);
            $table->string('target_type', 80)->nullable();
            $table->string('target_id', 120)->nullable();
            $table->string('summary');
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function test_owner_can_convert_lead_and_audit_log_is_written(): void
    {
        $role = new Role([
            'id' => 'role-owner',
            'name' => 'Owner',
            'slug' => 'owner',
        ]);

        $user = new User([
            'id' => 1,
            'name' => 'Central Owner',
            'email' => 'owner@aircloud.test',
            'password' => 'secret',
            'role_id' => 'role-owner',
            'is_active' => true,
        ]);
        $user->setRelation('role', $role);

        $lead = DemoRequest::query()->create([
            'name' => 'Budi',
            'email' => 'budi@example.com',
            'phone_number' => '08123456789',
            'platform_type' => 'resto',
            'status' => DemoRequest::STATUS_QUALIFIED,
        ]);

        app()->instance(TenantProvisioningService::class, new class extends TenantProvisioningService
        {
            public function provisionWithOwner(string $businessName, string $subdomain, string $ownerName, string $ownerEmail, ?string $saasType = null): array
            {
                return [
                    'tenant' => new Tenant([
                        'id' => $subdomain,
                        'name' => $businessName,
                        'saas_type' => $saasType,
                    ]),
                    'owner_user_id' => 'tenant-owner-1',
                    'owner_email' => $ownerEmail,
                    'owner_password' => 'TempPass123!',
                ];
            }

            public function tenantLoginUrl(Tenant $tenant): string
            {
                return 'http://' . $tenant->id . '.aircloud.biz.id/login';
            }
        });

        $response = $this->actingAs($user, 'central')->post(
            'http://aircloud.biz.id/super-admin/demo-requests/' . $lead->id . '/convert',
            [
                'business_name' => 'Resto Budi',
                'subdomain' => 'resto-budi',
            ]
        );

        $response->assertRedirect(route('central.super-admin.leads.index', absolute: false));
        $response->assertSessionHas('provisioned_owner');

        $lead->refresh();

        $this->assertSame(DemoRequest::STATUS_CONVERTED, $lead->status);
        $this->assertSame('resto-budi', $lead->converted_tenant_id);
        $this->assertDatabaseHas('central_audit_logs', [
            'event_key' => 'lead.converted',
            'target_type' => 'tenant',
            'target_id' => 'resto-budi',
            'actor_email' => 'owner@aircloud.test',
        ]);
    }
}
