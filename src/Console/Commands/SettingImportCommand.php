<?php

declare(strict_types=1);

namespace TimurTurdyev\SimpleSettings\Console\Commands;

use TimurTurdyev\SimpleSettings\Concerns\CastsValue;
use TimurTurdyev\SimpleSettings\Contracts\SettingStorageInterface;

class SettingImportCommand extends BaseCommand
{
    use CastsValue;

    protected $signature = 'setting:import {file : Path to JSON file} {--replace : Wipe existing settings in affected groups before import} {--g|group= : Restrict import to a specific group from the file}';

    protected $description = 'Import settings from a JSON file (produced by setting:export)';

    public function handle(SettingStorageInterface $settings): int
    {
        $file = $this->argument('file');
        $groupFilter = $this->option('group');
        $replace = (bool) $this->option('replace');

        if (!is_file($file) || !is_readable($file)) {
            $this->error("File not found or not readable: [{$file}].");
            return self::FAILURE;
        }

        $raw = file_get_contents($file);
        if ($raw === false) {
            $this->error("Failed to read [{$file}].");
            return self::FAILURE;
        }

        $rows = json_decode($raw, true);
        if (!is_array($rows)) {
            $this->error('Invalid JSON: ' . json_last_error_msg());
            return self::FAILURE;
        }

        foreach ($rows as $index => $row) {
            if (!is_array($row) || !isset($row['group'], $row['name'], $row['type']) || !array_key_exists('val', $row)) {
                $this->error("Invalid entry at index {$index}: expected keys group, name, type, val.");
                return self::FAILURE;
            }
        }

        if ($groupFilter !== null) {
            $rows = array_values(array_filter($rows, fn($r) => $r['group'] === $groupFilter));
        }

        if ($rows === []) {
            $this->info('Nothing to import.');
            return self::SUCCESS;
        }

        if ($replace) {
            $groups = array_unique(array_column($rows, 'group'));
            if (!$this->confirm('Replace all existing settings in groups [' . implode(', ', $groups) . ']?', false)) {
                $this->info('Cancelled.');
                return self::SUCCESS;
            }
            foreach ($groups as $group) {
                $settings->forGroup($group)->removeAll();
            }
        }

        $grouped = [];
        foreach ($rows as $row) {
            $grouped[$row['group']][$row['name']] = self::castValue($row['val'], $row['type']);
        }

        foreach ($grouped as $group => $pairs) {
            $settings->forGroup($group)->set($pairs);
        }

        $this->info('Imported ' . count($rows) . ' setting(s) from [' . $file . '].');

        return self::SUCCESS;
    }
}
