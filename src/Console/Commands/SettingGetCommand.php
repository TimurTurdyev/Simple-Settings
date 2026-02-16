<?php

namespace TimurTurdyev\SimpleSettings\Console\Commands;

use Illuminate\Console\Command;

class SettingGetCommand extends Command
{
    protected $signature = 'setting:get {key : The setting key} {--g|group=global : The setting group} {--fresh : Bypass cache}';

    protected $description = 'Get a setting value';

    public function handle(): int
    {
        $key = $this->argument('key');
        $group = $this->option('group');
        $fresh = $this->option('fresh');

        $setting = app(\TimurTurdyev\SimpleSettings\Contracts\SettingStorageInterface::class)
            ->forGroup($group);

        $value = $setting->get($key, null, $fresh);

        if (is_null($value)) {
            $this->error("Setting [{$key}] in group [{$group}] not found.");

            return self::FAILURE;
        }

        $this->info("Value: " . json_encode($value, JSON_PRETTY_PRINT));

        return self::SUCCESS;
    }
}
