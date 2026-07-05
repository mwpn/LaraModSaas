<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Central\MessageTemplateRenderer;
use PHPUnit\Framework\TestCase;

class MessageTemplateRendererTest extends TestCase
{
    public function test_it_replaces_known_placeholders(): void
    {
        $renderer = new MessageTemplateRenderer();

        $message = $renderer->render(
            'Halo {{tenant_name}}, invoice {{invoice_number}} status {{payment_status}}.',
            [
                'tenant_name' => 'Demo Tenant',
                'invoice_number' => 'INV-001',
                'payment_status' => 'PAID',
            ]
        );

        self::assertSame('Halo Demo Tenant, invoice INV-001 status PAID.', $message);
    }

    public function test_it_leaves_unknown_placeholders_untouched(): void
    {
        $renderer = new MessageTemplateRenderer();

        $message = $renderer->render('Halo {{known}} {{unknown}}', [
            'known' => 'Bro',
        ]);

        self::assertSame('Halo Bro {{unknown}}', $message);
    }
}
