<?php

namespace TimurTurdyev\SimpleSettings\Console\Commands;

use Illuminate\Console\Command;

class SettingSetCommand extends Command
{
    protected $signature = 'setting:set {key : The setting key} {value : The setting value} {--g|group=global : The setting group}';

    protected $description = 'Set a setting value';

    public function handle(): int
    {
        $key = $this->argument('key');
        $value = $this->argument('value');
        $group = $this->option('group');

        $value = $this->parseValue($value);

        $setting = app(\TimurTurdyev\SimpleSettings\Contracts\SettingStorageInterface::class)
            ->forGroup($group);

        $setting->set($key, $value);

        $this->info("Setting [{$key}] in group [{$group}] has been set.");

        return self::SUCCESS;
    }

    private function parseValue(string $value): mixed
    {
        if ($value === 'true') {
            return true;
        }

        if ($value === 'false') {
            return false;
        }

        if ($value === 'null') {
            return null;
        }

        if (is_numeric($value)) {
            return str_contains($value, '.') ? (float)$value : (int)$value;
        }

        if (str_starts_with($value, '[') || str_starts_with($value, '{')) {
            return json_decode($value, true);
        }

        return $value;
    }
}
