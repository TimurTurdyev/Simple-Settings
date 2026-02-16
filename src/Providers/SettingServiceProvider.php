<?php

namespace TimurTurdyev\SimpleSettings\Providers;

use Illuminate\Support\ServiceProvider;
use TimurTurdyev\SimpleSettings\Console\Commands\SettingClearCommand;
use TimurTurdyev\SimpleSettings\Console\Commands\SettingDeleteCommand;
use TimurTurdyev\SimpleSettings\Console\Commands\SettingGetCommand;
use TimurTurdyev\SimpleSettings\Console\Commands\SettingListCommand;
use TimurTurdyev\SimpleSettings\Console\Commands\SettingSetCommand;
use TimurTurdyev\SimpleSettings\Contracts\SettingStorageInterface;
use TimurTurdyev\SimpleSettings\SettingStorage;

class SettingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SettingStorageInterface::class, function ($app) {
            return new SettingStorage();
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');

            $this->publishes([
                __DIR__ . '/../../config/simple-settings.php' => config_path('simple-settings.php'),
            ]);

            $this->commands([
                SettingGetCommand::class,
                SettingSetCommand::class,
                SettingListCommand::class,
                SettingClearCommand::class,
                SettingDeleteCommand::class,
            ]);
        }
    }
}
