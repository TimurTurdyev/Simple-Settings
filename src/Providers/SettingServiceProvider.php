<?php

declare(strict_types=1);

namespace TimurTurdyev\SimpleSettings\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use TimurTurdyev\SimpleSettings\Console\Commands\SettingClearCommand;
use TimurTurdyev\SimpleSettings\Console\Commands\SettingDeleteCommand;
use TimurTurdyev\SimpleSettings\Console\Commands\SettingGetCommand;
use TimurTurdyev\SimpleSettings\Console\Commands\SettingListCommand;
use TimurTurdyev\SimpleSettings\Console\Commands\SettingSetCommand;
use TimurTurdyev\SimpleSettings\Contracts\SettingStorageInterface;
use TimurTurdyev\SimpleSettings\Events\SettingDeleted;
use TimurTurdyev\SimpleSettings\Events\SettingSaved;
use TimurTurdyev\SimpleSettings\Listeners\RecordSettingChange;
use TimurTurdyev\SimpleSettings\SettingStorage;

class SettingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../../config/simple-settings.php',
            'simple-settings'
        );

        $this->app->singleton(SettingStorageInterface::class, function ($app) {
            return new SettingStorage();
        });
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');

        if ($this->app->runningInConsole()) {
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

        if ($this->app['config']->get('simple-settings.audit.enabled', false)) {
            Event::listen(SettingSaved::class, [RecordSettingChange::class, 'handleSaved']);
            Event::listen(SettingDeleted::class, [RecordSettingChange::class, 'handleDeleted']);
        }
    }
}
