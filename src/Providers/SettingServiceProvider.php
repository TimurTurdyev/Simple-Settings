<?php

namespace TimurTurdyev\SimpleSettings\Providers;


use Illuminate\Support\ServiceProvider;

class SettingServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');

            $this->publishes([
                __DIR__ . '/../../config/simple-settings.php' => config_path('simple-settings.php'),
            ]);
        }
    }
}
