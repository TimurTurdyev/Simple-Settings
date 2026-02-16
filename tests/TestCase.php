<?php

namespace TimurTurdyev\SimpleSettings\Tests;

use Illuminate\Support\Facades\Cache;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        Cache::flush();
    }

    protected function getPackageProviders($app): array
    {
        return [
            \TimurTurdyev\SimpleSettings\Providers\SettingServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        
        $app['config']->set('simple-settings.table_name', 'simple_settings');
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }
}
