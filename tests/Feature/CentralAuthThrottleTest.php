<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CentralAuthThrottleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->boolean('is_active')->default(true);
            $table->uuid('role_id')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function test_central_login_is_rate_limited_after_repeated_failed_attempts(): void
    {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $response = $this->from('http://aircloud.biz.id/login')
                ->post('http://aircloud.biz.id/login', [
                    'email' => 'owner@aircloud.test',
                    'password' => 'wrong-password',
                ]);

            $response->assertSessionHasErrors('email');
        }

        $response = $this->from('http://aircloud.biz.id/login')
            ->post('http://aircloud.biz.id/login', [
                'email' => 'owner@aircloud.test',
                'password' => 'wrong-password',
            ]);

        $response->assertStatus(429);
    }
}
