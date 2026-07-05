<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected ?string $testDatabasePath = null;

    public function createApplication()
    {
        $this->testDatabasePath = sys_get_temp_dir() . '/laramodsaas-test-' . bin2hex(random_bytes(8)) . '.sqlite';

        if (file_exists($this->testDatabasePath)) {
            @unlink($this->testDatabasePath);
        }

        touch($this->testDatabasePath);

        $pdo = new \PDO('sqlite:' . $this->testDatabasePath);
        $pdo->exec('CREATE TABLE IF NOT EXISTS central_settings ("key" VARCHAR PRIMARY KEY, "value" TEXT NULL)');

        putenv('APP_ENV=testing');
        putenv('DB_CONNECTION=sqlite');
        putenv('DB_DATABASE=' . $this->testDatabasePath);
        putenv('CENTRAL_DOMAIN=aircloud.biz.id');

        $_ENV['APP_ENV'] = 'testing';
        $_ENV['DB_CONNECTION'] = 'sqlite';
        $_ENV['DB_DATABASE'] = $this->testDatabasePath;
        $_ENV['CENTRAL_DOMAIN'] = 'aircloud.biz.id';
        $_SERVER['APP_ENV'] = 'testing';
        $_SERVER['DB_CONNECTION'] = 'sqlite';
        $_SERVER['DB_DATABASE'] = $this->testDatabasePath;
        $_SERVER['CENTRAL_DOMAIN'] = 'aircloud.biz.id';

        $app = require Application::inferBasePath() . '/bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        if ($this->testDatabasePath && file_exists($this->testDatabasePath)) {
            @unlink($this->testDatabasePath);
        }
    }
}
