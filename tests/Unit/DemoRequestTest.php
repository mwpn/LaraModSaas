<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\DemoRequest;
use PHPUnit\Framework\TestCase;

class DemoRequestTest extends TestCase
{
    public function test_it_normalizes_unknown_status_to_new(): void
    {
        $lead = new DemoRequest([
            'status' => '  INVALID ',
        ]);

        self::assertSame(DemoRequest::STATUS_NEW, $lead->normalizedStatus());
        self::assertSame('New', $lead->statusLabel());
    }

    public function test_it_formats_whatsapp_url_to_indonesia_format(): void
    {
        $lead = new DemoRequest([
            'name' => 'Budi',
            'phone_number' => '0812-3456-7890',
            'platform_type' => 'resto',
        ]);

        $url = $lead->whatsappUrl();

        self::assertStringStartsWith('https://wa.me/6281234567890?text=', $url);
        self::assertStringContainsString('Budi', rawurldecode($url));
        self::assertStringContainsString('Resto', rawurldecode($url));
    }

    public function test_it_marks_converted_only_when_status_and_tenant_id_exist(): void
    {
        $lead = new DemoRequest([
            'status' => DemoRequest::STATUS_CONVERTED,
            'converted_tenant_id' => 'tenant-demo',
        ]);

        self::assertTrue($lead->isConverted());

        $missingTenantId = new DemoRequest([
            'status' => DemoRequest::STATUS_CONVERTED,
        ]);

        self::assertFalse($missingTenantId->isConverted());
    }
}
