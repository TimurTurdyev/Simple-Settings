<?php

namespace TimurTurdyev\SimpleSettings\Console\Commands;

class SettingSetCommand extends BaseCommand
{
    protected $signature = 'setting:set {key : The setting key} {value : The setting value} {--g|group=global : The setting group}';

    protected $description = 'Set a setting value';

    public function handle(): int
    {
        $key = $this->argument('key');
        $value = $this->argument('value');
        $group = $this->option('group');

        try {
            $value = $this->parseValue($value);
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        $this->storage($group)->set($key, $value);

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
            $decoded = json_decode($value, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \InvalidArgumentException('Invalid JSON: ' . json_last_error_msg());
            }

            return $decoded;
        }

        return $value;
    }
}
